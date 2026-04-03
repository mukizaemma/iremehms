<?php

namespace App\Models;

use App\Support\PaymentCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'invoice_id',
        'payment_method',
        'payment_status',
        'amount',
        'tip_amount',
        'tip_handling',
        'received_by',
        'received_at',
        'submitted_at',
        'client_reference',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'tip_amount' => 'decimal:2',
        'received_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    /** @deprecated Use PaymentCatalog::methods() */
    public const PAYMENT_METHODS = [
        'Cash' => 'Cash',
        'MoMo' => 'MoMo',
        'POS Card' => 'POS Card',
        'Bank' => 'Bank',
    ];

    public function isCashHeldByWaiter(): bool
    {
        return PaymentCatalog::normalizePosMethod($this->payment_method) === PaymentCatalog::METHOD_CASH
            && $this->submitted_at === null;
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
