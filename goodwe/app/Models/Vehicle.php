<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = ['brand', 'model', 'plate', 'battery_capacity_kwh'];

    protected $casts = ['battery_capacity_kwh' => 'decimal:1'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
