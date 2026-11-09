<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class Installation
{
    /** The web installer is offered only when a code is configured and no owner exists yet. */
    public static function open(): bool
    {
        return filled(config('app.install_code')) && ! self::hasOwner();
    }

    public static function hasOwner(): bool
    {
        try {
            return Schema::hasTable('users') && User::where('role', Role::Owner)->exists();
        } catch (Throwable) {
            return false;
        }
    }
}
