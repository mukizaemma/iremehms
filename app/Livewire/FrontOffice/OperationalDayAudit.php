<?php

namespace App\Livewire\FrontOffice;

use App\Models\Hotel;
use App\Services\BusinessDayService;
use App\Services\OperationalDayAuditService;
use App\Traits\ChecksModuleStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

class OperationalDayAudit extends Component
{
    use ChecksModuleStatus;

    #[Url]
    public string $date = '';

    public string $physicalCashCounted = '';

    public string $auditNotes = '';

    public function mount(): void
    {
        session(['selected_module' => 'front-office']);
        $this->ensureModuleEnabled('front-office');

        $user = Auth::user();
        if (! $user || ! self::userCanAudit($user)) {
            abort(403, 'You do not have permission to run the operational day audit.');
        }

        if ($this->date === '') {
            $this->date = Hotel::getTodayForHotel();
        }
    }

    public static function userCanAudit($user): bool
    {
        return $user->isSuperAdmin()
            || $user->canNavigateModules()
            || $user->hasPermission('fo_operational_audit')
            || $user->hasPermission('fo_collect_payment')
            || $user->hasPermission('fo_check_in_out')
            || $user->isReceptionist();
    }

    public function updatedDate(): void
    {
        $this->date = $this->date ?: Hotel::getTodayForHotel();
    }

    public function refreshAudit(): void
    {
        // Re-render computed audit.
    }

    /** @return array<string, mixed> */
    public function getAuditProperty(): array
    {
        $hotel = Hotel::getHotel();
        if (! $hotel) {
            return ['sections' => [], 'summary' => ['blockers' => 0, 'warnings' => 0, 'ok' => 0]];
        }

        return OperationalDayAuditService::build($hotel, $this->date);
    }

    public function getCashVarianceProperty(): ?float
    {
        $physical = trim($this->physicalCashCounted);
        if ($physical === '' || ! is_numeric($physical)) {
            return null;
        }

        $systemCash = (float) ($this->audit['payments_today']['cash'] ?? 0);

        return round((float) $physical - $systemCash, 2);
    }

    public function getCanCloseBusinessDayFromHereProperty(): bool
    {
        $user = Auth::user();

        return $user
            && ($user->isSuperAdmin() || $user->canNavigateModules())
            && OperationalDayAuditService::canCloseBusinessDay($this->audit)
            && ! ($this->audit['business_day_closed'] ?? false);
    }

    public function closeBusinessDayFromAudit(): void
    {
        $user = Auth::user();
        if (! $user || ! ($user->isSuperAdmin() || $user->canNavigateModules())) {
            session()->flash('error', 'Only management can close the business day.');

            return;
        }

        if (! OperationalDayAuditService::canCloseBusinessDay($this->audit)) {
            session()->flash('error', 'Resolve all blockers before closing the business day.');

            return;
        }

        $variance = $this->cashVariance;
        if ($variance !== null && abs($variance) > 0.02 && trim($this->auditNotes) === '') {
            session()->flash('error', 'Cash count differs from system — add a note in Audit notes before closing.');

            return;
        }

        try {
            BusinessDayService::closeBusinessDay((int) $user->id);
            session()->flash('message', 'Business day closed successfully.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.front-office.operational-day-audit', [
            'audit' => $this->audit,
            'cashVariance' => $this->cashVariance,
            'canCloseBusinessDay' => $this->canCloseBusinessDayFromHere,
        ])->layout('livewire.layouts.app-layout');
    }
}
