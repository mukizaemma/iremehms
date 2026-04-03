<?php

namespace App\Services;

use App\Models\Hotel;
use App\Models\SubscriptionInvoice;
use Carbon\Carbon;

class SubscriptionInvoiceService
{
    /**
     * Generate a subscription invoice for the hotel if next_due_date is within the next 15 days
     * and no invoice exists for that due date. Advances hotel next_due_date by one month.
     */
    public static function generateForHotel(Hotel $hotel): ?SubscriptionInvoice
    {
        if (!$hotel->next_due_date || !$hotel->subscription_amount || (float) $hotel->subscription_amount <= 0) {
            return null;
        }

        $today = Carbon::today();
        $nextDue = Carbon::parse($hotel->next_due_date);
        $windowEnd = $today->copy()->addDays(30);

        if ($nextDue->gt($windowEnd)) {
            return null;
        }

        $exists = SubscriptionInvoice::where('hotel_id', $hotel->id)
            ->whereDate('due_date', $nextDue)
            ->exists();
        if ($exists) {
            return null;
        }

        $periodStart = $nextDue->copy()->subMonth();
        $invoiceNumber = self::generateInvoiceNumber();

        $invoice = SubscriptionInvoice::create([
            'hotel_id' => $hotel->id,
            'invoice_number' => $invoiceNumber,
            'invoice_date' => $today,
            'amount' => $hotel->subscription_amount,
            'due_date' => $nextDue,
            'period_start' => $periodStart,
            'period_end' => $nextDue,
            'status' => 'sent',
        ]);

        $newNextDue = $hotel->subscription_type === 'monthly'
            ? $nextDue->copy()->addMonth()
            : $nextDue->copy()->addMonth();
        $hotel->update(['next_due_date' => $newNextDue->format('Y-m-d')]);

        return $invoice;
    }

    public static function generateInvoiceNumber(): string
    {
        $prefix = 'SUB-' . now()->format('Ymd') . '-';
        $last = SubscriptionInvoice::where('invoice_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('invoice_number');
        $seq = $last ? (int) substr($last, strlen($prefix)) + 1 : 1;
        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
