<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma leitura de demanda elétrica ativa (kW e A) reportada por um carregador.
 *
 * As leituras são usadas para agregar a demanda total dos totens em andamento
 * e compará-la com a capacidade configurada da infraestrutura elétrica local.
 */
class DemandReading extends Model
{
    use HasFactory;

    public const SOURCE_IOT = 'iot';

    public const SOURCE_FIRESTORE = 'firestore';

    public const SOURCE_MANUAL = 'manual';

    protected $fillable = [
        'establishment_id',
        'charger_id',
        'external_id',
        'active_power_kw',
        'current_a',
        'voltage_v',
        'recorded_at',
        'source',
        'metadata',
    ];

    protected $casts = [
        'active_power_kw' => 'decimal:3',
        'current_a' => 'decimal:3',
        'voltage_v' => 'decimal:2',
        'recorded_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    public function charger(): BelongsTo
    {
        return $this->belongsTo(Charger::class);
    }
}
