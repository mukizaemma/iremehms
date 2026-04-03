<?php

namespace App\Livewire\FrontOffice;

use App\Models\Hotel;
use App\Models\ProformaInvoice;
use App\Support\ProformaInvoicePermissions;
use App\Traits\ChecksModuleStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class ProformaInvoicesIndex extends Component
{
    use ChecksModuleStatus;
    use WithPagination;

    public string $statusFilter = '';

    protected $queryString = [
        'statusFilter' => ['except' => ''],
    ];

    public function mount(): void
    {
        session(['selected_module' => 'front-office']);
        $this->ensureModuleEnabled('front-office');
        $this->authorizeFo();
    }

    protected function authorizeFo(): void
    {
        $u = Auth::user();
        if (! $u || ! $u->hasPermission('fo_proforma_manage')) {
            abort(403, 'You do not have permission to manage proforma invoices.');
        }
        if (! Hotel::getHotel()) {
            abort(403, 'Hotel context required.');
        }
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $hotel = Hotel::getHotel();
        $q = ProformaInvoice::query()->where('hotel_id', $hotel->id)->orderByDesc('created_at');
        if ($this->statusFilter !== '') {
            $q->where('status', $this->statusFilter);
        }

        $u = Auth::user();
        $canVerify = $u ? ProformaInvoicePermissions::canVerifyProforma($u) : false;
        $pendingApprovalCount = $canVerify
            ? ProformaInvoice::query()->where('hotel_id', $hotel->id)->where('status', 'pending_manager')->count()
            : 0;
        $newApprovalAlerts = $canVerify
            ? $u->unreadNotifications()->where('data->type', 'proforma_approval_request')->count()
            : 0;

        return view('livewire.front-office.proforma-invoices-index', [
            'proformas' => $q->paginate(15),
            'canVerify' => $canVerify,
            'pendingApprovalCount' => $pendingApprovalCount,
            'newApprovalAlerts' => $newApprovalAlerts,
        ])->layout('livewire.layouts.app-layout');
    }
}
