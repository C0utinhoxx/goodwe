<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChargingSession extends Model
{
    protected $fillable = [
        'user_id',
        'establishment_id',
        'charger_name',
        'energy_kwh',
        'amount_charged',
        'payment_method',
        'status',
    ];

    protected $casts = [
        'energy_kwh' => 'decimal:2',
        'amount_charged' => 'decimal:2',
    ];

    public const PAYMENT_WALLET = 'wallet';

    public const PAYMENT_POINTS = 'points';

    public const PAYMENT_SIMULATED = 'simulated';

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }
}
