<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WellnessService extends Model
{
    protected $fillable = [
        'hotel_id',
        'name',
        'code',
        'description',
        'billing_type',
        'default_price',
        'price_per_day',
        'price_monthly_subscription',
        'duration_minutes',
        'report_bucket_key',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'default_price' => 'decimal:2',
            'price_per_day' => 'decimal:2',
            'price_monthly_subscription' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(WellnessPayment::class);
    }
}
