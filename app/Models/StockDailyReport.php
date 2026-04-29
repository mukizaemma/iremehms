<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockDailyReport extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'hotel_id',
        'report_date',
        'stock_location_id',
        'scope_key',
        'status',
        'prepared_by_id',
        'submitted_by_id',
        'verified_by_id',
        'approved_by_id',
        'rejected_by_id',
        'submitted_at',
        'verified_at',
        'approved_at',
        'rejected_at',
        'rejection_reason',
    ];

    protected $casts = [
        'report_date' => 'date',
        'submitted_at' => 'datetime',
        'verified_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function stockLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'stock_location_id');
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(StockDailyReportComment::class, 'stock_daily_report_id')->orderBy('created_at');
    }
}
