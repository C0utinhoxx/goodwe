<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Charger extends Model
{
    use HasFactory;

    protected $fillable = ['establishment_id', 'code', 'name', 'location', 'power_kw', 'connector', 'physical_status', 'physical_status_updated_at', 'tariff_override', 'lat', 'lng', 'active_reservation_id', 'active_session_id', 'activation_status'];

    protected $attributes = ['physical_status' => 'unavailable', 'activation_status' => 'pending'];

    protected $casts = ['power_kw' => 'decimal:2', 'tariff_override' => 'decimal:4', 'physical_status_updated_at' => 'datetime', 'lat' => 'float', 'lng' => 'float'];

    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    public function activeReservation(): BelongsTo
    {
        return $this->belongsTo(ChargingReservation::class, 'active_reservation_id');
    }

    public function activeSession(): BelongsTo
    {
        return $this->belongsTo(ChargingSession::class, 'active_session_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(ChargingReservation::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ChargingSession::class);
    }

    public function status(): string
    {
        if ($this->activation_status !== null && $this->activation_status !== 'active') {
            return 'unavailable';
        }
        if (! in_array($this->physical_status, ['available', 'in_use'], true)) {
            return 'unavailable';
        }
        if ($this->active_session_id || $this->physical_status === 'in_use') {
            return 'in_use';
        }

        return $this->active_reservation_id ? 'reserved' : 'available';
    }

    public function isAvailable(): bool
    {
        return $this->status() === 'available';
    }

    public function currentTariff(): ?string
    {
        return $this->establishment?->currentTariff();
    }

    public function canReserve(): bool
    {
        return $this->isAvailable() && ($this->establishment?->reservation_tolerance_minutes ?? 0) > 0 && (float) $this->currentTariff() > 0;
    }
}
