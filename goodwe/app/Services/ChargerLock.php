<?php

namespace App\Services;

use App\Models\Charger;
use Illuminate\Support\Facades\DB;

class ChargerLock
{
    public static function acquire(int $chargerId): Charger
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::table('chargers')->where('id', $chargerId)->update(['id' => $chargerId]);
        }

        return Charger::whereKey($chargerId)->lockForUpdate()->firstOrFail();
    }
}
