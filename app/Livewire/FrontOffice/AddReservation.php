<?php

namespace App\Livewire\FrontOffice;

use App\Helpers\VatHelper;
use App\Services\OperationalShiftActionGate;
use App\Models\Hotel;
use App\Models\PreRegistration;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\RoomUnit;
use App\Models\ReservationPayment;
use App\Models\SupportRequest;
use App\Support\PaymentCatalog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Add Reservation – full reservation form with rate types, guest auto-search,
 * other confirmations, payment summary, and reservation number generation.
 * Persists to reservations and reservation_room_unit.
 */
class AddReservation extends Component
{
    // Booking details (dates in Y-m-d for date picker)
    public $check_in_date = '';
    public $check_in_time = '13:00';
    public $check_out_date = '';
    public $check_out_time = '11:00';
    public $rooms_count = 1;
    /** Single booking (default) or group booking */
    public $is_group_booking = false;
    /** When group: primary guest or group name */
    public $group_guest_name = '';
    /** When group: rows of [room_type_id, quantity (rooms), adults, children] */
    public $group_room_rows = [];
    public $reservation_type = 'Confirm Booking';
    public $booking_source = 'Direct';
    public $business_source = '';
    /** When Business Source = OTA: selected OTA */
    public $selected_ota = '';
    /** When Business Source = Social media: which page/account */
    public $social_media_page = '';
    /** When Business Source = Referral: person and phone */
    public $referral_name = '';
    public $referral_phone = '';

    // Room row(s) – room_type_id and room_unit_id for persistence
    public $room_type_id = '';
    public $room_type = '';
    public $rate_type = '';
    public $room_unit_id = '';
    public $room = '';
    public $adult = 1;
    public $child = 0;
    public $rate_rs = '';
    public $rate_tax_inc = false;
    /** Booked ranges for currently selected room/unit (for user guidance). */
    public array $room_unit_booked_ranges = [];
    /** Overlapping reservation details when selected dates collide with selected unit. */
    public array $overlap_reservations = [];
    /** Show overlap popup/modal when selected dates collide with existing bookings. */
    public bool $show_overlap_modal = false;

    // Guest (for auto-fill)
    public $guest_salutation = 'Mr.';
    public $guest_name = '';
    public $guest_mobile = '';
    public $guest_email = '';
    public $guest_address = '';
    public $guest_country = 'Rwanda';
    public $guest_state = '';
    public $guest_city = '';
    public $guest_zip = '';
    public $guest_id_number = '';
    public $guest_profession = '';
    public $guest_stay_purpose = '';
    public $guest_search_open = false;
    public $guest_suggestions = [];
    /** Set true after user confirms the past client is added to the form */
    public $guest_confirmed = false;
    /** Company / group name and type (Tour Operator, etc.) */
    public $guest_company_name = '';
    public $guest_company_type = '';

    /** Guest search mode: false = new guest, true = existing client lookup. */
    public bool $use_existing_client = false;

    /** Summary stats for the selected existing guest. */
    public ?int $existing_guest_stay_count = null;
    public ?int $existing_guest_referral_count = null;

    /** When true, guest has requested an extra bed instead of an additional room. */
    public $extra_bed = false;

    // Other confirmations
    public $email_booking_vouchers = false;
    public $send_email_at_checkout = false;
    public $access_to_guest_portal = true;

    // Billing summary
    public $bill_to = 'Guest';
    public $tax_exempt = false;

    // UI helpers for occupancy & availability
    public ?string $occupancy_warning = null;
    public ?int $suggested_group_rooms = null;
    public ?string $missing_occupancy_room_type = null;
    public ?string $rooms_availability_warning = null;

    // Payment
    public $payment_mode_enabled = true;
    public $payment_unified = 'Cash';
    public $payment_client_reference = '';
    /** Amount paid now (local currency) when not using international currency */
    public $payment_amount = '';
    public $use_international_currency = false;
    public $foreign_currency = 'USD';
    public $exchange_rate = '';
    public $amount_in_foreign = '';
    public $amount_in_local = '';

    // After reserve
    public $reservation_number = null;
    public $reserve_success = false;
    public $pre_registration_id = null;

    public const RESERVATION_TYPES = [
        'Confirm Booking',
        'Unconfirmed Booking Inquiry',
        'Online Failed Booking',
        'Hold Confirm Booking',
        'Hold Unconfirm Booking',
    ];

    public const RATE_TYPES = [
        'Locals',
        'EAC',
        'Residents',
        'International',
        'Continental Plan (CP)',
        'American Plan (AP)',
        'European Plan',
    ];

    public const FOREIGN_CURRENCIES = ['USD', 'EUR', 'GBP'];

    // Business source: how this guest came to us
    public const BUSINESS_SOURCE_OPTIONS = [
        'Walk-in',
        'Phone',
        'Website',
        'Social media',
        'Referral',
        'OTA',
    ];

    public const KNOWN_OTAS = ['Booking.com', 'Expedia', 'Agoda', 'TripAdvisor', 'Airbnb', 'Other OTA'];

    public const COMPANY_TYPES = ['Tour Operator', 'Local Travel Agent', 'Corporate Company', 'Family'];

    /** Full list of countries for searchable select (ISO 3166-1 names). */
    public static function getAllCountries(): array
    {
        return [
            'Afghanistan', 'Albania', 'Algeria', 'Andorra', 'Angola', 'Antigua and Barbuda', 'Argentina', 'Armenia', 'Australia', 'Austria', 'Azerbaijan',
            'Bahamas', 'Bahrain', 'Bangladesh', 'Barbados', 'Belarus', 'Belgium', 'Belize', 'Benin', 'Bhutan', 'Bolivia', 'Bosnia and Herzegovina', 'Botswana', 'Brazil', 'Brunei', 'Bulgaria', 'Burkina Faso', 'Burundi',
            'Cabo Verde', 'Cambodia', 'Cameroon', 'Canada', 'Central African Republic', 'Chad', 'Chile', 'China', 'Colombia', 'Comoros', 'Congo', 'Costa Rica', 'Croatia', 'Cuba', 'Cyprus', 'Czech Republic',
            'Denmark', 'Djibouti', 'Dominica', 'Dominican Republic',
            'Ecuador', 'Egypt', 'El Salvador', 'Equatorial Guinea', 'Eritrea', 'Estonia', 'Eswatini', 'Ethiopia',
            'Fiji', 'Finland', 'France',
            'Gabon', 'Gambia', 'Georgia', 'Germany', 'Ghana', 'Greece', 'Grenada', 'Guatemala', 'Guinea', 'Guinea-Bissau', 'Guyana',
            'Haiti', 'Honduras', 'Hungary',
            'Iceland', 'India', 'Indonesia', 'Iran', 'Iraq', 'Ireland', 'Israel', 'Italy', 'Ivory Coast',
            'Jamaica', 'Japan', 'Jordan',
            'Kazakhstan', 'Kenya', 'Kiribati', 'Kuwait', 'Kyrgyzstan',
            'Laos', 'Latvia', 'Lebanon', 'Lesotho', 'Liberia', 'Libya', 'Liechtenstein', 'Lithuania', 'Luxembourg',
            'Madagascar', 'Malawi', 'Malaysia', 'Maldives', 'Mali', 'Malta', 'Marshall Islands', 'Mauritania', 'Mauritius', 'Mexico', 'Micronesia', 'Moldova', 'Monaco', 'Mongolia', 'Montenegro', 'Morocco', 'Mozambique', 'Myanmar',
            'Namibia', 'Nauru', 'Nepal', 'Netherlands', 'New Zealand', 'Nicaragua', 'Niger', 'Nigeria', 'North Korea', 'North Macedonia', 'Norway',
            'Oman',
            'Pakistan', 'Palau', 'Palestine', 'Panama', 'Papua New Guinea', 'Paraguay', 'Peru', 'Philippines', 'Poland', 'Portugal', 'Qatar',
            'Romania', 'Russia', 'Rwanda',
            'Saint Kitts and Nevis', 'Saint Lucia', 'Saint Vincent and the Grenadines', 'Samoa', 'San Marino', 'Sao Tome and Principe', 'Saudi Arabia', 'Senegal', 'Serbia', 'Seychelles', 'Sierra Leone', 'Singapore', 'Slovakia', 'Slovenia', 'Solomon Islands', 'Somalia', 'South Africa', 'South Korea', 'South Sudan', 'Spain', 'Sri Lanka', 'Sudan', 'Suriname', 'Sweden', 'Switzerland', 'Syria',
            'Taiwan', 'Tajikistan', 'Tanzania', 'Thailand', 'Timor-Leste', 'Togo', 'Tonga', 'Trinidad and Tobago', 'Tunisia', 'Turkey', 'Turkmenistan', 'Tuvalu',
            'Uganda', 'Ukraine', 'United Arab Emirates', 'United Kingdom', 'United States', 'Uruguay', 'Uzbekistan',
            'Vanuatu', 'Vatican City', 'Venezuela', 'Vietnam',
            'Yemen',
            'Zambia', 'Zimbabwe',
        ];
    }

    public const COUNTRIES = ['Rwanda', 'Uganda', 'Kenya', 'Tanzania', 'Burundi', 'India', 'USA', 'UK', 'France', 'Germany', 'Other'];

    /** Mock guests for auto-search (replace with Guest::search() when model exists) */
    protected static function mockGuests(): array
    {
        return [
            ['id' => 1, 'name' => 'Mr. Bob Baker', 'mobile' => '999999999', 'email' => 'bob@example.com', 'address' => 'PO BOX 53', 'country' => 'India', 'state' => 'Pune', 'city' => 'Surat', 'zip' => '395553'],
            ['id' => 2, 'name' => 'Mr. A', 'mobile' => '987654321', 'email' => 'mr.a@gmail.com', 'address' => '', 'country' => 'India', 'state' => '', 'city' => '', 'zip' => ''],
            ['id' => 3, 'name' => 'Ms. Jane Doe', 'mobile' => '788123456', 'email' => 'jane@example.com', 'address' => 'Street 1', 'country' => 'Rwanda', 'state' => 'Kigali', 'city' => 'Kigali', 'zip' => ''],
        ];
    }

    public function mount()
    {
        session(['selected_module' => 'front-office']);
        $today = Carbon::now();
        $this->check_in_date = $today->format('Y-m-d');
        $this->check_out_date = $today->copy()->addDays(2)->format('Y-m-d');
        if ($this->rate_type === '') {
            $this->rate_type = 'Locals';
        }
        if ($this->rate_tax_inc === false) {
            $this->rate_tax_inc = true;
        }
        if ($this->rate_tax_inc === false) {
            $this->rate_tax_inc = true;
        }
        // Allow pre-arrival assignment to pre-fill dates from query (?check_in=Y-m-d&check_out=Y-m-d)
        if (request()->filled('check_in')) {
            $this->check_in_date = (string) request()->get('check_in');
        }
        if (request()->filled('check_out')) {
            $this->check_out_date = (string) request()->get('check_out');
        }

        if (request()->filled('room_unit_id')) {
            $unit = RoomUnit::with('room')->find(request()->get('room_unit_id'));
            if ($unit && $unit->room) {
                $this->room_type_id = (string) $unit->room->room_type_id;
                $this->room_unit_id = (string) $unit->id;
                $this->applyConfiguredRate();
            }
        }
        if (request()->filled('pre_registration')) {
            $pre = PreRegistration::find((int) request()->get('pre_registration'));
            if ($pre) {
                $this->pre_registration_id = $pre->id;
                $this->guest_name = $pre->guest_name ?? '';
                $this->guest_mobile = $pre->guest_phone ?? '';
                $this->guest_email = $pre->guest_email ?? '';
                if ($pre->guest_country) {
                    $this->guest_country = $pre->guest_country;
                }
                $this->guest_id_number = $pre->guest_id_number ?? '';
                $this->guest_profession = $pre->guest_profession ?? '';
                $this->guest_stay_purpose = $pre->guest_stay_purpose ?? '';
                if (! $this->room_unit_id && $pre->room_unit_id) {
                    $unit = RoomUnit::with('room')->find($pre->room_unit_id);
                    if ($unit && $unit->room) {
                        $this->room_type_id = (string) $unit->room->room_type_id;
                        $this->room_unit_id = (string) $unit->id;
                        $this->applyConfiguredRate();
                    }
                }
            }
        }
        $this->computeNights();
        // Prevent keeping a room/unit selected when the default or prefilled dates are already booked.
        $this->clearRoomUnitIfUnavailable();
        $this->refreshSelectedUnitBookingInfo();
    }

    public function updatedCheckInDate()
    {
        $this->computeNights();
        $this->clearRoomUnitIfUnavailable();
        $this->checkRoomsAvailability();
        $this->checkOccupancyLimits();
        $this->refreshSelectedUnitBookingInfo();
    }

    public function updatedCheckOutDate()
    {
        $this->computeNights();
        $this->clearRoomUnitIfUnavailable();
        $this->checkRoomsAvailability();
        $this->checkOccupancyLimits();
        $this->refreshSelectedUnitBookingInfo();
    }

    public function updatedAdult(): void
    {
        $this->checkOccupancyLimits();
    }

    public function updatedChild(): void
    {
        $this->checkOccupancyLimits();
    }

    public function updatedRoomsCount(): void
    {
        $this->checkOccupancyLimits();
        $this->checkRoomsAvailability();
    }

    /** Clear room type/unit if no longer available for the selected dates (avoid overbooking). */
    protected function clearRoomUnitIfUnavailable(): void
    {
        if (! $this->check_in_date || ! $this->check_out_date || $this->check_in_date >= $this->check_out_date) {
            return;
        }
        $booked = $this->getBookedUnitIdsForPeriod($this->check_in_date, $this->check_out_date);
        if ($this->room_unit_id && in_array((int) $this->room_unit_id, $booked, true)) {
            $this->room_unit_id = '';
        }
        if ($this->room_type_id) {
            $roomIds = Room::where('hotel_id', Hotel::getHotel()->id)->where('room_type_id', $this->room_type_id)->where('is_active', true)->pluck('id');
            $unitIds = RoomUnit::whereIn('room_id', $roomIds)->where('is_active', true)->pluck('id')->all();
            $available = array_diff($unitIds, $booked);
            if (count($available) === 0) {
                $this->room_type_id = '';
                $this->room_unit_id = '';
            }
        }
    }

    public function updatedRoomTypeId(): void
    {
        $this->room_unit_id = '';
        $this->applyConfiguredRate();
        $this->checkOccupancyLimits();
        $this->checkRoomsAvailability();
    }

    /**
     * Validate that adults/children do not exceed the allowed occupancy
     * for the selected room type and number of rooms. When exceeded,
     * helper properties are set so the UI can guide the user.
     */
    protected function checkOccupancyLimits(): void
    {
        if (! $this->room_type_id) {
            return;
        }

        $roomType = RoomType::find($this->room_type_id);
        if (! $roomType) {
            return;
        }

        $rooms = max(1, (int) $this->rooms_count);

        $rawMaxAdults = (int) ($roomType->max_adults ?? 0);
        $rawMaxChildren = (int) ($roomType->max_children ?? 0);

        // If occupancy is not configured, fall back to defaults and record warning so
        // receptionist/manager can request proper setup.
        if ($rawMaxAdults <= 0 && $rawMaxChildren <= 0) {
            $this->missing_occupancy_room_type = $roomType->name;
            $maxAdultsPerRoom = 2;
            $maxChildrenPerRoom = 1;
        } else {
            $this->missing_occupancy_room_type = null;
            $maxAdultsPerRoom = max(1, $rawMaxAdults);
            $maxChildrenPerRoom = max(0, $rawMaxChildren);
        }

        $allowedAdults = $maxAdultsPerRoom * $rooms;
        $allowedChildren = $maxChildrenPerRoom * $rooms;

        $exceededAdults = $this->adult > $allowedAdults;
        $exceededChildren = $this->child > $allowedChildren;

        if (! $exceededAdults && ! $exceededChildren) {
            $this->occupancy_warning = null;
            $this->suggested_group_rooms = null;
            return;
        }

        $requiredByAdults = $maxAdultsPerRoom > 0 && $this->adult > 0
            ? (int) ceil($this->adult / $maxAdultsPerRoom)
            : 0;
        $requiredByChildren = $maxChildrenPerRoom > 0 && $this->child > 0
            ? (int) ceil($this->child / $maxChildrenPerRoom)
            : 0;

        $requiredRooms = max(1, $requiredByAdults, $requiredByChildren);
        $this->suggested_group_rooms = $requiredRooms;

        // If we're already in group booking mode, keep group rows in sync with the
        // newly required room count (so rows are removed when capacity allows).
        if ($this->is_group_booking) {
            $this->adjustGroupRowsToRequiredRooms($requiredRooms);
        }

        $this->occupancy_warning = sprintf(
            'This selection exceeds the capacity of %s (max %d adults and %d children per room). You likely need around %d room(s) in this category.',
            $roomType->name,
            $maxAdultsPerRoom,
            $maxChildrenPerRoom,
            $requiredRooms
        );
    }

    /**
     * When in group booking mode, ensure the sum of "No. of rooms" across rows
     * does not exceed the required room count. If the user decreases adults/
     * children so fewer rooms are needed, extra rows/quantities are reduced.
     */
    protected function adjustGroupRowsToRequiredRooms(int $requiredRooms): void
    {
        $requiredRooms = max(1, $requiredRooms);

        // Compute total rooms represented by current rows.
        $totalRooms = 0;
        foreach ($this->group_room_rows as $row) {
            $totalRooms += max(1, (int) ($row['quantity'] ?? 1));
        }

        // If we already match the required count, nothing to do.
        if ($totalRooms === $requiredRooms) {
            $this->rooms_count = $requiredRooms;
            return;
        }

        // If we have more rooms than required, trim from the end.
        if ($totalRooms > $requiredRooms) {
            $rows = $this->group_room_rows;
            for ($i = count($rows) - 1; $i >= 0 && $totalRooms > $requiredRooms; $i--) {
                $qty = max(1, (int) ($rows[$i]['quantity'] ?? 1));
                $canRemove = min($qty, $totalRooms - $requiredRooms);
                $qty -= $canRemove;
                $totalRooms -= $canRemove;

                if ($qty <= 0) {
                    array_splice($rows, $i, 1);
                } else {
                    $rows[$i]['quantity'] = $qty;
                }
            }
            $this->group_room_rows = array_values($rows);
        }

        $this->rooms_count = $requiredRooms;
    }

    /**
     * Validate that requested number of rooms does not exceed available
     * rooms for the selected room type in the chosen period.
     */
    protected function checkRoomsAvailability(): void
    {
        if (! $this->room_type_id || ! $this->check_in_date || ! $this->check_out_date || $this->check_in_date >= $this->check_out_date) {
            $this->rooms_availability_warning = null;
            return;
        }

        $hotel = Hotel::getHotel();
        $bookedUnitIds = $this->getBookedUnitIdsForPeriod($this->check_in_date, $this->check_out_date);

        $roomIds = Room::where('hotel_id', $hotel->id)
            ->where('room_type_id', $this->room_type_id)
            ->where('is_active', true)
            ->pluck('id');

        $unitIds = RoomUnit::whereIn('room_id', $roomIds)
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        $available = array_diff($unitIds, $bookedUnitIds);
        $availableCount = count($available);

        if ($availableCount > 0 && (int) $this->rooms_count > $availableCount) {
            $this->rooms_availability_warning = sprintf(
                'You requested %d room(s) but only %d room(s) are vacant in this category for the selected dates.',
                (int) $this->rooms_count,
                $availableCount
            );
        } else {
            $this->rooms_availability_warning = null;
        }
    }

    public function updatedIsGroupBooking(): void
    {
        if ($this->is_group_booking && empty($this->group_room_rows)) {
            $this->group_room_rows = [['room_type_id' => '', 'quantity' => 1, 'adults' => 2, 'children' => 0]];
        }
        if (! $this->is_group_booking) {
            $this->group_room_rows = [];
        }
    }

    /** Convert current single booking into a group booking layout using suggested rooms. */
    public function convertToSuggestedGroupBooking(): void
    {
        if (! $this->room_type_id || ! $this->suggested_group_rooms) {
            return;
        }

        $roomType = RoomType::find($this->room_type_id);
        if (! $roomType) {
            return;
        }

        $rooms = max(1, (int) $this->suggested_group_rooms);
        $this->is_group_booking = true;
        $this->rooms_count = $rooms;

        $maxAdultsPerRoom = max(1, (int) ($roomType->max_adults ?? 2));
        $maxChildrenPerRoom = max(0, (int) ($roomType->max_children ?? 0));

        $remainingAdults = max(0, (int) $this->adult);
        $remainingChildren = max(0, (int) $this->child);

        $rows = [];
        for ($i = 0; $i < $rooms; $i++) {
            $rowAdults = min($maxAdultsPerRoom, $remainingAdults);
            $remainingAdults -= $rowAdults;

            $rowChildren = min($maxChildrenPerRoom, $remainingChildren);
            $remainingChildren -= $rowChildren;

            $rows[] = [
                'room_type_id' => (string) $this->room_type_id,
                'quantity' => 1,
                'adults' => $rowAdults,
                'children' => $rowChildren,
            ];
        }

        $this->group_room_rows = $rows;
    }

    public function addGroupRow(): void
    {
        $this->group_room_rows = array_values($this->group_room_rows);
        $this->group_room_rows[] = ['room_type_id' => '', 'quantity' => 1, 'adults' => 2, 'children' => 0];
    }

    public function removeGroupRow(int $index): void
    {
        $rows = array_values($this->group_room_rows);
        if (isset($rows[$index])) {
            array_splice($rows, $index, 1);
            $this->group_room_rows = $rows;
        }
    }

    public function updatedRateType(): void
    {
        $this->applyConfiguredRate();
    }

    public function updatedRoomUnitId(): void
    {
        $this->applyConfiguredRate();
        $this->checkRoomsAvailability();
        $this->checkOccupancyLimits();
        $this->refreshSelectedUnitBookingInfo();
    }

    /** Load the admin-configured rate for current room type/room and rate type into rate_rs (user can still override). */
    protected function applyConfiguredRate(): void
    {
        $hotel = Hotel::getHotel();
        $rateType = $this->rate_type ?: 'Locals';

        // If a specific room unit is selected, use room-level rate when set, otherwise fall back to room type
        if ($this->room_unit_id) {
            $unit = RoomUnit::with('room')->find($this->room_unit_id);
            if ($unit && $unit->room) {
                $amount = $unit->room->getAmountForRateType($rateType);
                if ($amount !== null) {
                    $this->rate_rs = (string) $amount;
                    return;
                }
            }
        }

        if ($this->room_type_id) {
            $roomType = RoomType::find($this->room_type_id);
            if ($roomType) {
                $amount = $roomType->getAmountForRateType($rateType);
                if ($amount !== null) {
                    $this->rate_rs = (string) $amount;
                }
            }
        }
    }

    public function updatedBusinessSource(): void
    {
        $this->selected_ota = '';
        $this->social_media_page = '';
        $this->referral_name = '';
        $this->referral_phone = '';
    }

    public function computeNights(): int
    {
        try {
            $in = Carbon::parse($this->check_in_date);
            $out = Carbon::parse($this->check_out_date);
            return max(0, $in->diffInDays($out));
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public function updatedGuestName(): void
    {
        $this->searchGuests();
    }

    /** Guest name search for auto-fill (debounced from view) */
    public function searchGuests(): void
    {
        $q = trim($this->guest_name);
        if (strlen($q) < 2) {
            $this->guest_suggestions = [];
            $this->guest_search_open = false;
            return;
        }
        // Only show the past-client search when user opted for existing client lookup
        if (! $this->use_existing_client) {
            $this->guest_suggestions = [];
            $this->guest_search_open = false;
            return;
        }

        $q = strtolower($q);

        // First try to search real reservations history for this hotel
        $hotel = Hotel::getHotel();
        $matches = Reservation::where('hotel_id', $hotel->id)
            ->where(function ($query) use ($q) {
                $query
                    ->whereRaw('LOWER(guest_name) LIKE ?', ['%' . $q . '%'])
                    ->orWhereRaw('LOWER(guest_phone) LIKE ?', ['%' . $q . '%'])
                    ->orWhereRaw('LOWER(guest_email) LIKE ?', ['%' . $q . '%']);
            })
            ->orderByDesc('check_in_date')
            ->limit(15)
            ->get();

        if ($matches->isEmpty()) {
            // Fall back to mock list if no real guests found (keeps UX working in demo mode)
            $all = static::mockGuests();
            $this->guest_suggestions = array_values(array_filter($all, function ($g) use ($q) {
                return str_contains(strtolower($g['name']), $q) || str_contains(strtolower($g['mobile'] ?? ''), $q);
            }));
        } else {
            $this->guest_suggestions = $matches->map(function (Reservation $r) {
                return [
                    'id' => $r->id,
                    'name' => $r->guest_name,
                    'mobile' => $r->guest_phone,
                    'email' => $r->guest_email,
                    'country' => $r->guest_country,
                    'address' => $r->guest_address,
                ];
            })->toArray();
        }

        $this->guest_search_open = count($this->guest_suggestions) > 0;
    }

    public function selectGuest(int $id): void
    {
        $hotel = Hotel::getHotel();
        $reservation = Reservation::where('hotel_id', $hotel->id)->find($id);

        if ($reservation) {
            $this->guest_salutation = $this->guest_salutation ?: 'Mr.';
            $this->guest_name = $reservation->guest_name ?? '';
            $this->guest_mobile = $reservation->guest_phone ?? '';
            $this->guest_email = $reservation->guest_email ?? '';
            $this->guest_address = $reservation->guest_address ?? '';
            $this->guest_country = $reservation->guest_country ?? 'Rwanda';
            $this->guest_state = '';
            $this->guest_city = '';
            $this->guest_zip = '';

            // Load simple stats: how many stays and how many reservations marked as referral from this guest.
            $name = $reservation->guest_name;
            $phone = $reservation->guest_phone;
            $email = $reservation->guest_email;

            $staysQuery = Reservation::where('hotel_id', $hotel->id);
            if ($name) {
                $staysQuery->where('guest_name', $name);
            }
            if ($phone) {
                $staysQuery->orWhere('guest_phone', $phone);
            }
            if ($email) {
                $staysQuery->orWhere('guest_email', $email);
            }
            $this->existing_guest_stay_count = $staysQuery->count();

            $this->existing_guest_referral_count = 0; // Placeholder – depends on how referrals are tracked.
        } else {
            // Fallback to mock guests when demo data is used.
            $all = static::mockGuests();
            foreach ($all as $g) {
                if ((int) $g['id'] === $id) {
                    $this->guest_salutation = str_starts_with($g['name'], 'Ms.') ? 'Ms.' : 'Mr.';
                    $this->guest_name = $g['name'];
                    $this->guest_mobile = $g['mobile'] ?? '';
                    $this->guest_email = $g['email'] ?? '';
                    $this->guest_address = $g['address'] ?? '';
                    $this->guest_country = $g['country'] ?? 'Rwanda';
                    $this->guest_state = $g['state'] ?? '';
                    $this->guest_city = $g['city'] ?? '';
                    $this->guest_zip = $g['zip'] ?? '';
                    $this->existing_guest_stay_count = null;
                    $this->existing_guest_referral_count = null;
                    break;
                }
            }
        }

        $this->guest_suggestions = [];
        $this->guest_search_open = false;
        $this->guest_confirmed = false;
    }

    /** Confirm the selected past client is added to the form. */
    public function confirmPastClient(): void
    {
        if (trim($this->guest_name) !== '' && trim($this->guest_mobile) !== '') {
            $this->guest_confirmed = true;
            session()->flash('message', 'Past client confirmed and added to the form.');
        } else {
            session()->flash('error', 'Please select a guest from the search or enter name and mobile.');
        }
    }

    public function closeGuestSearch(): void
    {
        $this->guest_search_open = false;
    }

    /** Room charges from rate × nights × rooms (single) or sum of group rows (group). */
    public function getRoomChargesTotal(): float
    {
        if ($this->is_group_booking) {
            return $this->getGroupChargesTotal();
        }
        $rate = (float) preg_replace('/[^0-9.]/', '', $this->rate_rs);
        $nights = $this->computeNights();
        return $rate * $nights * max(1, (int) $this->rooms_count);
    }

    /** Total room charges for group booking (sum of each row's rate × nights × quantity). */
    protected function getGroupChargesTotal(): float
    {
        $hotel = Hotel::getHotel();
        $nights = $this->computeNights();
        $rateForCalculation = (float) preg_replace('/[^0-9.]/', '', $this->rate_rs ?? '0');
        $total = 0.0;
        foreach ($this->group_room_rows as $row) {
            $rtId = $row['room_type_id'] ?? '';
            $qty = max(1, (int) ($row['quantity'] ?? 1));
            if ($rtId === '') {
                continue;
            }
            $roomType = RoomType::find($rtId);
            $rate = $roomType ? ($roomType->getAmountForRateType($this->rate_type ?: 'Locals') ?? $rateForCalculation) : $rateForCalculation;
            $total += $rate * $nights * $qty;
        }
        return $total;
    }

    /** VAT to remit to RRA: total × (18/118) when prices are VAT-inclusive. */
    public function getTaxesTotal(): float
    {
        if ($this->tax_exempt) {
            return 0;
        }
        return VatHelper::vatFromInclusive($this->getRoomChargesTotal());
    }

    /** Total due from guest (VAT-inclusive room total). */
    public function getDueAmount(): float
    {
        $total = $this->getRoomChargesTotal();

        // When tax is marked as exclusive, remove the VAT portion from the total
        // so the guest pays the net amount without VAT.
        if ($this->tax_exempt) {
            $vat = $this->getTaxesTotal();
            return max(0, $total - $vat);
        }

        return $total;
    }

    /** International currency: convert foreign to local or local to foreign */
    public function getConvertedAmount(): ?array
    {
        if (!$this->use_international_currency || !is_numeric($this->exchange_rate) || (float) $this->exchange_rate <= 0) {
            return null;
        }
        $rate = (float) $this->exchange_rate;
        $foreign = $this->amount_in_foreign !== '' && is_numeric($this->amount_in_foreign) ? (float) $this->amount_in_foreign : null;
        $local = $this->amount_in_local !== '' && is_numeric($this->amount_in_local) ? (float) $this->amount_in_local : null;
        if ($foreign !== null) {
            return ['foreign' => $foreign, 'local' => round($foreign * $rate, 2), 'currency' => $this->foreign_currency];
        }
        if ($local !== null) {
            return ['local' => $local, 'foreign' => round($local / $rate, 2), 'currency' => $this->foreign_currency];
        }
        return null;
    }

    public function updatedAmountInForeign(): void
    {
        if ($this->exchange_rate !== '' && is_numeric($this->exchange_rate) && $this->amount_in_foreign !== '' && is_numeric($this->amount_in_foreign)) {
            $this->amount_in_local = (string) round((float) $this->amount_in_foreign * (float) $this->exchange_rate, 2);
        }
    }

    public function updatedAmountInLocal(): void
    {
        if ($this->exchange_rate !== '' && is_numeric($this->exchange_rate) && $this->amount_in_local !== '' && is_numeric($this->amount_in_local)) {
            $this->amount_in_foreign = (string) round((float) $this->amount_in_local / (float) $this->exchange_rate, 2);
        }
    }

    /** Send a support request so manager/admin can complete occupancy details for the current room type. */
    public function sendOccupancySetupRequest(): void
    {
        $hotel = Hotel::getHotel();
        if (! $hotel || ! $this->room_type_id) {
            return;
        }

        $roomType = RoomType::find($this->room_type_id);
        if (! $roomType) {
            return;
        }

        SupportRequest::create([
            'hotel_id' => $hotel->id,
            'user_id' => auth()->id(),
            'subject' => 'Please set occupancy (max adults/children) for room type: ' . $roomType->name,
            'message' => 'Front office attempted to create a reservation for room type "' . $roomType->name . '" but max adults / children are not configured. '
                . 'Please open Rooms → Categories, edit this room type and set the allowed adults and children per room.',
            'status' => 'open',
        ]);

        session()->flash('message', 'Request sent to manager/admin to configure occupancy for ' . $roomType->name . '.');
    }

    public function reserve(): void
    {
        $rules = [
            'check_in_date' => 'required|date|after_or_equal:' . now()->toDateString(),
            'check_out_date' => 'required|date|after:check_in_date',
            'rate_rs' => 'nullable|numeric|min:0',
            'business_source' => 'nullable|string',
        ];

        $attributes = [
            'check_in_date' => 'Check-in date',
            'check_out_date' => 'Check-out date',
            'guest_name' => 'Guest name',
            'rate_rs' => 'Nightly rate (price per night)',
            'business_source' => 'Business source',
            'selected_ota' => 'OTA',
            'social_media_page' => 'Social media page',
            'referral_name' => 'Referral name',
            'referral_phone' => 'Referral phone',
        ];

        if ($this->is_group_booking) {
            // Group uses same guest name from Guest information section
        } else {
            $rules['room_type_id'] = 'required';
            $rules['rate_rs'] = 'required|numeric|min:0';
        }
        $rules['guest_name'] = 'required|string|min:2';

        if ($this->business_source === 'OTA') {
            $rules['selected_ota'] = 'required|string';
        } elseif ($this->business_source === 'Social media') {
            $rules['social_media_page'] = 'required|string';
        } elseif ($this->business_source === 'Referral') {
            $rules['referral_name'] = 'required|string';
            $rules['referral_phone'] = 'required|string';
        }

        $this->validate($rules, [], $attributes);

        $hotel = Hotel::getHotel();
        try {
            OperationalShiftActionGate::assertFrontOfficeActionAllowed($hotel);
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }
        $reservationNumber = Reservation::generateUniqueNumber($hotel->id);
        $bookedUnitIds = $this->getBookedUnitIdsForPeriod($this->check_in_date, $this->check_out_date);

        $unitIds = [];
        $totalAdults = (int) $this->adult;
        $totalChildren = (int) $this->child;
        $totalAmount = 0.0;
        $firstRoomTypeId = $this->room_type_id ?: null;
        $rateForCalculation = (float) preg_replace('/[^0-9.]/', '', $this->rate_rs ?? '0');
        $nights = $this->computeNights();

        if ($this->is_group_booking) {
            $totalAdults = 0;
            $totalChildren = 0;
            $usedUnitIds = [];
            foreach ($this->group_room_rows as $row) {
                $rtId = $row['room_type_id'] ?? '';
                $qty = max(1, (int) ($row['quantity'] ?? 1));
                $totalAdults += max(0, (int) ($row['adults'] ?? 0));
                $totalChildren += max(0, (int) ($row['children'] ?? 0));
                if ($rtId === '') {
                    continue;
                }
                $firstRoomTypeId = $firstRoomTypeId ?: $rtId;
                $roomIds = Room::where('hotel_id', $hotel->id)->where('room_type_id', $rtId)->where('is_active', true)->pluck('id');
                $candidateIds = RoomUnit::whereIn('room_id', $roomIds)->where('is_active', true)->whereNotIn('id', $bookedUnitIds)->whereNotIn('id', $usedUnitIds)->orderBy('sort_order')->orderBy('label')->pluck('id')->all();
                $take = array_slice($candidateIds, 0, $qty);
                foreach ($take as $uid) {
                    $usedUnitIds[] = $uid;
                    $unitIds[] = $uid;
                }
                $roomType = RoomType::find($rtId);
                if ($roomType) {
                    $rate = $roomType->getAmountForRateType($this->rate_type ?: 'Locals') ?? $rateForCalculation;
                    $totalAmount += $rate * $nights * count($take);
                } else {
                    $totalAmount += $rateForCalculation * $nights * count($take);
                }
            }
            if (empty($unitIds)) {
                $this->addError('group_room_rows', 'Select at least one room type and ensure enough rooms are available.');
                return;
            }
        } else {
            $totalAmount = $this->getRoomChargesTotal();
            if ($this->room_unit_id) {
                // Prevent creating overlapping reservation on same room/unit.
                $conflict = Reservation::where('hotel_id', $hotel->id)
                    ->whereNotIn('status', [Reservation::STATUS_CANCELLED, Reservation::STATUS_CHECKED_OUT])
                    ->where('check_out_date', '>', $this->check_in_date)
                    ->where('check_in_date', '<', $this->check_out_date)
                    ->whereHas('roomUnits', fn ($q) => $q->where('room_units.id', $this->room_unit_id))
                    ->first();
                if ($conflict) {
                    $this->addError('room_unit_id', 'Selected room/unit is already booked from ' . $conflict->check_in_date->format('Y-m-d') . ' to ' . $conflict->check_out_date->format('Y-m-d') . '. Choose different dates or add guest to the existing booking.');
                    $this->refreshSelectedUnitBookingInfo();
                    return;
                }
                $unitIds[] = (int) $this->room_unit_id;
            }
        }

        $paidAmount = 0;
        if ($this->use_international_currency && $this->amount_in_local !== '' && is_numeric($this->amount_in_local)) {
            $paidAmount = (float) $this->amount_in_local;
        } elseif ($this->payment_amount !== '' && is_numeric($this->payment_amount)) {
            $paidAmount = (float) $this->payment_amount;
        }

        if ($this->payment_mode_enabled && $paidAmount > 0.00001) {
            $payRules = [
                'payment_unified' => ['required', Rule::in(PaymentCatalog::unifiedAccommodationValues())],
            ];
            if (PaymentCatalog::unifiedChoiceRequiresClientDetails($this->payment_unified)) {
                $payRules['payment_client_reference'] = 'required|string|min:2|max:500';
            }
            $this->validate($payRules, [], [
                'payment_unified' => 'Payment type',
                'payment_client_reference' => 'Client / account details',
            ]);
        }

        $guestName = $this->guest_name;
        $guestStayPurpose = $this->guest_stay_purpose ?: null;
        if ($this->extra_bed) {
            $extraNote = 'Extra bed requested';
            $guestStayPurpose = $guestStayPurpose ? ($guestStayPurpose . ' · ' . $extraNote) : $extraNote;
        }
        $businessSourceDetail = null;
        switch ($this->business_source) {
            case 'OTA':
                $businessSourceDetail = $this->selected_ota ?: null;
                break;
            case 'Social media':
                $businessSourceDetail = $this->social_media_page ?: null;
                break;
            case 'Referral':
                $parts = trim($this->referral_name . ' ' . $this->referral_phone);
                $businessSourceDetail = $parts !== '' ? $parts : null;
                break;
            default:
                $businessSourceDetail = null;
        }

        $reservation = Reservation::create([
            'hotel_id' => $hotel->id,
            'reservation_number' => $reservationNumber,
            'guest_name' => $guestName,
            'guest_email' => $this->is_group_booking ? null : ($this->guest_email ?: null),
            'guest_phone' => $this->is_group_booking ? null : ($this->guest_mobile ?: null),
            'guest_country' => $this->is_group_booking ? null : ($this->guest_country ?: null),
            'guest_address' => $this->is_group_booking ? null : ($this->guest_address ?: null),
            'guest_id_number' => $this->is_group_booking ? null : ($this->guest_id_number ?: null),
            'guest_profession' => $this->is_group_booking ? null : ($this->guest_profession ?: null),
            'guest_stay_purpose' => $this->is_group_booking ? null : $guestStayPurpose,
            'check_in_date' => $this->check_in_date,
            'check_out_date' => $this->check_out_date,
            'check_in_time' => $this->parseTime($this->check_in_time),
            'check_out_time' => $this->parseTime($this->check_out_time),
            'room_type_id' => $firstRoomTypeId ?: null,
            'rate_plan' => $this->rate_type ?: null,
            'adult_count' => max(1, $totalAdults),
            'child_count' => $totalChildren,
            'total_amount' => $totalAmount,
            'paid_amount' => 0,
            'currency' => $hotel->currency ?? 'RWF',
            'status' => Reservation::STATUS_CONFIRMED,
            'booking_source' => $this->booking_source ?: null,
            'reservation_type' => $this->reservation_type ?: null,
            'business_source' => $this->is_group_booking ? 'Group' : ($this->business_source ?: null),
            'business_source_detail' => $this->is_group_booking ? ($this->guest_company_name ?: $this->guest_name) : $businessSourceDetail,
            'group_name' => $this->is_group_booking ? $this->guest_name : null,
            'expected_guest_count' => $this->is_group_booking ? ($totalAdults + $totalChildren) : null,
        ]);

        if (count($unitIds) > 0) {
            $reservation->roomUnits()->sync($unitIds);
        }

        if ($this->pre_registration_id) {
            $pre = PreRegistration::where('hotel_id', $hotel->id)->find($this->pre_registration_id);
            if ($pre) {
                $pre->update([
                    'reservation_id' => $reservation->id,
                    'status' => PreRegistration::STATUS_CHECKED_IN,
                ]);
            }
        }

        if ($this->payment_mode_enabled && $paidAmount > 0.00001) {
            $user = Auth::user();
            if ($user) {
                $stored = PaymentCatalog::expandUnifiedToStorage($this->payment_unified, false);
                $method = PaymentCatalog::normalizeReservationMethod($stored['payment_method']);
                $pStatus = PaymentCatalog::normalizeStatus($stored['payment_status']);
                $payComment = PaymentCatalog::mergeClientReferenceIntoComment('', $this->payment_client_reference ?? '');
                $receipt = 'RCPT-' . date('Ymd-His') . '-' . $reservation->id . '-' . random_int(100, 999);
                $receipt = substr($receipt, 0, 50);
                ReservationPayment::create([
                    'hotel_id' => $hotel->id,
                    'reservation_id' => $reservation->id,
                    'amount' => $paidAmount,
                    'currency' => $reservation->currency ?? ($hotel->currency ?? 'RWF'),
                    'payment_type' => $method,
                    'payment_method' => $method,
                    'payment_status' => $pStatus,
                    'received_by' => $user->id,
                    'received_at' => Carbon::now(),
                    'receipt_number' => $receipt,
                    'status' => 'confirmed',
                    'comment' => $payComment !== '' ? $payComment : null,
                    'total_paid_after' => 0,
                    'balance_after' => 0,
                ]);
                ReservationPayment::recomputeBalancesForReservation((int) $reservation->id);
                $reservation->refresh();
            }
        }

        $this->reservation_number = $reservationNumber;
        $this->reserve_success = true;
        session()->flash('message', 'Reservation created. Reservation number: ' . $this->reservation_number);
    }

    protected function parseTime(?string $time): ?string
    {
        if ($time === null || $time === '') {
            return null;
        }
        try {
            $parsed = Carbon::parse($time);
            return $parsed->format('H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Refresh booked date ranges + overlap info for the selected room unit. */
    protected function refreshSelectedUnitBookingInfo(): void
    {
        $this->room_unit_booked_ranges = [];
        $this->overlap_reservations = [];
        $this->show_overlap_modal = false;

        if (! $this->room_unit_id) {
            return;
        }

        $hotel = Hotel::getHotel();
        $reservations = Reservation::where('hotel_id', $hotel->id)
            ->whereNotIn('status', [Reservation::STATUS_CANCELLED, Reservation::STATUS_CHECKED_OUT])
            ->whereHas('roomUnits', fn ($q) => $q->where('room_units.id', $this->room_unit_id))
            ->orderBy('check_in_date')
            ->get();

        $this->room_unit_booked_ranges = $reservations->map(function (Reservation $r) {
            return [
                'reservation_id' => $r->id,
                'reservation_number' => $r->reservation_number,
                'guest_name' => $r->guest_name,
                'from' => $r->check_in_date?->format('Y-m-d'),
                'to' => $r->check_out_date?->format('Y-m-d'),
                'status' => $r->status,
            ];
        })->toArray();

        if (! $this->check_in_date || ! $this->check_out_date || $this->check_in_date >= $this->check_out_date) {
            return;
        }

        // Only show conflicts for the selected date range.
        $overlaps = $reservations->filter(function (Reservation $r) {
            return $r->check_out_date->format('Y-m-d') > $this->check_in_date
                && $r->check_in_date->format('Y-m-d') < $this->check_out_date;
        })->values();

        $this->overlap_reservations = $overlaps->map(function (Reservation $r) {
            return [
                'reservation_id' => (int) $r->id,
                'reservation_number' => $r->reservation_number,
                'guest_name' => $r->guest_name,
                'from' => $r->check_in_date?->format('Y-m-d'),
                'to' => $r->check_out_date?->format('Y-m-d'),
                'status' => $r->status,
            ];
        })->toArray();

        $this->room_unit_booked_ranges = $this->overlap_reservations;
        $this->show_overlap_modal = count($this->overlap_reservations) > 0;
    }

    /**
     * For occupied room flow: add this guest as additional occupant on the existing
     * overlapping reservation (e.g. couple) instead of creating a new reservation.
     */
    public function addGuestToExistingBooking(int $reservationId): void
    {
        $this->validate([
            'guest_name' => 'required|string|min:2',
        ], [], [
            'guest_name' => 'Guest name',
        ]);

        $hotel = Hotel::getHotel();
        $reservation = Reservation::where('hotel_id', $hotel->id)->find($reservationId);
        if (! $reservation) {
            session()->flash('error', 'Existing reservation not found.');
            return;
        }

        try {
            OperationalShiftActionGate::assertFrontOfficeActionAllowed($hotel);
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $existingNames = array_map('trim', explode(',', (string) $reservation->guest_name));
        if (! in_array(trim($this->guest_name), $existingNames, true)) {
            $existingNames[] = trim($this->guest_name);
        }
        $reservation->guest_name = implode(', ', array_filter($existingNames));
        $reservation->adult_count = max(1, (int) $reservation->adult_count + max(0, (int) $this->adult));
        $reservation->child_count = max(0, (int) $reservation->child_count + max(0, (int) $this->child));
        if (! $reservation->guest_phone && $this->guest_mobile) {
            $reservation->guest_phone = $this->guest_mobile;
        }
        if (! $reservation->guest_email && $this->guest_email) {
            $reservation->guest_email = $this->guest_email;
        }
        $reservation->save();

        session()->flash('message', 'Guest added to existing booking ' . ($reservation->reservation_number ?? ('#' . $reservation->id)) . '.');
        $this->overlap_reservations = [];
        $this->room_unit_booked_ranges = [];
        $this->show_overlap_modal = false;
    }

    public function closeOverlapModal(): void
    {
        $this->show_overlap_modal = false;
    }

    /**
     * Room unit IDs that are already booked for the given period (overlap with check_in..check_out).
     * Used to show only available rooms/units and avoid overbooking.
     * Excludes cancelled and checked_out so that once a guest checks out, the room is available again.
     */
    protected function getBookedUnitIdsForPeriod(string $checkIn, string $checkOut): array
    {
        $hotel = Hotel::getHotel();
        return Reservation::where('hotel_id', $hotel->id)
            ->whereNotIn('status', [Reservation::STATUS_CANCELLED, Reservation::STATUS_CHECKED_OUT])
            ->where('check_out_date', '>', $checkIn)
            ->where('check_in_date', '<', $checkOut)
            ->with('roomUnits')
            ->get()
            ->pluck('roomUnits')
            ->flatten()
            ->pluck('id')
            ->unique()
            ->filter()
            ->values()
            ->all();
    }

    /**
     * All active room types with available unit count for the selected check-in/check-out period.
     * Used in the room type dropdown to show "Twin Room (5)" etc.
     */
    public function getRoomTypesWithAvailableCount(): array
    {
        $hotel = Hotel::getHotel();
        $bookedUnitIds = [];
        if ($this->check_in_date && $this->check_out_date && $this->check_in_date < $this->check_out_date) {
            $bookedUnitIds = $this->getBookedUnitIdsForPeriod($this->check_in_date, $this->check_out_date);
        }
        $allRoomTypes = RoomType::where('hotel_id', $hotel->id)->where('is_active', true)->with(['rooms' => fn ($q) => $q->where('is_active', true)])->orderBy('name')->get();
        $result = [];
        foreach ($allRoomTypes as $rt) {
            $roomIds = $rt->rooms->pluck('id')->all();
            $unitIds = [];
            if (! empty($roomIds)) {
                $unitIds = RoomUnit::whereIn('room_id', $roomIds)->where('is_active', true)->pluck('id')->all();
            }
            $available = array_diff($unitIds, $bookedUnitIds);
            $result[] = [
                'id' => $rt->id,
                'name' => $rt->name,
                'available_count' => count($available),
                'max_adults' => (int) ($rt->max_adults ?? 0),
                'max_children' => (int) ($rt->max_children ?? 0),
                'occupancy_label' => $rt->getOccupancyLabel(),
            ];
        }
        return $result;
    }

    public function render()
    {
        $hotel = Hotel::getHotel();
        $bookedUnitIds = [];
        if ($this->check_in_date && $this->check_out_date && $this->check_in_date < $this->check_out_date) {
            $bookedUnitIds = $this->getBookedUnitIdsForPeriod($this->check_in_date, $this->check_out_date);
        }

        $roomTypesWithCount = $this->getRoomTypesWithAvailableCount();
        $allRoomTypes = RoomType::where('hotel_id', $hotel->id)->where('is_active', true)->with(['rooms' => fn ($q) => $q->where('is_active', true)])->orderBy('name')->get();
        $roomTypes = $allRoomTypes->filter(function (RoomType $rt) use ($bookedUnitIds) {
            $roomIds = $rt->rooms->pluck('id')->all();
            if (empty($roomIds)) {
                return false;
            }
            $unitIds = RoomUnit::whereIn('room_id', $roomIds)->where('is_active', true)->pluck('id')->all();
            $available = array_diff($unitIds, $bookedUnitIds);
            return count($available) > 0;
        });

        $roomUnits = collect();
        if ($this->room_type_id) {
            $roomIds = Room::where('hotel_id', $hotel->id)->where('room_type_id', $this->room_type_id)->where('is_active', true)->pluck('id');
            $roomUnits = RoomUnit::whereIn('room_id', $roomIds)->where('is_active', true)->whereNotIn('id', $bookedUnitIds)->orderBy('sort_order')->orderBy('label')->get();
        }

        $currency = $hotel->currency ?? 'RWF';
        return view('livewire.front-office.add-reservation', [
            'roomTypes' => $roomTypes,
            'roomTypesWithCount' => $roomTypesWithCount,
            'roomUnits' => $roomUnits,
            'currency' => $currency,
            'allCountries' => static::getAllCountries(),
        ])->layout('livewire.layouts.app-layout');
    }
}
