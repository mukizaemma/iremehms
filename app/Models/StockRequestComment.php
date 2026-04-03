<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockRequestComment extends Model
{
    protected $fillable = [
        'stock_request_id',
        'user_id',
        'body',
    ];

    public function stockRequest(): BelongsTo
    {
        return $this->belongsTo(StockRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
