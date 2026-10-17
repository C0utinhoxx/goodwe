<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserBalanceLock
{
    public static function acquire(int $userId): User
    {
        // SQLite has no row locks; acquire its write lock before reading balances.
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::table('users')->where('id', $userId)->update(['id' => $userId]);
        }

        return User::whereKey($userId)->lockForUpdate()->firstOrFail();
    }
}
