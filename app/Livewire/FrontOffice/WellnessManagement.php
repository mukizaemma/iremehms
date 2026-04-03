<?php

namespace App\Livewire\FrontOffice;

use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\WellnessPayment;
use App\Models\WellnessService;
use App\Services\HotelRevenueReportColumnService;
use App\Support\GeneralReportPosBuckets;
use App\Traits\ChecksModuleStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class WellnessManagement extends Component
{
    use ChecksModuleStatus;
    use WithPagination;

    public string $tab = 'services';

    /** @var 'per_visit'|'daily'|'subscription' */
    public string $svc_billing_type = 'per_visit';

    public string $svc_name = '';

    public string $svc_code = '';

    public string $svc_description = '';

    public string $svc_default_price = '0';

    public string $svc_price_per_day = '';

    public string $svc_price_monthly = '';

    public ?int $svc_duration = null;

    public string $svc_report_bucket = 'other';

    public bool $svc_is_active = true;

    public ?int $editingServiceId = null;

    public string $wp_amount = '';

    public string $wp_received_at = '';

    public ?int $wp_service_id = null;

    public string $wp_kind = 'visit';

    public string $wp_destination = 'direct_payment';

    public ?int $wp_reservation_id = null;

    public string $wp_reservation_search = '';

    public ?string $wp_period_start = null;

    public ?string $wp_period_end = null;

    public string $wp_guest_name = '';

    public string $wp_notes = '';

    protected $queryString = ['tab' => ['except' => 'services']];

    public function updatedTab(): void
    {
        $this->resetPage();
    }

    public function mount(): void
    {
        session(['selected_module' => 'front-office']);
        $this->ensureModuleEnabled('front-office');
        $u = Auth::user();
        if (! $u || ! $u->hasPermission('fo_wellness_manage')) {
            abort(403, 'You do not have permission to manage wellness services.');
        }
        $hotel = Hotel::getHotel();
        if (! $hotel) {
            abort(403, 'Hotel context required.');
        }
        $this->wp_received_at = now($hotel->getTimezone())->format('Y-m-d\TH:i');
    }

    public function startNewService(): void
    {
        $this->editingServiceId = null;
        $this->svc_billing_type = 'per_visit';
        $this->svc_name = '';
        $this->svc_code = '';
        $this->svc_description = '';
        $this->svc_default_price = '0';
        $this->svc_price_per_day = '';
        $this->svc_price_monthly = '';
        $this->svc_duration = null;
        $this->svc_report_bucket = 'massage';
        $this->svc_is_active = true;
    }

    public function editService(int $id): void
    {
        $hotel = Hotel::getHotel();
        $s = WellnessService::query()->where('hotel_id', $hotel->id)->whereKey($id)->firstOrFail();
        $this->editingServiceId = $s->id;
        $this->svc_billing_type = $s->billing_type;
        $this->svc_name = $s->name;
        $this->svc_code = (string) ($s->code ?? '');
        $this->svc_description = (string) ($s->description ?? '');
        $this->svc_default_price = (string) $s->default_price;
        $this->svc_price_per_day = $s->price_per_day !== null ? (string) $s->price_per_day : '';
        $this->svc_price_monthly = $s->price_monthly_subscription !== null ? (string) $s->price_monthly_subscription : '';
        $this->svc_duration = $s->duration_minutes;
        $this->svc_report_bucket = $s->report_bucket_key;
        $this->svc_is_active = $s->is_active;
    }

    public function saveService(): void
    {
        $hotel = Hotel::getHotel();
        if (! $hotel) {
            return;
        }

        $this->validate([
            'svc_name' => 'required|string|max:190',
            'svc_code' => 'nullable|string|max:32',
            'svc_billing_type' => 'required|in:per_visit,daily,subscription',
            'svc_default_price' => 'required|numeric|min:0',
            'svc_price_per_day' => 'nullable|numeric|min:0',
            'svc_price_monthly' => 'nullable|numeric|min:0',
            'svc_duration' => 'nullable|integer|min:1|max:1440',
            'svc_report_bucket' => 'required|string|max:40',
            'svc_description' => 'nullable|string|max:2000',
        ]);

        $data = [
            'name' => $this->svc_name,
            'code' => $this->svc_code ?: null,
            'description' => $this->svc_description ?: null,
            'billing_type' => $this->svc_billing_type,
            'default_price' => (float) $this->svc_default_price,
            'price_per_day' => $this->svc_price_per_day !== '' ? (float) $this->svc_price_per_day : null,
            'price_monthly_subscription' => $this->svc_price_monthly !== '' ? (float) $this->svc_price_monthly : null,
            'duration_minutes' => $this->svc_duration,
            'report_bucket_key' => $this->svc_report_bucket,
            'is_active' => $this->svc_is_active,
        ];

        if ($this->editingServiceId) {
            WellnessService::query()->where('hotel_id', $hotel->id)->whereKey($this->editingServiceId)->update($data);
            session()->flash('message', 'Service updated.');
        } else {
            $data['hotel_id'] = $hotel->id;
            $data['sort_order'] = (int) (WellnessService::query()->where('hotel_id', $hotel->id)->max('sort_order') ?? 0) + 1;
            WellnessService::create($data);
            session()->flash('message', 'Service created.');
        }

        $this->startNewService();
    }

    public function deleteService(int $id): void
    {
        $hotel = Hotel::getHotel();
        WellnessService::query()->where('hotel_id', $hotel->id)->whereKey($id)->delete();
        session()->flash('message', 'Service removed.');
        $this->startNewService();
    }

    public function savePayment(): void
    {
        $hotel = Hotel::getHotel();
        if (! $hotel) {
            return;
        }

        $this->validate([
            'wp_service_id' => 'required|integer',
            'wp_amount' => 'required|numeric|min:0.01',
            'wp_received_at' => 'required|date',
            'wp_kind' => 'required|in:visit,daily,subscription',
            'wp_destination' => 'required|in:direct_payment,room_folio',
            'wp_reservation_id' => 'nullable|integer',
            'wp_period_start' => 'nullable|date',
            'wp_period_end' => 'nullable|date',
            'wp_guest_name' => 'nullable|string|max:190',
            'wp_notes' => 'nullable|string|max:500',
        ]);

        $svc = WellnessService::query()->where('hotel_id', $hotel->id)->whereKey($this->wp_service_id)->first();
        if (! $svc) {
            $this->addError('wp_service_id', 'Invalid service.');

            return;
        }

        $reservationId = null;
        if ($this->wp_destination === 'room_folio') {
            if (! $this->wp_reservation_id) {
                $this->addError('wp_reservation_id', 'Select a room guest reservation.');

                return;
            }
            $reservation = Reservation::query()
                ->where('hotel_id', $hotel->id)
                ->whereKey($this->wp_reservation_id)
                ->first();
            if (! $reservation) {
                $this->addError('wp_reservation_id', 'Invalid reservation for this hotel.');

                return;
            }
            $reservation->total_amount = (float) ($reservation->total_amount ?? 0) + (float) $this->wp_amount;
            $reservation->save();
            $reservationId = (int) $reservation->id;
        }

        WellnessPayment::create([
            'hotel_id' => $hotel->id,
            'reservation_id' => $reservationId,
            'wellness_service_id' => $svc->id,
            'service_name_snapshot' => $svc->name,
            'billing_type_snapshot' => $svc->billing_type,
            'payment_kind' => $this->wp_kind,
            'destination' => $this->wp_destination,
            'amount' => (float) $this->wp_amount,
            'received_at' => \Carbon\Carbon::parse($this->wp_received_at),
            'period_start' => $this->wp_period_start,
            'period_end' => $this->wp_period_end,
            'guest_name' => $this->wp_guest_name ?: null,
            'report_bucket_key' => (string) ($svc->report_bucket_key ?: 'other'),
            'notes' => $this->wp_notes ?: null,
            'recorded_by' => Auth::id(),
        ]);

        $this->wp_amount = '';
        $this->wp_notes = '';
        $this->wp_guest_name = '';
        $this->wp_reservation_id = null;
        $this->wp_reservation_search = '';
        session()->flash('message', $this->wp_destination === 'room_folio'
            ? 'Wellness charge posted to room folio and included in general report.'
            : 'Payment recorded. It appears on the general report for the received date.');
    }

    public function render()
    {
        $hotel = Hotel::getHotel();
        $services = WellnessService::query()->where('hotel_id', $hotel->id)->orderBy('sort_order')->orderBy('name')->get();
        $payments = WellnessPayment::query()->where('hotel_id', $hotel->id)->orderByDesc('received_at')->paginate(20);
        $roomReservationsQuery = Reservation::query()
            ->where('hotel_id', $hotel->id)
            ->whereIn('status', ['confirmed', 'checked_in']);

        $search = trim($this->wp_reservation_search);
        if ($search !== '') {
            $like = '%'.$search.'%';
            $roomReservationsQuery->where(function ($q) use ($like) {
                $q->where('reservation_number', 'like', $like)
                    ->orWhere('guest_name', 'like', $like)
                    ->orWhere('guest_phone', 'like', $like)
                    ->orWhereHas('roomUnits.room', function ($qr) use ($like) {
                        $qr->where('room_number', 'like', $like)
                            ->orWhere('name', 'like', $like);
                    })
                    ->orWhereHas('roomUnits', function ($qu) use ($like) {
                        $qu->where('label', 'like', $like);
                    });
            });
        }

        $roomReservations = $roomReservationsQuery
            ->with(['roomUnits.room'])
            ->orderByDesc('check_in_date')
            ->limit(120)
            ->get();

        $defs = HotelRevenueReportColumnService::defaultDefinitions();
        $reportBucketOptions = [];
        foreach (GeneralReportPosBuckets::COLUMN_KEYS as $k) {
            $reportBucketOptions[$k] = $defs[$k] ?? $k;
        }

        return view('livewire.front-office.wellness-management', [
            'services' => $services,
            'payments' => $payments,
            'reportBucketOptions' => $reportBucketOptions,
            'roomReservations' => $roomReservations,
            'paymentDestinations' => [
                'direct_payment' => 'Direct payment (wellness desk)',
                'room_folio' => 'Post to room guest folio',
            ],
            'paymentKindLabels' => [
                'visit' => 'Single visit',
                'daily' => 'Daily pass',
                'subscription' => 'Subscription / package',
            ],
        ])->layout('livewire.layouts.app-layout');
    }
}
