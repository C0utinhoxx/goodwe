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
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public const TYPE_DEPOSIT = 'deposit';

    public const TYPE_CASHBACK = 'cashback';

    public const TYPE_CHARGE_PAYMENT = 'charge_payment';

    public const TYPE_POINTS_REDEEM = 'points_redeem';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
