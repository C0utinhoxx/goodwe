<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointMovement extends Model
{
    use HasFactory;

    public const TYPES = ['deposit', 'redemption', 'refund', 'penalty', 'adjustment'];

    protected $fillable = ['user_id', 'transaction_id', 'coupon_id', 'type', 'amount', 'balance_after', 'description', 'event_key', 'metadata'];

    protected $casts = ['amount' => 'decimal:2', 'balance_after' => 'decimal:2', 'metadata' => 'array'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }
}
