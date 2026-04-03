<?php

namespace App\Livewire\FrontOffice;

use App\Models\Hotel;
use App\Services\OperationalShiftActionGate;
use App\Models\Reservation;
use App\Traits\ChecksModuleStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Quick group booking: group name, check-in/out, guest count, notes.
 * Creates a reservation with a shareable reference (reservation_number);
 * no room assigned yet – guests self-register and reception assigns rooms.
 */
class QuickGroupBooking extends Component
{
    use ChecksModuleStatus;

    public string $group_name = '';
    public string $check_in_date = '';
    public string $check_out_date = '';
    public string $expected_guest_count = '';
    public string $notes = '';
    public ?string $reservation_number = null;
    public bool $success = false;

    public function mount(): void
    {
        session(['selected_module' => 'front-office']);
        $this->ensureModuleEnabled('front-office');
        $module = \App\Models\Module::where('slug', 'front-office')->first();
        if ($module && ! Auth::user()->hasModuleAccess($module->id)) {
            abort(403, 'You do not have access to Front Office.');
        }
        $hotel = Hotel::getHotel();
        $today = Carbon::now($hotel->getTimezone())->format('Y-m-d');
        $this->check_in_date = $today;
        $this->check_out_date = $today;
    }

    public function save(): void
    {
        $this->validate([
            'group_name' => 'required|string|min:2|max:200',
            'check_in_date' => 'required|date|after_or_equal:' . now()->toDateString(),
            'check_out_date' => 'required|date|after_or_equal:check_in_date',
            'expected_guest_count' => 'nullable|integer|min:1|max:500',
            'notes' => 'nullable|string|max:1000',
        ], [], [
            'group_name' => 'Group / Company name',
            'check_in_date' => 'Check-in date',
            'check_out_date' => 'Check-out date',
            'expected_guest_count' => 'Number of guests',
        ]);

        $hotel = Hotel::getHotel();
        try {
            OperationalShiftActionGate::assertFrontOfficeActionAllowed($hotel);
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }
        $reservationNumber = Reservation::generateUniqueNumber($hotel->id);

        Reservation::create([
            'hotel_id' => $hotel->id,
            'reservation_number' => $reservationNumber,
            'guest_name' => 'Group: ' . $this->group_name,
            'guest_email' => null,
            'guest_phone' => null,
            'guest_country' => null,
            'guest_address' => null,
            'check_in_date' => $this->check_in_date,
            'check_out_date' => $this->check_out_date,
            'check_in_time' => null,
            'check_out_time' => null,
            'room_type_id' => null,
            'rate_plan' => null,
            'adult_count' => (int) ($this->expected_guest_count ?: 1),
            'child_count' => 0,
            'total_amount' => null,
            'paid_amount' => 0,
            'currency' => $hotel->currency ?? 'RWF',
            'status' => Reservation::STATUS_CONFIRMED,
            'booking_source' => 'Direct',
            'reservation_type' => 'Confirm Booking',
            'business_source' => 'Group',
            'business_source_detail' => $this->group_name,
            'group_name' => $this->group_name,
            'expected_guest_count' => $this->expected_guest_count ? (int) $this->expected_guest_count : null,
        ]);

        $this->reservation_number = $reservationNumber;
        $this->success = true;
        session()->flash('message', 'Group booking created. Share the reservation reference: ' . $reservationNumber);
    }

    public function startNew(): void
    {
        $this->success = false;
        $this->reservation_number = null;
        $this->group_name = '';
        $this->notes = '';
        $hotel = Hotel::getHotel();
        $today = Carbon::now($hotel->getTimezone())->format('Y-m-d');
        $this->check_in_date = $today;
        $this->check_out_date = $today;
        $this->expected_guest_count = '';
    }

    public function render()
    {
        return view('livewire.front-office.quick-group-booking')->layout('livewire.layouts.app-layout');
    }
}
