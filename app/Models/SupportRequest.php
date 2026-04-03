<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportRequest extends Model
{
    protected $fillable = [
        'hotel_id',
        'user_id',
        'subject',
        'message',
        'status',
    ];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SupportRequestResponse::class)->orderBy('created_at');
    }
}
