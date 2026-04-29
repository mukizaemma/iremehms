<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockDailyReportComment extends Model
{
    protected $fillable = [
        'stock_daily_report_id',
        'user_id',
        'stage',
        'body',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(StockDailyReport::class, 'stock_daily_report_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
