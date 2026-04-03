<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProformaInvoice extends Model
{
    protected $fillable = [
        'hotel_id',
        'proforma_number',
        'status',
        'client_organization',
        'client_name',
        'client_email',
        'client_phone',
        'event_title',
        'service_start_date',
        'service_end_date',
        'notes',
        'payment_terms',
        'currency',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'grand_total',
        'reservation_id',
        'created_by',
        'sent_at',
        'accepted_at',
        'invoiced_at',
        'submitted_to_manager_at',
        'manager_verified_at',
        'manager_verified_by',
        'manager_rejected_at',
        'manager_rejection_note',
    ];

    protected function casts(): array
    {
        return [
            'service_start_date' => 'date',
            'service_end_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'invoiced_at' => 'datetime',
            'submitted_to_manager_at' => 'datetime',
            'manager_verified_at' => 'datetime',
            'manager_rejected_at' => 'datetime',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function managerVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_verified_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ProformaInvoiceLine::class)->orderBy('sort_order');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ProformaInvoicePayment::class)->orderByDesc('received_at');
    }
}
