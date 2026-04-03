<?php

namespace App\Livewire\FrontOffice;

use App\Models\Hotel;
use App\Models\HotelProformaLineDefault;
use App\Services\HotelRevenueReportColumnService;
use App\Support\GeneralReportPosBuckets;
use App\Support\ProformaCatalog;
use App\Traits\ChecksModuleStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ProformaLineDefaults extends Component
{
    use ChecksModuleStatus;

    /** @var array<string, string> line_type => price string */
    public array $prices = [];

    /** @var array<string, string> line_type => report bucket */
    public array $buckets = [];

    public function mount(): void
    {
        session(['selected_module' => 'front-office']);
        $this->ensureModuleEnabled('front-office');
        $u = Auth::user();
        if (! $u || ! $u->hasPermission('fo_proforma_manage')) {
            abort(403);
        }
        $hotel = Hotel::getHotel();
        if (! $hotel) {
            abort(403);
        }

        $bucketDefaults = ProformaCatalog::defaultBucketForLineType();
        foreach (array_keys(ProformaCatalog::lineTypes()) as $type) {
            $this->prices[$type] = '0';
            $this->buckets[$type] = $bucketDefaults[$type] ?? 'other';
        }

        $rows = HotelProformaLineDefault::query()->where('hotel_id', $hotel->id)->get();
        foreach ($rows as $row) {
            if (isset($this->prices[$row->line_type])) {
                $this->prices[$row->line_type] = (string) $row->default_unit_price;
                $this->buckets[$row->line_type] = (string) ($row->report_bucket_key ?? ($this->buckets[$row->line_type] ?? 'other'));
            }
        }
    }

    public function save(): void
    {
        $hotel = Hotel::getHotel();
        if (! $hotel) {
            return;
        }

        $this->validate([
            'prices' => 'array',
            'prices.*' => 'nullable|numeric|min:0',
            'buckets' => 'array',
            'buckets.*' => 'required|string|max:40|in:'.implode(',', GeneralReportPosBuckets::COLUMN_KEYS),
        ]);

        foreach ($this->prices as $lineType => $priceStr) {
            $price = (float) $priceStr;
            HotelProformaLineDefault::query()->updateOrCreate(
                ['hotel_id' => $hotel->id, 'line_type' => $lineType],
                [
                    'default_unit_price' => $price,
                    'report_bucket_key' => (string) ($this->buckets[$lineType] ?? 'other'),
                ]
            );
        }

        session()->flash('message', 'Default unit prices and report columns saved. New lines will use these defaults automatically.');
    }

    public function render()
    {
        $defs = HotelRevenueReportColumnService::defaultDefinitions();
        $reportBucketOptions = [];
        foreach (GeneralReportPosBuckets::COLUMN_KEYS as $k) {
            $reportBucketOptions[$k] = $defs[$k] ?? $k;
        }

        return view('livewire.front-office.proforma-line-defaults', [
            'lineTypes' => ProformaCatalog::lineTypes(),
            'reportBucketOptions' => $reportBucketOptions,
        ])->layout('livewire.layouts.app-layout');
    }
}
