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
        'cnpj', 'pricing_mode', 'surge_percentage', 'peak_tariff', 'active_peak_id',
        'max_power_kw', 'max_current_a',
    ];

    protected $casts = [
        'cost_per_kwh' => 'decimal:4', 'reservation_tolerance_minutes' => 'integer', 'no_show_penalty_points' => 'decimal:2',
        'surge_percentage' => 'decimal:2', 'peak_tariff' => 'decimal:4',
        'max_power_kw' => 'decimal:2', 'max_current_a' => 'decimal:2',
    ];

    public const TYPE_RESIDENCIAL = 'residencial';

    public const TYPE_COMERCIAL = 'comercial';

    public const TYPE_SHOPPING = 'shopping';

    public const TYPE_OUTRO = 'outro';

    public function currentTariff(): ?string
    {
        return $this->peak_tariff ?? $this->cost_per_kwh;
    }

    public function activePeak(): BelongsTo
    {
        return $this->belongsTo(DemandPeak::class, 'active_peak_id');
    }

    public function demandPeaks(): HasMany
    {
        return $this->hasMany(DemandPeak::class);
    }

    public function tariffEvents(): HasMany
    {
        return $this->hasMany(TariffEvent::class);
    }

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

    public function demandReadings(): HasMany
    {
        return $this->hasMany(DemandReading::class);
    }

    /**
     * Indica se o estabelecimento possui limite de demanda elétrica configurado.
     */
    public function hasDemandLimit(): bool
    {
        return $this->max_power_kw !== null || $this->max_current_a !== null;
    }
}
