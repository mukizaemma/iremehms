<?php

namespace App\Livewire\FrontOffice;

use App\Models\Hotel;
use App\Services\ComplementaryReportService;
use App\Traits\ChecksModuleStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ComplementaryServicesReport extends Component
{
    use ChecksModuleStatus;

    public string $date_from = '';

    public string $date_to = '';

    public array $rows = [];

    public array $summary = [];

    public function mount(): void
    {
        session(['selected_module' => 'front-office']);
        $this->ensureModuleEnabled('front-office');

        $user = Auth::user();
        $allowed = $user && (
            $user->isSuperAdmin()
            || $user->canNavigateModules()
            || $user->hasPermission('fo_availability')
            || $user->hasPermission('fo_view_guest_bills')
            || $user->hasPermission('reports_view_all')
            || $user->isReceptionist()
        );
        if (! $allowed) {
            abort(403, 'You do not have permission to view complementary services reports.');
        }

        $hotel = Hotel::getHotel();
        $today = $hotel ? Carbon::now($hotel->getTimezone())->format('Y-m-d') : now()->format('Y-m-d');
        $this->date_from = $today;
        $this->date_to = $today;
        $this->loadReport();
    }

    public function updated($property): void
    {
        if (in_array($property, ['date_from', 'date_to'], true)) {
            $this->loadReport();
        }
    }

    public function loadReport(): void
    {
        $hotel = Hotel::getHotel();
        if (! $hotel) {
            return;
        }

        $from = $this->date_from;
        $to = $this->date_to;
        if ($from > $to) {
            [$from, $to] = [$to, $from];
            $this->date_from = $from;
            $this->date_to = $to;
        }

        $collection = ComplementaryReportService::buildRows($hotel->id, $from, $to);
        $this->rows = $collection->all();
        $this->summary = ComplementaryReportService::summarize($collection);
    }

    public function render()
    {
        $hotel = Hotel::getHotel();
        $currency = $hotel->currency ?? 'RWF';

        return view('livewire.front-office.complementary-services-report', [
            'hotel' => $hotel,
            'currency' => $currency,
        ])->layout('livewire.layouts.app-layout');
    }
}
