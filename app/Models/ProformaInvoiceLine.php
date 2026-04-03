<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProformaInvoiceLine extends Model
{
    protected $fillable = [
        'proforma_invoice_id',
        'sort_order',
        'line_type',
        'description',
        'report_bucket_key',
        'quantity',
        'unit_price',
        'discount_percent',
        'line_total',
        'metadata',
        'wellness_service_id',
        'wellness_pricing_mode',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'line_total' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function proformaInvoice(): BelongsTo
    {
        return $this->belongsTo(ProformaInvoice::class);
    }

    public function wellnessService(): BelongsTo
    {
        return $this->belongsTo(WellnessService::class);
    }
}
