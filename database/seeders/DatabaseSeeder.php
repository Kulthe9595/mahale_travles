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

        $this->fillBusinessDetails(BusinessSetting::current());
    }

    /** Contact details from mahalestravels.in; only fills fields that are still empty. */
    private function fillBusinessDetails(BusinessSetting $settings): void
    {
        $defaults = [
            'phone' => '+91 97656 79191',
            'email' => 'mahale.veeru1@gmail.com',
            'address' => 'Navale Colony, Nashik–Pune Highway, near Shivaji Maharaj Statue, Nashik Road, Maharashtra',
        ];

        foreach ($defaults as $field => $value) {
            if (blank($settings->{$field})) {
                $settings->{$field} = $value;
            }
        }

        $settings->save();
    }
}
