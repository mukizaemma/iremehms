<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdditionalCharge extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotel_id',
        'type',
        'name',
        'code',
        'description',
        'default_amount',
        'charge_rule',
        'is_tax_inclusive',
        'is_active',
    ];

    /** Charge types for display and validation */
    public const TYPE_SERVICE = 'service';
    public const TYPE_EQUIPMENT = 'equipment';
    public const TYPE_LATE_CHECKOUT = 'late-checkout';
    public const TYPE_EXTRA_BED = 'extra_bed';
    public const TYPE_EXTRA_PERSON = 'extra_person';

    public const TYPES = [
        self::TYPE_SERVICE => 'Service',
        self::TYPE_EQUIPMENT => 'Equipment',
        self::TYPE_LATE_CHECKOUT => 'Late checkout',
        self::TYPE_EXTRA_BED => 'Extra bed',
        self::TYPE_EXTRA_PERSON => 'Extra person',
    ];

    protected $casts = [
        'default_amount' => 'decimal:2',
        'is_tax_inclusive' => 'boolean',
        'is_active' => 'boolean',
    ];

    public const CHARGE_RULES = [
        'per_instance' => 'Per Instance',
        'per_night' => 'Per Night',
        'per_person' => 'Per Person',
        'per_adult' => 'Per Adult',
        'per_child' => 'Per Child',
        'per_booking' => 'Per Booking',
        'per_quantity' => 'Per Quantity',
    ];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }
}
