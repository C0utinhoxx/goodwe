<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TariffEvent extends Model
{
    use HasFactory;

    protected $fillable = ['establishment_id', 'demand_peak_id', 'actor_id', 'type', 'previous_tariff', 'new_tariff', 'percentage', 'pricing_mode', 'metadata', 'occurred_at'];

    protected $casts = ['previous_tariff' => 'decimal:4', 'new_tariff' => 'decimal:4', 'percentage' => 'decimal:2', 'metadata' => 'array', 'occurred_at' => 'datetime'];

    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
