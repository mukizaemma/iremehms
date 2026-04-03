<?php

namespace App\Models;

use App\Support\PaymentCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationPayment extends Model
{
    protected $fillable = [
        'hotel_id',
        'reservation_id',
        'amount',
        'currency',
        'payment_type',
        'payment_method',
        'payment_status',
        'received_by',
        'received_at',
        'receipt_number',
        'status',
        'voided_at',
        'voided_by',
        'void_reason',
        'comment',
        'total_paid_after',
        'balance_after',
        'is_debt_settlement',
        'revenue_attribution_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'total_paid_after' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'received_at' => 'datetime',
        'voided_at' => 'datetime',
        'is_debt_settlement' => 'boolean',
        'revenue_attribution_date' => 'date',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    /**
     * Date key (Y-m-d) used for rooms sales / payment reports: back-dated debt settlements use revenue_attribution_date.
     */
    public function effectiveReportDateYmd(): string
    {
        if ($this->revenue_attribution_date) {
            return $this->revenue_attribution_date->format('Y-m-d');
        }

        return $this->received_at
            ? $this->received_at->format('Y-m-d')
            : '';
    }

    /**
     * Recompute reservation paid_amount and per-payment balance snapshots from stored payment lines.
     */
    public static function recomputeBalancesForReservation(int $reservationId): void
    {
        $reservation = Reservation::find($reservationId);
        if (! $reservation) {
            return;
        }

        $total = (float) ($reservation->total_amount ?? 0);
        $paidCum = 0.0;

        $payments = self::query()
            ->where('reservation_id', $reservationId)
            ->where('status', 'confirmed')
            ->orderBy('received_at')
            ->get();

        foreach ($payments as $p) {
            $ps = PaymentCatalog::normalizeStatus($p->payment_status ?? PaymentCatalog::STATUS_PAID);
            if (! PaymentCatalog::reservationPaymentCountsTowardPaid($ps)) {
                $p->total_paid_after = $paidCum;
                $p->balance_after = max(0, $total - $paidCum);
                $p->save();

                continue;
            }

            $paidCum += (float) ($p->amount ?? 0);
            $p->total_paid_after = $paidCum;
            $p->balance_after = max(0, $total - $paidCum);
            $p->save();
        }

        $reservation->paid_amount = max(0, min($total, $paidCum));
        $reservation->save();
    }
}

