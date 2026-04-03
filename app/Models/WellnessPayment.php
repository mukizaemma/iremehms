<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WellnessPayment extends Model
{
    protected $fillable = [
        'hotel_id',
        'reservation_id',
        'wellness_service_id',
        'service_name_snapshot',
        'billing_type_snapshot',
        'payment_kind',
        'destination',
        'amount',
        'received_at',
        'period_start',
        'period_end',
        'guest_name',
        'report_bucket_key',
        'notes',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'received_at' => 'datetime',
            'period_start' => 'date',
            'period_end' => 'date',
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

    public function wellnessService(): BelongsTo
    {
        return $this->belongsTo(WellnessService::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
