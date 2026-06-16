<?php

namespace App\Livewire\FrontOffice;

use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Traits\ChecksModuleStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Front Office Dashboard – module summary for receptionist.
 * Shows rooms, vacant, occupied, due in, due out, dirty; click a box to see details.
 */
class FrontOfficeDashboard extends Component
{
    use ChecksModuleStatus;

    public $totalRooms = 0;
    public $totalUnits = 0;
    public $vacant = 0;
    public $occupied = 0;
    public $dueOut = 0;
    public $dueIn = 0;
    public $dirty = 0;
    /** No-show reservations (guest did not arrive) for follow-up */
    public $noShow = 0;

    /** Filter the rooms table: null | all | vacant | occupied | due_out | due_in | dirty | no_show */
    public ?string $tableFilter = null;

    /** @var array<int, array<string, mixed>> */
    public array $roomRows = [];

    public function mount()
    {
        session(['selected_module' => 'front-office']);
        $this->ensureModuleEnabled('front-office');
        $module = \App\Models\Module::where('slug', 'front-office')->first();
        if ($module && ! Auth::user()->hasModuleAccess($module->id)) {
            abort(403, 'You do not have access to Front Office.');
        }
        $this->loadSummary();
        $this->loadRoomRows();
    }

    public function refreshDashboard(): void
    {
        $this->loadSummary();
        $this->loadRoomRows();
    }

    public function toggleTableFilter(string $filter): void
    {
        $this->tableFilter = $this->tableFilter === $filter ? null : $filter;
    }

    public function clearTableFilter(): void
    {
        $this->tableFilter = null;
    }

    protected function loadSummary(): void
    {
        $hotel = Hotel::getHotel();
        $roomIds = Room::where('hotel_id', $hotel->id)->where('is_active', true)->pluck('id');
        $this->totalRooms = $roomIds->count();
        $units = RoomUnit::whereIn('room_id', $roomIds)->where('is_active', true)->with('room')->get();
        $this->totalUnits = $units->count();

        $today = Hotel::getTodayForHotel();
        $endRange = Carbon::now($hotel->getTimezone())->addDays(30)->format('Y-m-d');

        $reservations = Reservation::where('hotel_id', $hotel->id)
            ->whereNotIn('status', [Reservation::STATUS_CANCELLED, Reservation::STATUS_CHECKED_OUT])
            ->where('check_out_date', '>=', $today)
            ->where('check_in_date', '<=', $endRange)
            ->with(['roomUnits.room'])
            ->get();

        $unitIdsOccupied = [];
        $unitIdsDueOut = [];
        $unitIdsDueIn = [];
        $unitIdsBookedInRange = [];
        $unitIdsNoShow = [];

        foreach ($reservations as $r) {
            $from = $r->check_in_date->format('Y-m-d');
            $to = $r->check_out_date->format('Y-m-d');
            $inRange = $from <= $endRange && $to >= $today;
            $isNoShow = $r->status === Reservation::STATUS_NO_SHOW;
            $isCheckedIn = $r->status === Reservation::STATUS_CHECKED_IN;
            $isConfirmed = $r->status === Reservation::STATUS_CONFIRMED;
            $occupiesToday = $isCheckedIn && $from <= $today && $today < $to;
            $departsToday = $isCheckedIn && $to === $today;
            $arrivesTodayDueIn = $isConfirmed && $from === $today;

            foreach ($r->roomUnits as $unit) {
                $uid = (string) $unit->id;
                if ($inRange) {
                    $unitIdsBookedInRange[$uid] = true;
                }
                if ($isNoShow && $inRange) {
                    $unitIdsNoShow[$uid] = true;
                }
                if ($occupiesToday) {
                    $unitIdsOccupied[$uid] = true;
                }
                if ($departsToday) {
                    $unitIdsDueOut[$uid] = true;
                }
                if ($arrivesTodayDueIn) {
                    $unitIdsDueIn[$uid] = true;
                }
            }
        }

        $occupiedCount = count($unitIdsOccupied);
        $dueOutCount = count($unitIdsDueOut);
        $dueInCount = count($unitIdsDueIn);
        $noShowCount = count($unitIdsNoShow);
        $vacantCount = max(0, $this->totalUnits - count($unitIdsBookedInRange));

        $this->occupied = $occupiedCount;
        $this->dueOut = $dueOutCount;
        $this->dueIn = $dueInCount;
        $this->noShow = $noShowCount;
        $this->vacant = $vacantCount;
        $this->dirty = 0; // No housekeeping status in DB yet
    }

    protected function loadRoomRows(): void
    {
        $hotel = Hotel::getHotel();
        if (! $hotel) {
            $this->roomRows = [];

            return;
        }

        $today = Hotel::getTodayForHotel();
        $endRange = Carbon::now($hotel->getTimezone())->addDays(30)->format('Y-m-d');

        $roomIds = Room::where('hotel_id', $hotel->id)->where('is_active', true)->pluck('id');
        $units = RoomUnit::whereIn('room_id', $roomIds)
            ->where('is_active', true)
            ->with(['room.roomType'])
            ->orderBy('sort_order')
            ->get();

        $reservations = Reservation::where('hotel_id', $hotel->id)
            ->whereNotIn('status', [Reservation::STATUS_CANCELLED, Reservation::STATUS_CHECKED_OUT])
            ->where('check_out_date', '>=', $today)
            ->where('check_in_date', '<=', $endRange)
            ->with(['roomUnits'])
            ->orderBy('check_in_date')
            ->get();

        $reservationsByUnit = [];
        foreach ($reservations as $reservation) {
            foreach ($reservation->roomUnits as $unit) {
                $uid = (int) $unit->id;
                if (! isset($reservationsByUnit[$uid])) {
                    $reservationsByUnit[$uid] = [];
                }
                $reservationsByUnit[$uid][] = $reservation;
            }
        }

        $rows = [];
        foreach ($units as $unit) {
            $reservation = $this->pickReservationForUnit($reservationsByUnit[(int) $unit->id] ?? [], $today);
            $status = $this->resolveUnitStatus($reservation, $today);
            $room = $unit->room;

            $rows[] = [
                'unit_id' => $unit->id,
                'room_number' => $unit->label ?: ($room->room_number ?? (string) $unit->id),
                'room_id' => $room->id ?? null,
                'room_type' => strtoupper($room->roomType->name ?? $room->name ?? '—'),
                'status' => $status['label'],
                'status_key' => $status['key'],
                'sort_rank' => $status['sort_rank'],
                'guest_name' => $reservation?->guest_name,
                'company' => $reservation
                    ? ($reservation->group_name ?: $reservation->business_source_detail ?: $reservation->business_source)
                    : null,
                'booking_source' => $reservation?->booking_source,
                'check_in' => $reservation?->check_in_date?->format('d/m/Y'),
                'check_out' => $reservation?->check_out_date?->format('d/m/Y'),
                'reservation_id' => $reservation?->id,
                'reservation_number' => $reservation?->reservation_number,
            ];
        }

        usort($rows, function (array $a, array $b): int {
            if ($a['sort_rank'] !== $b['sort_rank']) {
                return $a['sort_rank'] <=> $b['sort_rank'];
            }

            return strnatcasecmp((string) $a['room_number'], (string) $b['room_number']);
        });

        $this->roomRows = $rows;
    }

    /**
     * @param  array<int, Reservation>  $candidates
     */
    protected function pickReservationForUnit(array $candidates, string $today): ?Reservation
    {
        if ($candidates === []) {
            return null;
        }

        $todayCarbon = Carbon::parse($today);

        foreach ($candidates as $reservation) {
            if ($reservation->status === Reservation::STATUS_CHECKED_IN
                && $reservation->check_in_date->lte($todayCarbon)
                && $reservation->check_out_date->gt($todayCarbon)) {
                return $reservation;
            }
        }

        foreach ($candidates as $reservation) {
            if ($reservation->status === Reservation::STATUS_CONFIRMED
                && $reservation->check_in_date->format('Y-m-d') === $today) {
                return $reservation;
            }
        }

        foreach ($candidates as $reservation) {
            if ($reservation->status === Reservation::STATUS_NO_SHOW) {
                continue;
            }
            if ($reservation->check_out_date->gte($todayCarbon)) {
                return $reservation;
            }
        }

        return $candidates[0];
    }

    /**
     * @return array{key: string, label: string, sort_rank: int}
     */
    protected function resolveUnitStatus(?Reservation $reservation, string $today): array
    {
        if (! $reservation) {
            return ['key' => 'vacant', 'label' => 'Vacant', 'sort_rank' => 60];
        }

        $from = $reservation->check_in_date->format('Y-m-d');
        $to = $reservation->check_out_date->format('Y-m-d');

        if ($reservation->status === Reservation::STATUS_NO_SHOW) {
            return ['key' => 'no_show', 'label' => 'No show', 'sort_rank' => 50];
        }

        if ($reservation->status === Reservation::STATUS_CHECKED_IN) {
            if ($to === $today) {
                return ['key' => 'due_out', 'label' => 'Due out', 'sort_rank' => 10];
            }
            if ($from <= $today && $today < $to) {
                return ['key' => 'occupied', 'label' => 'Occupied', 'sort_rank' => 0];
            }
        }

        if ($reservation->status === Reservation::STATUS_CONFIRMED) {
            if ($from === $today) {
                return ['key' => 'due_in', 'label' => 'Due in', 'sort_rank' => 20];
            }
            if ($from > $today) {
                return ['key' => 'reserved', 'label' => 'Reserved', 'sort_rank' => 30];
            }
        }

        return ['key' => 'reserved', 'label' => 'Reserved', 'sort_rank' => 30];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getDisplayedRoomRowsProperty(): array
    {
        if (! $this->tableFilter || $this->tableFilter === 'all') {
            return $this->roomRows;
        }

        return array_values(array_filter(
            $this->roomRows,
            fn (array $row) => $row['status_key'] === $this->tableFilter
        ));
    }

    public function render()
    {
        return view('livewire.front-office.front-office-dashboard')->layout('livewire.layouts.app-layout');
    }
}
