<?php

namespace App\Livewire\Pos;

use App\Helpers\ReportDatePreset;
use App\Models\OrderItem;
use App\Models\PreparationStation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class PosStationReport extends Component
{
    use WithPagination;

    public string $station = '';
    public string $stationName = '';
    public string $datePreset = ReportDatePreset::TODAY;
    public ?string $dateFrom = null;
    public ?string $dateTo = null;

    public string $verified_by_name = '';
    public string $approved_by_name = '';

    protected $queryString = ['station' => ['except' => ''], 'datePreset' => ['except' => 'today'], 'dateFrom', 'dateTo'];

    public function mount(string $station = null)
    {
        $user = Auth::user();
        if (! $user || (! $user->hasPermission('pos_view_station_orders') && ! $user->hasPermission('pos_mark_station_ready'))) {
            abort(403, 'You do not have access to view station reports.');
        }

        $stations = PreparationStation::getActiveForPos();
        if ($station && array_key_exists($station, $stations)) {
            $this->station = $station;
            $this->stationName = $stations[$station];
        } elseif ($station) {
            // Requested station is inactive or missing — use first active
            $first = array_key_first($stations);
            $this->station = $first ?: '';
            $this->stationName = $first ? $stations[$first] : '—';
        } else {
            $first = array_key_first($stations);
            $this->station = $first ?: '';
            $this->stationName = $first ? $stations[$first] : '—';
        }
        [$this->dateFrom, $this->dateTo] = ReportDatePreset::apply($this->datePreset, $this->dateFrom, $this->dateTo);
    }

    public function applyPreset()
    {
        [$this->dateFrom, $this->dateTo] = ReportDatePreset::apply($this->datePreset, $this->dateFrom, $this->dateTo);
        $this->resetPage();
    }

    public function getRows(): LengthAwarePaginator
    {
        [$from, $to] = ReportDatePreset::apply($this->datePreset, $this->dateFrom, $this->dateTo);
        $fromStart = $from . ' 00:00:00';
        $toEnd = $to . ' 23:59:59';

        $query = OrderItem::query()
            ->with(['order.table', 'order.waiter', 'menuItem'])
            ->whereHas('menuItem', fn ($q) => $q->where('preparation_station', $this->station))
            ->whereHas('order', fn ($q) => $q->whereBetween('created_at', [$fromStart, $toEnd]));

        return $query->orderByDesc('order_items.created_at')->paginate(20, ['order_items.*'], 'page');
    }

    public function getTotalAmount(): float
    {
        [$from, $to] = ReportDatePreset::apply($this->datePreset, $this->dateFrom, $this->dateTo);
        $fromStart = $from . ' 00:00:00';
        $toEnd = $to . ' 23:59:59';

        return (float) OrderItem::query()
            ->whereHas('menuItem', fn ($q) => $q->where('preparation_station', $this->station))
            ->whereHas('order', fn ($q) => $q->whereBetween('created_at', [$fromStart, $toEnd]))
            ->sum('line_total');
    }

    public function getShareUrl(): string
    {
        return route('pos.station-report', ['station' => $this->station]) . '?' . http_build_query([
            'datePreset' => $this->datePreset,
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
        ]);
    }

    public function getShareText(): string
    {
        [$from, $to] = ReportDatePreset::apply($this->datePreset, $this->dateFrom, $this->dateTo);
        $total = $this->getTotalAmount();
        return sprintf(
            "%s Report %s to %s. Total amount: %s. View: %s",
            $this->stationName,
            $from,
            $to,
            \App\Helpers\CurrencyHelper::format($total),
            $this->getShareUrl()
        );
    }

    public function updatedDatePreset()
    {
        $this->applyPreset();
    }

    public function updatedStation()
    {
        $stations = PreparationStation::getActiveForPos();
        $this->stationName = $stations[$this->station] ?? $this->station;
        $this->resetPage();
    }

    public function render()
    {
        $rows = $this->getRows();
        $totalAmount = $this->getTotalAmount();
        $dateRange = ReportDatePreset::apply($this->datePreset, $this->dateFrom, $this->dateTo);

        return view('livewire.pos.pos-station-report', [
            'rows' => $rows,
            'totalAmount' => $totalAmount,
            'datePresetOptions' => ReportDatePreset::options(),
            'dateRange' => $dateRange,
            'shareText' => $this->getShareText(),
            'preparationStations' => PreparationStation::getActiveForPos(),
        ])->layout('livewire.layouts.app-layout');
    }
}
