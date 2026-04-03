<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProformaInvoicePayment extends Model
{
    protected $fillable = [
        'hotel_id',
        'proforma_invoice_id',
        'amount',
        'received_at',
        'payment_method',
        'reference',
        'report_bucket_key',
        'notes',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'received_at' => 'datetime',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function proformaInvoice(): BelongsTo
    {
        return $this->belongsTo(ProformaInvoice::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
