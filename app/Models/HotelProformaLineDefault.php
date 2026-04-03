<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelProformaLineDefault extends Model
{
    protected $fillable = [
        'hotel_id',
        'line_type',
        'report_bucket_key',
        'default_unit_price',
    ];

    protected function casts(): array
    {
        return [
            'default_unit_price' => 'decimal:2',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }
}
