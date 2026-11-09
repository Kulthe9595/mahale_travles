<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\BusinessSetting;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** Creates the owner login from OWNER_EMAIL / OWNER_PASSWORD and the settings row. */
    public function run(): void
    {
        $email = env('OWNER_EMAIL');
        $password = env('OWNER_PASSWORD');

        if ($email && $password) {
            User::query()->updateOrCreate(
                ['email' => $email],
                ['name' => env('OWNER_NAME', 'Owner'), 'password' => $password, 'role' => Role::Owner],
            );
        } else {
            $this->command?->warn('Set OWNER_EMAIL and OWNER_PASSWORD in .env to create the owner login.');
        }

        BusinessSetting::current()->fillDefaults();
    }
}
