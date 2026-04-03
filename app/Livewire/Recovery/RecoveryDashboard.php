<?php

namespace App\Livewire\Recovery;

use App\Models\Invoice;
use App\Models\Module;
use App\Traits\ChecksModuleStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class RecoveryDashboard extends Component
{
    use ChecksModuleStatus;

    public string $tab = 'unpaid'; // unpaid | credits | room_charges
    public string $search = '';

    public function mount()
    {
        $module = Module::where('slug', 'recovery')->first();
        if ($module) {
            $this->ensureModuleEnabled($module->slug);
            if (! Auth::user()->hasModuleAccess($module->id)) {
                abort(403, 'You do not have access to the Recovery module.');
            }
        }
    }

    public function getUnpaidInvoicesProperty()
    {
        return Invoice::query()
            ->where('invoice_status', 'UNPAID')
            ->with(['order.waiter', 'order.table', 'postedBy', 'reservation'])
            ->when($this->search, function ($q) {
                $q->where(function ($q2) {
                    $q2->where('invoice_number', 'like', '%' . $this->search . '%')
                        ->orWhereHas('order', fn ($o) => $o->whereHas('waiter', fn ($w) => $w->where('name', 'like', '%' . $this->search . '%')));
                });
            })
            ->orderByDesc('id')
            ->limit(200)
            ->get();
    }

    public function getCreditInvoicesProperty()
    {
        return Invoice::query()
            ->where('invoice_status', 'CREDIT')
            ->with(['order.waiter', 'order.table', 'postedBy', 'reservation'])
            ->when($this->search, function ($q) {
                $q->where('invoice_number', 'like', '%' . $this->search . '%');
            })
            ->orderByDesc('id')
            ->limit(200)
            ->get();
    }

    public function getRoomChargesProperty()
    {
        return Invoice::query()
            ->where('charge_type', 'room')
            ->whereIn('invoice_status', ['UNPAID', 'CREDIT'])
            ->with(['order.waiter', 'postedBy', 'reservation', 'room'])
            ->when($this->search, function ($q) {
                $q->where(function ($q2) {
                    $q2->where('invoice_number', 'like', '%' . $this->search . '%')
                        ->orWhereHas('reservation', fn ($r) => $r->where('guest_name', 'like', '%' . $this->search . '%')->orWhere('reservation_number', 'like', '%' . $this->search . '%'));
                });
            })
            ->orderByDesc('id')
            ->limit(200)
            ->get();
    }

    public function getHotelCoveredInvoicesProperty()
    {
        return Invoice::query()
            ->where('charge_type', 'hotel_covered')
            ->with(['order.waiter', 'postedBy'])
            ->when($this->search, function ($q) {
                $q->where(function ($q2) {
                    $q2->where('invoice_number', 'like', '%' . $this->search . '%')
                        ->orWhere('hotel_covered_names', 'like', '%' . $this->search . '%')
                        ->orWhere('hotel_covered_reason', 'like', '%' . $this->search . '%');
                });
            })
            ->orderByDesc('assigned_at')
            ->limit(200)
            ->get();
    }

    public function render()
    {
        $unpaid = $this->unpaidInvoices;
        $credits = $this->creditInvoices;
        $roomCharges = $this->roomCharges;
        $hotelCoveredInvoices = $this->hotelCoveredInvoices;

        return view('livewire.recovery.recovery-dashboard', [
            'unpaidInvoices' => $unpaid,
            'creditInvoices' => $credits,
            'roomChargeInvoices' => $roomCharges,
            'hotelCoveredInvoices' => $hotelCoveredInvoices,
        ])->layout('livewire.layouts.app-layout');
    }
}
