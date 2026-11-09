<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Owner-only SQL backup, for hosts without mysqldump or scheduled jobs. Restore by importing in phpMyAdmin. */
class BackupController extends Controller
{
    private const SKIP = ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens', 'sqlite_sequence'];

    public function __invoke(Request $request): StreamedResponse
    {
        abort_unless($request->user()->isOwner(), 403);

        $stamp = CarbonImmutable::now(config('app.timezone'))->format('Y-m-d_Hi');

        return response()->streamDownload(function () {
            $db = DB::connection();
            $pdo = $db->getPdo();
            $mysql = $db->getDriverName() === 'mysql';

            echo "-- Mahale Tours backup ".CarbonImmutable::now(config('app.timezone'))->toDateTimeString()."\n";
            echo "-- Restore: import this file into an empty database (phpMyAdmin > Import).\n\n";
            if ($mysql) {
                echo "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";
            }

            foreach (Schema::getTables() as $table) {
                $name = $table['name'];
                if (in_array($name, self::SKIP, true)) {
                    continue;
                }

                $create = $mysql
                    ? (array) DB::selectOne("SHOW CREATE TABLE `{$name}`")
                    : ['sql' => DB::selectOne('select sql from sqlite_master where type = ? and name = ?', ['table', $name])->sql];
                echo "DROP TABLE IF EXISTS `{$name}`;\n".end($create).";\n\n";

                foreach (DB::table($name)->cursor() as $row) {
                    $row = (array) $row;
                    $values = array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), array_values($row));
                    echo "INSERT INTO `{$name}` (`".implode('`, `', array_keys($row)).'`) VALUES ('.implode(', ', $values).");\n";
                }
                echo "\n";
            }

            if ($mysql) {
                echo "SET FOREIGN_KEY_CHECKS=1;\n";
            }
        }, "mahale-backup-{$stamp}.sql", ['Content-Type' => 'application/sql; charset=utf-8']);
    }
}
