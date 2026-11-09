<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\BusinessSetting;
use App\Models\User;
use App\Support\Installation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/** One-time setup for shared hosting without SSH: creates tables, settings and the owner login. */
class InstallController extends Controller
{
    public function create(): Response
    {
        abort_unless(Installation::open(), 404);

        return Inertia::render('Install', ['databaseOk' => $this->databaseOk()]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(Installation::open(), 404);

        $data = $request->validate([
            'install_code' => ['required', 'string'],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! hash_equals((string) config('app.install_code'), trim($data['install_code']))) {
            throw ValidationException::withMessages(['install_code' => 'This setup code is not correct. It is in the README that came with the upload package.']);
        }

        try {
            Artisan::call('migrate', ['--force' => true]);
        } catch (Throwable $e) {
            throw ValidationException::withMessages([
                'install_code' => 'Could not set up the database. Check the DB_ lines in .env. ('.$e->getMessage().')',
            ]);
        }

        $owner = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => Role::Owner,
        ]);
        BusinessSetting::current()->fillDefaults();

        Auth::login($owner, true);
        $request->session()->regenerate();

        return redirect('/')->with('success', 'Setup complete. Welcome!');
    }

    private function databaseOk(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
