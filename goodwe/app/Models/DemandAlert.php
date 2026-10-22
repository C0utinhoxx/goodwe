<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemandAlert extends Model
{
    use HasFactory;

    public const TYPE_CHARGING_PEAK = 'charging_peak';
    public const TYPE_ENERGY_PEAK = 'energy_peak';

    public const LEVEL_WARNING = 'warning';
    public const LEVEL_CRITICAL = 'critical';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_RESOLVED = 'resolved';

    protected $fillable = [
        'establishment_id',
        'type',
        'level',
        'status',
        'title',
        'message',
        'measured_value',
        'threshold_value',
        'unit',
        'details',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'measured_value' => 'decimal:3',
        'threshold_value' => 'decimal:3',
        'details' => 'array',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }
}
