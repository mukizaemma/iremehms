<?php

namespace App\Livewire\FrontOffice;

use App\Models\Hotel;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

/**
 * All reservations page: tabs (All, Arrivals, Departures, In-house, No show),
 * grid/list view, autosearch by name, phone, email, room number.
 */
class ReservationsList extends Component
{
    public string $tab = 'all';
    public string $viewMode = 'grid';
    public string $search = '';

    public function mount(): void
    {
        session(['selected_module' => 'front-office']);
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
    }

    public function setViewMode(string $mode): void
    {
        $this->viewMode = $mode;
    }

    protected function getToday(): string
    {
        return Hotel::getTodayForHotel();
    }

    /** Base query: hotel (status filtering handled per-tab). */
    protected function baseQuery(): Builder
    {
        $hotel = Hotel::getHotel();
        return Reservation::where('hotel_id', $hotel->id)
            ->with(['roomType', 'roomUnits.room']);
    }

    /** Apply tab filter to query. */
    protected function applyTabFilter(Builder $query): Builder
    {
        $today = $this->getToday();
        switch ($this->tab) {
            case 'arrivals':
                return $query->where('check_in_date', $today)
                    ->where('status', '!=', Reservation::STATUS_NO_SHOW);
            case 'departures':
                return $query->where('check_out_date', $today);
            case 'in_house':
                return $query->where('status', Reservation::STATUS_CHECKED_IN);
            case 'no_show':
                return $query->where('status', Reservation::STATUS_NO_SHOW);
            case 'cancelled':
                return $query->where('status', Reservation::STATUS_CANCELLED);
            default:
                // All: future and current (check_out >= today), excluding cancelled
                return $query->where('check_out_date', '>=', $today)
                    ->where('status', '!=', Reservation::STATUS_CANCELLED);
        }
    }

    /** Apply search filter: guest_name, guest_phone, guest_email, or room number (unit label or room.room_number). */
    protected function applySearch(Builder $query): Builder
    {
        $q = trim($this->search);
        if ($q === '') {
            return $query;
        }
        $like = '%' . $q . '%';
        return $query->where(function (Builder $b) use ($like, $q) {
            $b->where('guest_name', 'like', $like)
                ->orWhere('guest_phone', 'like', $like)
                ->orWhere('guest_email', 'like', $like)
                ->orWhere('reservation_number', 'like', $like)
                ->orWhereHas('roomUnits', function (Builder $u) use ($like, $q) {
                    $u->where('label', 'like', $like)
                        ->orWhereHas('room', function (Builder $r) use ($like) {
                            $r->where('room_number', 'like', $like)->orWhere('name', 'like', $like);
                        });
                });
        });
    }

    /** Count per tab (for badge). */
    public function getCounts(): array
    {
        $today = $this->getToday();
        $hotel = Hotel::getHotel();
        $base = Reservation::where('hotel_id', $hotel->id);

        return [
            'all' => (clone $base)->where('check_out_date', '>=', $today)->where('status', '!=', Reservation::STATUS_CANCELLED)->count(),
            'arrivals' => (clone $base)->where('check_in_date', $today)->whereNotIn('status', [Reservation::STATUS_NO_SHOW, Reservation::STATUS_CANCELLED])->count(),
            'departures' => (clone $base)->where('check_out_date', $today)->count(),
            'in_house' => (clone $base)->where('status', Reservation::STATUS_CHECKED_IN)->count(),
            'no_show' => (clone $base)->where('status', Reservation::STATUS_NO_SHOW)->count(),
            'cancelled' => (clone $base)->where('status', Reservation::STATUS_CANCELLED)->count(),
        ];
    }

    /** Get reservations for current tab + search, as flat rows (one per reservation–room unit pair for grid). */
    public function getReservations(): \Illuminate\Support\Collection
    {
        $query = $this->baseQuery();
        $query = $this->applyTabFilter($query);
        $query = $this->applySearch($query);
        $query->orderBy('check_in_date')->orderBy('id');

        $reservations = $query->get();
        $today = $this->getToday();
        $currency = Hotel::getHotel()->currency ?? 'RWF';

        $rows = [];
        foreach ($reservations as $r) {
            $checkInDt = $r->check_in_date->format('d/m/Y') . ($r->check_in_time ? ' ' . Carbon::parse($r->check_in_time)->format('H:i:s') : '');
            $checkOutDt = $r->check_out_date->format('d/m/Y') . ($r->check_out_time ? ' ' . Carbon::parse($r->check_out_time)->format('H:i:s') : '');
            $nights = max(0, $r->check_in_date->diffInDays($r->check_out_date));
            $bookingDate = $r->created_at ? $r->created_at->format('d/m/Y') : '—';
            $total = (float) ($r->total_amount ?? 0);
            $paid = (float) ($r->paid_amount ?? 0);
            $balance = $total - $paid;

            $units = $r->roomUnits;
            $isDueToday = $r->check_out_date->format('Y-m-d') === $today;
            $isArrivalToday = $r->check_in_date->format('Y-m-d') === $today;
            $isInHouse = $r->status === Reservation::STATUS_CHECKED_IN;
            $canCheckout = $isInHouse && $isDueToday;
            $canAddPayment = $balance > 0;
            $canAddExtras = $isInHouse;
            $statusLabel = match ($r->status) {
                Reservation::STATUS_CHECKED_IN => $isDueToday ? 'In-house · Departs today' : 'In-house',
                Reservation::STATUS_CHECKED_OUT => 'Checked-out',
                Reservation::STATUS_CANCELLED => 'Cancelled',
                Reservation::STATUS_NO_SHOW => 'No-show',
                default => $isArrivalToday ? 'Arrival today' : 'Booked / Reserved',
            };
            $statusBadge = match ($r->status) {
                Reservation::STATUS_CHECKED_IN => 'primary',
                Reservation::STATUS_CHECKED_OUT => 'secondary',
                Reservation::STATUS_CANCELLED => 'secondary',
                Reservation::STATUS_NO_SHOW => 'danger',
                default => ($isArrivalToday ? 'info' : 'success'),
            };
            if ($units->isEmpty()) {
                $rows[] = [
                    'reservation' => $r,
                    'reservation_number' => $r->reservation_number,
                    'room_label' => '—',
                    'room_number' => '—',
                    'guest_name' => $r->guest_name,
                    'check_in' => $checkInDt,
                    'check_out' => $checkOutDt,
                    'nights' => $nights,
                    'booking_date' => $bookingDate,
                    'adult_count' => $r->adult_count ?? 0,
                    'child_count' => $r->child_count ?? 0,
                    'rate_plan' => $r->rate_plan ?? '—',
                    'room_type' => $r->roomType?->name ?? '—',
                    'total' => $total,
                    'paid' => $paid,
                    'balance' => $balance,
                    'currency' => $currency,
                    'status' => $r->status,
                    'status_label' => $statusLabel,
                    'status_badge' => $statusBadge,
                    'can_checkout' => $canCheckout,
                    'can_add_payment' => $canAddPayment,
                    'can_add_extras' => $canAddExtras,
                    'is_arrival_today' => $isArrivalToday,
                ];
            } else {
                foreach ($units as $unit) {
                    $room = $unit->room;
                    $roomNumber = $room ? ($room->room_number ?? $unit->label) : $unit->label;
                    $rows[] = [
                        'reservation' => $r,
                        'reservation_number' => $r->reservation_number,
                        'room_label' => $unit->label,
                        'room_number' => $roomNumber,
                        'guest_name' => $r->guest_name,
                        'check_in' => $checkInDt,
                        'check_out' => $checkOutDt,
                        'nights' => $nights,
                        'booking_date' => $bookingDate,
                        'adult_count' => $r->adult_count ?? 0,
                        'child_count' => $r->child_count ?? 0,
                        'rate_plan' => $r->rate_plan ?? '—',
                        'room_type' => $r->roomType?->name ?? '—',
                        'total' => $total,
                        'paid' => $paid,
                        'balance' => $balance,
                        'currency' => $currency,
                        'status' => $r->status,
                        'status_label' => $statusLabel,
                        'status_badge' => $statusBadge,
                        'can_checkout' => $canCheckout,
                        'can_add_payment' => $canAddPayment,
                        'can_add_extras' => $canAddExtras,
                        'is_arrival_today' => $isArrivalToday,
                    ];
                }
            }
        }

        return collect($rows);
    }

    public function render()
    {
        $counts = $this->getCounts();
        $reservations = $this->getReservations();
        $currency = Hotel::getHotel()->currency ?? 'RWF';

        return view('livewire.front-office.reservations-list', [
            'counts' => $counts,
            'reservations' => $reservations,
            'currency' => $currency,
        ])->layout('livewire.layouts.app-layout');
    }
}
