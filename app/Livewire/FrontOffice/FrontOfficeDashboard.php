<?php

namespace App\Livewire\FrontOffice;

use App\Models\Hotel;
use App\Models\PreRegistration;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Traits\ChecksModuleStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Front Office Dashboard – module summary for receptionist.
 * Shows rooms, vacant, occupied, reserved, due in, due out, dirty, open; click a box to see details.
 */
class FrontOfficeDashboard extends Component
{
    use ChecksModuleStatus;

    public $totalRooms = 0;
    public $totalUnits = 0;
    public $vacant = 0;
    public $occupied = 0;
    public $reserved = 0;
    public $dueOut = 0;
    public $dueIn = 0;
    public $dirty = 0;
    /** Same as vacant – rooms available to sell */
    public $open = 0;
    /** No-show reservations (guest did not arrive) for follow-up */
    public $noShow = 0;

    /** Which summary box is expanded: null | vacant | occupied | reserved | due_out | due_in | dirty | open | no_show */
    public ?string $detailFilter = null;
    /** List of items for the detail panel: [ ['room_label' => ..., 'guest_name' => ..., 'check_in' => ..., 'check_out' => ...], ... ] */
    public array $detailItems = [];

    // Clients table (reservations + pre-arrivals)
    public string $clientSearch = '';
    /** @var array<int, array<string, mixed>> */
    public array $clients = [];

    public function mount()
    {
        session(['selected_module' => 'front-office']);
        $this->ensureModuleEnabled('front-office');
        $module = \App\Models\Module::where('slug', 'front-office')->first();
        if ($module && ! Auth::user()->hasModuleAccess($module->id)) {
            abort(403, 'You do not have access to Front Office.');
        }
        $this->loadSummary();
        $this->loadClients();
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
        $unitIdsInRange = [];
        $unitIdsNoShow = [];

        foreach ($reservations as $r) {
            $from = $r->check_in_date->format('Y-m-d');
            $to = $r->check_out_date->format('Y-m-d');
            $inRange = $from <= $endRange && $to >= $today;
            $occupiesToday = $from <= $today && $to >= $today && $r->status !== Reservation::STATUS_NO_SHOW;
            $departsToday = $to === $today && $r->status !== Reservation::STATUS_NO_SHOW;
            $arrivesToday = $from === $today;
            $isNoShow = $r->status === Reservation::STATUS_NO_SHOW;

            foreach ($r->roomUnits as $unit) {
                $uid = (string) $unit->id;
                if ($inRange) {
                    $unitIdsInRange[$uid] = true;
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
                if ($arrivesToday) {
                    $unitIdsDueIn[$uid] = true;
                }
            }
        }

        $occupiedCount = count($unitIdsOccupied);
        $dueOutCount = count($unitIdsDueOut);
        $dueInCount = count($unitIdsDueIn);
        $reservedCount = count($unitIdsInRange);
        $noShowCount = count($unitIdsNoShow);
        $vacantCount = max(0, $this->totalUnits - $reservedCount);

        $this->occupied = $occupiedCount;
        $this->dueOut = $dueOutCount;
        $this->dueIn = $dueInCount;
        $this->reserved = $reservedCount;
        $this->noShow = $noShowCount;
        $this->vacant = $vacantCount;
        $this->open = $vacantCount;
        $this->dirty = 0; // No housekeeping status in DB yet
    }

    public function updatedClientSearch(): void
    {
        $this->loadClients();
    }

    protected function loadClients(): void
    {
        $hotel = Hotel::getHotel();

        if (! $hotel) {
            $this->clients = [];
            return;
        }

        $q = trim($this->clientSearch);
        $qLower = strtolower($q);

        // Reservations-based clients
        $reservationQuery = Reservation::where('hotel_id', $hotel->id)
            ->whereNotIn('status', [Reservation::STATUS_CANCELLED])
            ->select([
                'guest_name',
                'guest_phone',
                'guest_email',
                'guest_country',
                'guest_address',
                DB::raw('COUNT(*) as stay_count'),
                DB::raw('MAX(check_in_date) as last_check_in'),
            ])
            ->groupBy('guest_name', 'guest_phone', 'guest_email', 'guest_country', 'guest_address');

        if ($q !== '') {
            $reservationQuery->where(function ($w) use ($qLower, $q) {
                $w->whereRaw('LOWER(guest_name) LIKE ?', ['%' . $qLower . '%'])
                    ->orWhereRaw('LOWER(guest_phone) LIKE ?', ['%' . $qLower . '%'])
                    ->orWhereRaw('LOWER(guest_email) LIKE ?', ['%' . $qLower . '%']);
            });
        }

        $reservationGroups = $reservationQuery
            ->orderByDesc('last_check_in')
            ->limit(100)
            ->get();

        // Pre-arrival clients (not yet confirmed in reservations)
        $preQuery = PreRegistration::where('hotel_id', $hotel->id)
            ->select([
                'guest_name',
                'guest_phone',
                'guest_email',
                'organization',
                DB::raw('COUNT(*) as pre_count'),
                DB::raw('MAX(submitted_at) as last_pre'),
            ])
            ->groupBy('guest_name', 'guest_phone', 'guest_email', 'organization');

        if ($q !== '') {
            $preQuery->where(function ($w) use ($qLower, $q) {
                $w->whereRaw('LOWER(guest_name) LIKE ?', ['%' . $qLower . '%'])
                    ->orWhereRaw('LOWER(guest_phone) LIKE ?', ['%' . $qLower . '%'])
                    ->orWhereRaw('LOWER(guest_email) LIKE ?', ['%' . $qLower . '%'])
                    ->orWhereRaw('LOWER(organization) LIKE ?', ['%' . $qLower . '%']);
            });
        }

        $preGroups = $preQuery
            ->orderByDesc('last_pre')
            ->limit(100)
            ->get();

        $makeKey = function (?string $name, ?string $phone, ?string $email): string {
            $phone = $phone ? preg_replace('/\\s+/', '', strtolower($phone)) : null;
            $email = $email ? strtolower(trim($email)) : null;
            $name = $name ? strtolower(trim($name)) : '';

            if ($phone) {
                return 'p:' . $phone;
            }
            if ($email) {
                return 'e:' . $email;
            }

            return 'n:' . $name;
        };

        $byKey = [];

        foreach ($reservationGroups as $g) {
            $key = $makeKey($g->guest_name, $g->guest_phone, $g->guest_email);
            $byKey[$key] = [
                'name' => $g->guest_name,
                'phone' => $g->guest_phone,
                'email' => $g->guest_email,
                'country' => $g->guest_country,
                'address' => $g->guest_address,
                'stay_count' => (int) $g->stay_count,
                'pre_count' => 0,
                'last_activity' => $g->last_check_in ? Carbon::parse($g->last_check_in)->format('Y-m-d') : null,
            ];
        }

        foreach ($preGroups as $g) {
            $key = $makeKey($g->guest_name, $g->guest_phone, $g->guest_email);
            if (! isset($byKey[$key])) {
                $byKey[$key] = [
                    'name' => $g->guest_name,
                    'phone' => $g->guest_phone,
                    'email' => $g->guest_email,
                    'country' => null,
                    'address' => null,
                    'stay_count' => 0,
                    'pre_count' => (int) $g->pre_count,
                    'last_activity' => $g->last_pre ? Carbon::parse($g->last_pre)->format('Y-m-d') : null,
                ];
            } else {
                $byKey[$key]['pre_count'] = (int) $g->pre_count;
                if ($g->last_pre) {
                    $preDate = Carbon::parse($g->last_pre);
                    $currentLast = $byKey[$key]['last_activity'] ? Carbon::parse($byKey[$key]['last_activity']) : null;
                    if (! $currentLast || $preDate->gt($currentLast)) {
                        $byKey[$key]['last_activity'] = $preDate->format('Y-m-d');
                    }
                }
            }
        }

        $this->clients = collect($byKey)
            ->sortByDesc(function ($c) {
                return $c['last_activity'] ?? '0000-00-00';
            })
            ->values()
            ->take(200)
            ->toArray();
    }

    /** Show detail panel for the given filter. */
    public function showDetail(string $filter): void
    {
        $this->detailFilter = $filter;
        $this->loadDetailItems();
    }

    public function clearDetail(): void
    {
        $this->detailFilter = null;
        $this->detailItems = [];
    }

    protected function loadDetailItems(): void
    {
        $hotel = Hotel::getHotel();
        $today = Hotel::getTodayForHotel();
        $items = [];
        $roomIds = Room::where('hotel_id', $hotel->id)->where('is_active', true)->pluck('id');

        switch ($this->detailFilter) {
            case 'vacant':
            case 'open':
                $occupiedUnitIds = Reservation::where('hotel_id', $hotel->id)
                    ->whereNotIn('status', [Reservation::STATUS_CANCELLED, Reservation::STATUS_NO_SHOW, Reservation::STATUS_CHECKED_OUT])
                    ->where('check_in_date', '<=', $today)
                    ->where('check_out_date', '>=', $today)
                    ->with('roomUnits')
                    ->get()
                    ->pluck('roomUnits')
                    ->flatten()
                    ->pluck('id')
                    ->flip()
                    ->all();
                $units = RoomUnit::whereIn('room_id', $roomIds)->where('is_active', true)->with('room')->get();
                foreach ($units as $unit) {
                    if (! isset($occupiedUnitIds[$unit->id])) {
                        $items[] = [
                            'room_label' => $unit->label,
                            'room_name' => $unit->room->name ?? '',
                            'guest_name' => null,
                            'check_in' => null,
                            'check_out' => null,
                            'reservation_number' => null,
                        ];
                    }
                }
                break;
            case 'occupied':
                $reservations = Reservation::where('hotel_id', $hotel->id)
                    ->whereNotIn('status', [Reservation::STATUS_CANCELLED, Reservation::STATUS_NO_SHOW, Reservation::STATUS_CHECKED_OUT])
                    ->where('check_in_date', '<=', $today)
                    ->where('check_out_date', '>=', $today)
                    ->with(['roomUnits.room'])
                    ->orderBy('check_in_date')
                    ->get();
                foreach ($reservations as $r) {
                    foreach ($r->roomUnits as $unit) {
                        $items[] = [
                            'room_label' => $unit->label,
                            'room_name' => $unit->room->name ?? '',
                            'guest_name' => $r->guest_name,
                            'check_in' => $r->check_in_date->format('Y-m-d'),
                            'check_out' => $r->check_out_date->format('Y-m-d'),
                            'reservation_number' => $r->reservation_number,
                        ];
                    }
                }
                break;
            case 'due_out':
                $reservations = Reservation::where('hotel_id', Hotel::getHotel()->id)
                    ->where('status', '!=', Reservation::STATUS_CANCELLED)
                    ->where('check_out_date', $today)
                    ->with(['roomUnits.room'])
                    ->get();
                foreach ($reservations as $r) {
                    foreach ($r->roomUnits as $unit) {
                        $items[] = [
                            'room_label' => $unit->label,
                            'room_name' => $unit->room->name ?? '',
                            'guest_name' => $r->guest_name,
                            'check_in' => $r->check_in_date->format('Y-m-d'),
                            'check_out' => $r->check_out_date->format('Y-m-d'),
                            'reservation_number' => $r->reservation_number,
                        ];
                    }
                }
                break;
            case 'due_in':
                $reservations = Reservation::where('hotel_id', Hotel::getHotel()->id)
                    ->where('status', '!=', Reservation::STATUS_CANCELLED)
                    ->where('check_in_date', $today)
                    ->with(['roomUnits.room'])
                    ->get();
                foreach ($reservations as $r) {
                    foreach ($r->roomUnits as $unit) {
                        $items[] = [
                            'room_label' => $unit->label,
                            'room_name' => $unit->room->name ?? '',
                            'guest_name' => $r->guest_name,
                            'check_in' => $r->check_in_date->format('Y-m-d'),
                            'check_out' => $r->check_out_date->format('Y-m-d'),
                            'reservation_number' => $r->reservation_number,
                        ];
                    }
                }
                break;
            case 'reserved':
                $reservations = Reservation::where('hotel_id', Hotel::getHotel()->id)
                    ->where('status', '!=', Reservation::STATUS_CANCELLED)
                    ->where('check_out_date', '>=', $today)
                    ->where('check_in_date', '<=', Carbon::now($hotel->getTimezone())->addDays(30)->format('Y-m-d'))
                    ->with(['roomUnits.room'])
                    ->orderBy('check_in_date')
                    ->get();
                foreach ($reservations as $r) {
                    foreach ($r->roomUnits as $unit) {
                        $items[] = [
                            'room_label' => $unit->label,
                            'room_name' => $unit->room->name ?? '',
                            'guest_name' => $r->guest_name,
                            'check_in' => $r->check_in_date->format('Y-m-d'),
                            'check_out' => $r->check_out_date->format('Y-m-d'),
                            'reservation_number' => $r->reservation_number,
                        ];
                    }
                }
                break;
            case 'no_show':
                $endRange = Carbon::now($hotel->getTimezone())->addDays(30)->format('Y-m-d');
                $reservations = Reservation::where('hotel_id', $hotel->id)
                    ->where('status', Reservation::STATUS_NO_SHOW)
                    ->where('check_out_date', '>=', $today)
                    ->where('check_in_date', '<=', $endRange)
                    ->with(['roomUnits.room'])
                    ->orderBy('check_in_date')
                    ->get();
                foreach ($reservations as $r) {
                    foreach ($r->roomUnits as $unit) {
                        $items[] = [
                            'room_label' => $unit->label,
                            'room_name' => $unit->room->name ?? '',
                            'guest_name' => $r->guest_name,
                            'check_in' => $r->check_in_date->format('Y-m-d'),
                            'check_out' => $r->check_out_date->format('Y-m-d'),
                            'reservation_number' => $r->reservation_number,
                        ];
                    }
                }
                break;
            case 'dirty':
                $items = [];
                break;
            default:
                $items = [];
        }

        $this->detailItems = $items;
    }

    public function render()
    {
        return view('livewire.front-office.front-office-dashboard')->layout('livewire.layouts.app-layout');
    }
}
