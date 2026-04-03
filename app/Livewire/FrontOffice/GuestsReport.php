<?php

namespace App\Livewire\FrontOffice;

use App\Models\Hotel;
use App\Models\Reservation;
use App\Traits\ChecksModuleStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class GuestsReport extends Component
{
    use ChecksModuleStatus;

    public string $date_from = '';

    public string $date_to = '';

    /** Shown on print / export; defaults from hotel or current user (prepared). */
    public string $prepared_by_name = '';

    public string $verified_by_name = '';

    public string $approved_by_name = '';

    /** @var array<int, array{guest_name: string, phone_email: string, guest_id_number: string, guest_country: string, guest_profession: string, guest_stay_purpose: string, check_in_date: string, nights: int}> */
    public array $guests = [];

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
        $this->date_from = $today;
        $this->date_to = $today;
        $this->seedSignatureNamesFromHotel($hotel);
        $this->loadGuests();
    }

    protected function seedSignatureNamesFromHotel(Hotel $hotel): void
    {
        $user = Auth::user();
        if (Schema::hasColumn('hotels', 'guests_report_signature_prepared_default')) {
            $this->prepared_by_name = (string) ($hotel->guests_report_signature_prepared_default ?? $user?->name ?? '');
            $this->verified_by_name = (string) ($hotel->guests_report_signature_verified_default ?? '');
            $this->approved_by_name = (string) ($hotel->guests_report_signature_approved_default ?? '');
        } else {
            $this->prepared_by_name = (string) ($user?->name ?? '');
            $this->verified_by_name = '';
            $this->approved_by_name = '';
        }
    }

    /**
     * Save the three names as hotel defaults (managers only) so daily reports pre-fill for all staff.
     */
    public function saveSignatureDefaults(): void
    {
        $user = Auth::user();
        if (! $user || (! $user->canNavigateModules() && ! $user->isSuperAdmin())) {
            session()->flash('error', 'Only hotel managers can save default signature names for the hotel.');

            return;
        }
        if (! Schema::hasColumn('hotels', 'guests_report_signature_prepared_default')) {
            session()->flash('error', 'Run database migrations to enable saving signature defaults.');

            return;
        }

        $this->validate([
            'prepared_by_name' => 'nullable|string|max:255',
            'verified_by_name' => 'nullable|string|max:255',
            'approved_by_name' => 'nullable|string|max:255',
        ], [], [
            'prepared_by_name' => 'prepared by',
            'verified_by_name' => 'verified by',
            'approved_by_name' => 'approved by',
        ]);

        $hotel = Hotel::getHotel();
        if (! $hotel) {
            return;
        }

        $hotel->update([
            'guests_report_signature_prepared_default' => $this->prepared_by_name !== '' ? $this->prepared_by_name : null,
            'guests_report_signature_verified_default' => $this->verified_by_name !== '' ? $this->verified_by_name : null,
            'guests_report_signature_approved_default' => $this->approved_by_name !== '' ? $this->approved_by_name : null,
        ]);

        session()->flash('message', 'Default names for this report are saved. They appear whenever you open Guests Report; you can still change them before printing.');
    }

    public function updatedDateFrom(): void
    {
        if ($this->date_to && $this->date_from > $this->date_to) {
            $this->date_to = $this->date_from;
        }
        $this->loadGuests();
    }

    public function updatedDateTo(): void
    {
        if ($this->date_from && $this->date_to < $this->date_from) {
            $this->date_from = $this->date_to;
        }
        $this->loadGuests();
    }

    public function loadGuests(): void
    {
        $hotel = Hotel::getHotel();
        $from = $this->date_from ?: Carbon::now($hotel->getTimezone())->format('Y-m-d');
        $to = $this->date_to ?: $from;
        if ($from > $to) {
            [$from, $to] = [$to, $from];
            $this->date_from = $from;
            $this->date_to = $to;
        }

        $reservations = Reservation::where('hotel_id', $hotel->id)
            ->where('status', '!=', Reservation::STATUS_CANCELLED)
            ->where('check_in_date', '<=', $to)
            ->where('check_out_date', '>=', $from)
            ->orderBy('check_in_date')
            ->get();

        $this->guests = $reservations->map(function (Reservation $r) {
            $nights = max(0, $r->check_in_date->diffInDays($r->check_out_date));
            return [
                'guest_name' => $r->guest_name ?? '—',
                'phone_email' => $r->guestsReportPhoneEmailDisplay(),
                'guest_id_number' => $r->guest_id_number ?? '—',
                'guest_country' => $r->guest_country ?? '—',
                'guest_profession' => $r->guest_profession ?? '—',
                'guest_stay_purpose' => $r->guest_stay_purpose ?? '—',
                'check_in_date' => $r->check_in_date?->format('Y-m-d') ?? '—',
                'nights' => $nights,
            ];
        })->toArray();
    }

    public function render()
    {
        return view('livewire.front-office.guests-report')->layout('livewire.layouts.app-layout');
    }
}
