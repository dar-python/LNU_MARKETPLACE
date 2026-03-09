<?php

namespace App\Support;

use App\Models\User;

class AdminAccess
{
    public static function allows(User $user): bool
    {
        $user->loadMissing('roles:id,code');

        $hasAdminRole = $user->role === 'admin'
            || $user->roles->contains(static fn ($role): bool => $role->code === 'admin');

        if (! $hasAdminRole) {
            return false;
        }

        if ($user->currentAccessToken() === null) {
            return true;
        }

        return $user->tokenCan('admin');
    }
}
