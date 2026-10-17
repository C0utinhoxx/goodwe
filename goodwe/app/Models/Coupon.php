<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'code', 'tier', 'name', 'discount_percent', 'points_cost', 'status', 'redemption_key', 'redeemed_at', 'used_at', 'cancelled_at'];

    protected $casts = ['points_cost' => 'decimal:2', 'discount_percent' => 'integer', 'redeemed_at' => 'datetime', 'used_at' => 'datetime', 'cancelled_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function chargingSession(): HasOne
    {
        return $this->hasOne(ChargingSession::class);
    }
}
