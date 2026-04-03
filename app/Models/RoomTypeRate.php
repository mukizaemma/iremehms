<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomTypeRate extends Model
{
    protected $fillable = [
        'room_type_id',
        'rate_type',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }
}
