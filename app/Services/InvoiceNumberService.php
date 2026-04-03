<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

class InvoiceNumberService
{
    /**
     * Generate next invoice number (e.g. INV-20260211-0001)
     */
    public static function generate(): string
    {
        $prefix = 'INV-' . date('Ymd') . '-';
        $last = Invoice::where('invoice_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('invoice_number');

        $seq = 1;
        if ($last) {
            $seq = (int) substr($last, strlen($prefix)) + 1;
        }

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
