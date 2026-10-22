<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'description',
        'reference',
        'status', 'credited_at', 'gateway_payment_id', 'refunded_amount', 'refund_requested_amount', 'refund_review_required',
    ];

    protected $casts = [
        'amount' => 'decimal:2', 'refunded_amount' => 'decimal:2', 'refund_requested_amount' => 'decimal:2', 'credited_at' => 'datetime', 'refund_review_required' => 'boolean',
    ];

    public const TYPE_DEPOSIT = 'deposit';

    public const TYPE_CASHBACK = 'cashback';

    public const TYPE_CHARGE_PAYMENT = 'charge_payment';

    public const TYPE_POINTS_REDEEM = 'points_redeem';

    public const TYPE_RESERVATION_FEE = 'reservation_fee';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
