<?php

namespace App\Livewire\FrontOffice;

use App\Models\Hotel;
use App\Models\HotelRevenueReportLine;
use App\Services\HotelRevenueReportColumnService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class HotelRevenueReportSettings extends Component
{
    /**
     * @var array<int, array{id:int,bucket_key:string,label:string,sort_order:int,is_active:bool}>
     */
    public array $lines = [];

    public function mount(): void
    {
        session(['selected_module' => 'front-office']);

        $user = Auth::user();
        if (! $user) {
            abort(403);
        }

        $allowed = $user->isSuperAdmin()
            || $user->isManager()
            || $user->isEffectiveGeneralManager()
            || $user->hasPermission('hotel_manage_users')
            || $user->hasPermission('reports_view_all');

        if (! $allowed) {
            abort(403, 'You do not have permission to configure general report columns.');
        }

        $hotel = Hotel::getHotel();
        if (! $hotel) {
            abort(403, 'Hotel context required.');
        }

        HotelRevenueReportColumnService::ensureDefaultLines($hotel);
        $this->loadLines();
    }

    protected function loadLines(): void
    {
        $hotel = Hotel::getHotel();
        if (! $hotel) {
            $this->lines = [];

            return;
        }

        $hotel->unsetRelation('revenueReportLines');

        $this->lines = $hotel->revenueReportLines()
            ->orderBy('sort_order')
            ->get()
            ->map(function (HotelRevenueReportLine $l) {
                $isOther = $l->bucket_key === 'other';

                return [
                    'id' => $l->id,
                    'bucket_key' => $l->bucket_key,
                    'label' => $l->label,
                    'sort_order' => (int) $l->sort_order,
                    // Booleans: required "other" column is always on; avoid loose values from the DB driver.
                    'is_active' => $isOther ? true : (bool) $l->is_active,
                ];
            })
            ->values()
            ->all();
    }

    public function save(): void
    {
        $hotel = Hotel::getHotel();
        if (! $hotel) {
            return;
        }

        foreach ($this->lines as &$line) {
            if (($line['bucket_key'] ?? '') === 'other') {
                $line['is_active'] = true;
            }
        }
        unset($line);

        $activeCount = 0;
        foreach ($this->lines as $line) {
            if (! empty($line['is_active'])) {
                $activeCount++;
            }
        }
        if ($activeCount === 0) {
            session()->flash('error', 'Enable at least one revenue column.');

            return;
        }

        $this->validate([
            'lines.*.label' => 'required|string|max:120',
            'lines.*.sort_order' => 'required|integer|min:0|max:65000',
        ]);

        foreach ($this->lines as $line) {
            if (empty($line['id'])) {
                continue;
            }
            $isOther = ($line['bucket_key'] ?? '') === 'other';
            HotelRevenueReportLine::query()
                ->where('id', $line['id'])
                ->where('hotel_id', $hotel->id)
                ->update([
                    'label' => $line['label'] ?? '',
                    'sort_order' => (int) ($line['sort_order'] ?? 0),
                    'is_active' => $isOther ? true : (bool) ($line['is_active'] ?? false),
                ]);
        }

        session()->flash('message', 'General report columns saved.');
        $this->loadLines();
    }

    public function resetToDefaults(): void
    {
        $hotel = Hotel::getHotel();
        if (! $hotel) {
            return;
        }

        $hotel->revenueReportLines()->delete();
        $hotel->unsetRelation('revenueReportLines');
        HotelRevenueReportColumnService::ensureDefaultLines($hotel);
        $this->loadLines();
        session()->flash('message', 'Columns reset to defaults (all lines enabled).');
    }

    public function render()
    {
        return view('livewire.front-office.hotel-revenue-report-settings')
            ->layout('livewire.layouts.app-layout');
    }
}
