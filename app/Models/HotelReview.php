<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelReview extends Model
{
    protected $fillable = ['hotel_id', 'guest_name', 'guest_email', 'rating', 'comment', 'is_approved'];

    protected $casts = ['is_approved' => 'boolean', 'rating' => 'integer'];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }
}
