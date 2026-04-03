<?php

namespace App\Console\Commands;

use App\Models\Hotel;
use App\Services\SubscriptionInvoiceService;
use Illuminate\Console\Command;

class GenerateSubscriptionInvoices extends Command
{
    protected $signature = 'subscription:generate-invoices';

    protected $description = 'Generate subscription invoices when next due date is within 30 days';

    public function handle(): int
    {
        $hotels = Hotel::where('subscription_status', 'active')
            ->whereNotNull('next_due_date')
            ->whereNotNull('subscription_amount')
            ->where('subscription_amount', '>', 0)
            ->get();

        $count = 0;
        foreach ($hotels as $hotel) {
            $invoice = SubscriptionInvoiceService::generateForHotel($hotel);
            if ($invoice) {
                $count++;
                $this->info("Created invoice {$invoice->invoice_number} for hotel {$hotel->name} (due {$invoice->due_date->format('Y-m-d')}).");
            }
        }

        $this->info("Generated {$count} subscription invoice(s).");
        return self::SUCCESS;
    }
}
