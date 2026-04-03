<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelVideoTour extends Model
{
    protected $fillable = ['hotel_id', 'title', 'url', 'embed_code', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }
}
