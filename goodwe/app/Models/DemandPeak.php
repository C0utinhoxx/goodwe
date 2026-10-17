<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemandPeak extends Model
{
    use HasFactory;

    protected $fillable = ['establishment_id', 'external_id', 'criteria_reference', 'evidence', 'status', 'decision', 'pricing_mode', 'base_tariff', 'suggested_tariff', 'percentage', 'version', 'started_at', 'ended_at', 'end_event_id', 'end_evidence'];

    protected $casts = ['evidence' => 'array', 'end_evidence' => 'array', 'base_tariff' => 'decimal:4', 'suggested_tariff' => 'decimal:4', 'percentage' => 'decimal:2', 'version' => 'integer', 'started_at' => 'datetime', 'ended_at' => 'datetime'];

    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }
}
