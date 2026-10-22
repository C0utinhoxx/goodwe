<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemandAlertState extends Model
{
    use HasFactory;

    public const TYPE_CHARGING_PEAK = 'charging_peak';
    public const TYPE_ENERGY_PEAK = 'energy_peak';

    public const CONDITION_NORMAL = 'normal';
    public const CONDITION_PENDING = 'pending';
    public const CONDITION_ACTIVE = 'active';
    public const CONDITION_RECOVERING = 'recovering';

    protected $fillable = [
        'establishment_id',
        'type',
        'condition',
        'consecutive_checks',
        'first_detected_at',
        'last_detected_at',
        'last_checked_at',
        'last_measured_value',
    ];

    protected $casts = [
        'consecutive_checks' => 'integer',
        'first_detected_at' => 'datetime',
        'last_detected_at' => 'datetime',
        'last_checked_at' => 'datetime',
        'last_measured_value' => 'decimal:3',
    ];

    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    public function isActive(): bool
    {
        return $this->condition === self::CONDITION_ACTIVE;
    }

    public function isPending(): bool
    {
        return $this->condition === self::CONDITION_PENDING;
    }

    public function isRecovering(): bool
    {
        return $this->condition === self::CONDITION_RECOVERING;
    }
}
