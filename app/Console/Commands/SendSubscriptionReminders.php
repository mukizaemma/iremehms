<?php

namespace App\Console\Commands;

use App\Models\SubscriptionInvoice;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendSubscriptionReminders extends Command
{
    protected $signature = 'subscription:send-reminders';

    protected $description = 'Send payment reminders 7 days and 24 hours before subscription invoice due date';

    public function handle(): int
    {
        $today = Carbon::today();

        // 7-day reminder: due_date = today + 7
        $dueIn7 = $today->copy()->addDays(7);
        $invoices7d = SubscriptionInvoice::whereDate('due_date', $dueIn7)
            ->whereIn('status', ['sent', 'overdue', 'draft'])
            ->whereNull('reminder_7d_sent_at')
            ->get();

        foreach ($invoices7d as $inv) {
            $this->sendReminder($inv, '7d');
            $inv->update(['reminder_7d_sent_at' => now()]);
        }

        // 24-hour reminder: due_date = today + 1
        $dueIn1 = $today->copy()->addDay();
        $invoices24h = SubscriptionInvoice::whereDate('due_date', $dueIn1)
            ->whereIn('status', ['sent', 'overdue', 'draft'])
            ->whereNull('reminder_24h_sent_at')
            ->get();

        foreach ($invoices24h as $inv) {
            $this->sendReminder($inv, '24h');
            $inv->update(['reminder_24h_sent_at' => now()]);
        }

        // Mark overdue: due_date < today and not paid
        SubscriptionInvoice::where('due_date', '<', $today)
            ->where('status', '!=', 'paid')
            ->update(['status' => 'overdue']);

        $total = $invoices7d->count() + $invoices24h->count();
        $this->info("Sent {$total} reminder(s) (7d: {$invoices7d->count()}, 24h: {$invoices24h->count()}).");
        return self::SUCCESS;
    }

    private function sendReminder(SubscriptionInvoice $invoice, string $type): void
    {
        $invoice->load('hotel');
        $hotel = $invoice->hotel;
        $email = $hotel->email ?? null;
        $days = $type === '7d' ? '7' : '1';
        $message = "Subscription invoice {$invoice->invoice_number} (due {$invoice->due_date->format('Y-m-d')}) is due in {$days} day(s). Amount: {$invoice->amount}.";
        Log::info("Subscription reminder [{$type}] for hotel {$hotel->name}: {$message}");
        // Optionally send email when mail is configured:
        // if ($email) { Mail::to($email)->send(new SubscriptionReminderMail($invoice, $type)); }
    }
}
