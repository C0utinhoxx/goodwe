<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChargingEvent extends Model
{
    protected $fillable = ['charger_id', 'charging_reservation_id', 'charging_session_id', 'type', 'event_key', 'metadata', 'occurred_at'];

    protected $casts = ['metadata' => 'array', 'occurred_at' => 'datetime'];
}
