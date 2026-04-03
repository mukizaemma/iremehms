<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionInvoice extends Model
{
    protected $fillable = [
        'hotel_id',
        'invoice_number',
        'invoice_date',
        'amount',
        'due_date',
        'period_start',
        'period_end',
        'status',
        'reminder_7d_sent_at',
        'reminder_24h_sent_at',
        'paid_at',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'period_start' => 'date',
        'period_end' => 'date',
        'amount' => 'decimal:2',
        'reminder_7d_sent_at' => 'datetime',
        'reminder_24h_sent_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isOverdue(): bool
    {
        return $this->status === 'overdue' || ($this->due_date->isPast() && !$this->isPaid());
    }
}
