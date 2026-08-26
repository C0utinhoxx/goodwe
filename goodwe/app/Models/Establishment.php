<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Establishment extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'address',
        'type',
        'operating_hours',
        'phone',
        'cost_per_kwh',
        'image',
    ];

    protected $casts = [
        'cost_per_kwh' => 'decimal:4',
    ];

    public const TYPE_RESIDENCIAL = 'residencial';

    public const TYPE_COMERCIAL = 'comercial';

    public const TYPE_SHOPPING = 'shopping';

    public const TYPE_OUTRO = 'outro';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function chargingSessions(): HasMany
    {
        return $this->hasMany(ChargingSession::class);
    }
}
