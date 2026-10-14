<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChargingSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'establishment_id',
        'charger_name',
        'energy_kwh',
        'amount_charged',
        'payment_method',
        'charger_id', 'charging_reservation_id', 'external_session_id', 'tariff_per_kwh', 'measured_energy_kwh', 'charging_state', 'payment_status', 'last_meter_sequence', 'started_at', 'ended_at', 'last_meter_at', 'stop_requested_at', 'stop_confirmed_at', 'paid_at', 'balance_exceeded',
        'status', 'original_amount', 'discount_amount', 'coupon_id', 'transaction_id', 'idempotency_key',
    ];

    protected $casts = [
        'tariff_per_kwh' => 'decimal:4', 'measured_energy_kwh' => 'decimal:4', 'last_meter_sequence' => 'integer', 'started_at' => 'datetime', 'ended_at' => 'datetime', 'last_meter_at' => 'datetime', 'stop_requested_at' => 'datetime', 'stop_confirmed_at' => 'datetime', 'paid_at' => 'datetime', 'balance_exceeded' => 'boolean',
        'energy_kwh' => 'decimal:2',
        'amount_charged' => 'decimal:2', 'original_amount' => 'decimal:2', 'discount_amount' => 'decimal:2',
    ];

    public const PAYMENT_WALLET = 'wallet';

    public const PAYMENT_POINTS = 'points';

    public const PAYMENT_SIMULATED = 'simulated';

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public function charger(): BelongsTo
    {
        return $this->belongsTo(Charger::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(ChargingReservation::class, 'charging_reservation_id');
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }
}
