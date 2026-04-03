<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class HotelGalleryImage extends Model
{
    protected $fillable = ['hotel_id', 'path', 'caption', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function getUrlAttribute(): string
    {
        return Storage::url($this->path);
    }
}
