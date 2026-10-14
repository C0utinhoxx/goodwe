<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Establishment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'address',
        'type',
        'operating_hours',
        'phone',
        'cost_per_kwh',
        'image', 'reservation_tolerance_minutes', 'no_show_penalty_points',
    ];

    protected $casts = [
        'cost_per_kwh' => 'decimal:4', 'reservation_tolerance_minutes' => 'integer', 'no_show_penalty_points' => 'decimal:2',
    ];

    public const TYPE_RESIDENCIAL = 'residencial';

    public const TYPE_COMERCIAL = 'comercial';

    public const TYPE_SHOPPING = 'shopping';

    public const TYPE_OUTRO = 'outro';

    public function chargers(): HasMany
    {
        return $this->hasMany(Charger::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function chargingSessions(): HasMany
    {
        return $this->hasMany(ChargingSession::class);
    }
}
