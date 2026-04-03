<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessDaySetting extends Model
{
    protected $fillable = [
        'hotel_id',
        'day_start_time',
        'auto_close_previous',
        'allow_manual_close',
    ];

    protected $casts = [
        'auto_close_previous' => 'boolean',
        'allow_manual_close' => 'boolean',
    ];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }
}
