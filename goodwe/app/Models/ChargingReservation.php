<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChargingReservation extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'charger_id', 'establishment_id', 'status', 'idempotency_key', 'tariff_per_kwh', 'reservation_fee', 'tolerance_seconds', 'no_show_penalty_points', 'penalty_applied_points', 'reserved_at', 'expires_at', 'cancelled_at', 'expired_at', 'started_at', 'penalty_processed_at'];

    protected $attributes = ['status' => 'active', 'penalty_applied_points' => '0.00', 'reservation_fee' => '0.00'];

    protected $casts = ['tariff_per_kwh' => 'decimal:4', 'reservation_fee' => 'decimal:2', 'no_show_penalty_points' => 'decimal:2', 'penalty_applied_points' => 'decimal:2', 'tolerance_seconds' => 'integer', 'reserved_at' => 'datetime', 'expires_at' => 'datetime', 'cancelled_at' => 'datetime', 'expired_at' => 'datetime', 'started_at' => 'datetime', 'penalty_processed_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function charger(): BelongsTo
    {
        return $this->belongsTo(Charger::class);
    }

    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }
}
