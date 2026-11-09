<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/** Creates (or resets) the owner login without relying on .env or cached config. */
class CreateOwner extends Command
{
    protected $signature = 'app:create-owner {email} {--name=Owner}';

    protected $description = 'Create the owner login, or reset its password';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $password = (string) $this->secret('Password (min 8 characters)');
        $repeat = (string) $this->secret('Repeat password');

        $validator = Validator::make(
            ['email' => $email, 'password' => $password, 'password_confirmation' => $repeat],
            ['email' => ['required', 'email'], 'password' => ['required', 'string', 'min:8', 'confirmed']],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::query()->updateOrCreate(
            ['email' => $email],
            ['name' => (string) $this->option('name'), 'password' => $password, 'role' => Role::Owner],
        );

        $this->info("Owner login ready: {$email}");

        return self::SUCCESS;
    }
}
