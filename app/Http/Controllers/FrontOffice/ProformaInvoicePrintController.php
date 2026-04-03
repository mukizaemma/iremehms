<?php

namespace App\Http\Controllers\FrontOffice;

use App\Models\Hotel;
use App\Models\ProformaInvoice;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProformaInvoicePrintController
{
    public function show(Request $request, ProformaInvoice $proformaInvoice): View
    {
        $hotel = Hotel::getHotel();
        if (! $hotel || $proformaInvoice->hotel_id !== $hotel->id) {
            abort(403);
        }

        $proformaInvoice->load(['lines', 'hotel']);

        return view('prints.proforma-invoice', [
            'proforma' => $proformaInvoice,
        ]);
    }
}
