<?php

namespace App\Services;

use App\Models\Hotel;
use App\Models\ProformaInvoice;

final class ProformaInvoiceNumberService
{
    public static function next(Hotel $hotel): string
    {
        $year = now($hotel->getTimezone())->year;
        $prefix = 'PF-'.$year.'-';

        $last = ProformaInvoice::query()
            ->where('hotel_id', $hotel->id)
            ->where('proforma_number', 'like', $prefix.'%')
            ->orderByDesc('proforma_number')
            ->value('proforma_number');

        $n = 1;
        if ($last && preg_match('/-(\d+)$/', (string) $last, $m)) {
            $n = (int) $m[1] + 1;
        }

        return $prefix.str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }
}
