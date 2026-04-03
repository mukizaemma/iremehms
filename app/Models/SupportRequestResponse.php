<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportRequestResponse extends Model
{
    protected $fillable = [
        'support_request_id',
        'user_id',
        'message',
    ];

    public function supportRequest(): BelongsTo
    {
        return $this->belongsTo(SupportRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
