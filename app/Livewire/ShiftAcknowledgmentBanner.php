<?php

namespace App\Livewire;

use App\Models\Hotel;
use App\Models\OperationalShift;
use App\Services\OperationalShiftActionGate;
use App\Services\OperationalShiftOpenRequestService;
use App\Services\OperationalShiftService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Operational shift awareness for all staff: shows open vs no shift per module, reminders for long shifts,
 * and optional "request shift open" workflow.
 */
class ShiftAcknowledgmentBanner extends Component
{
    public ?string $targetScope = null;

    public bool $onlyWhenMissing = false;

    /** @var array<int, array<string, mixed>> */
    public array $shiftRows = [];

    /** @var array<int, array<string, mixed>> */
    public array $scopeStatusRows = [];

    public bool $showRequestModal = false;

    public string $requestScope = '';

    public string $requestNote = '';

    public string $openNowScope = '';

    public string $openNowNote = '';

    public bool $showOpenNowModal = false;

    public function mount(?string $targetScope = null, bool $onlyWhenMissing = false): void
    {
        $this->targetScope = $targetScope;
        $this->onlyWhenMissing = $onlyWhenMissing;
        $this->loadRows();
    }

    #[On('operational-shift-updated')]
    public function refreshAfterShiftChange(): void
    {
        $this->loadRows();
    }

    public function loadRows(): void
    {
        $this->loadReminderRows();
        $this->loadScopeStatusRows();
    }

    protected function loadReminderRows(): void
    {
        $this->shiftRows = [];
        $user = Auth::user();
        $hotel = Hotel::getHotel();
        if (! $user || ! $hotel) {
            return;
        }

        $visible = OperationalShiftActionGate::getVisibleOpenShiftsForUser($hotel, $user);
        foreach ($visible as $shift) {
            $this->shiftRows[] = $this->rowFromShift($shift);
        }
    }

    protected function loadScopeStatusRows(): void
    {
        $this->scopeStatusRows = [];
        $user = Auth::user();
        $hotel = Hotel::getHotel();
        if (! $user || ! $hotel || ! OperationalShiftOpenRequestService::isEnabled()) {
            return;
        }
        if (! OperationalShiftActionGate::appliesOperationalShiftWorkflow($hotel)) {
            return;
        }

        $scopes = OperationalShiftActionGate::relevantModuleScopesForUser($hotel, $user);
        if ($this->targetScope) {
            $scopes = array_values(array_filter($scopes, fn (string $s) => $s === $this->targetScope));
        }

        foreach ($scopes as $scope) {
            $open = OperationalShiftService::getOpenByScope($hotel, $scope);
            if ($this->onlyWhenMissing && $open !== null) {
                continue;
            }
            $myPending = OperationalShiftOpenRequestService::pendingForUserScope($hotel, $user, $scope);
            $pendingTotal = OperationalShiftOpenRequestService::pendingCountForScope($hotel, $scope);
            $canOpenNow = OperationalShiftOpenRequestService::userCanOpenOperationalScope($user, $scope);
            $canResolveRequests = OperationalShiftOpenRequestService::userCanResolveRequests($user);

            $this->scopeStatusRows[] = [
                'scope' => $scope,
                'scope_label' => OperationalShiftActionGate::labelForScope($scope),
                'is_open' => $open !== null,
                'reference_date' => $open?->reference_date?->format('Y-m-d'),
                'opened_at' => $open?->opened_at?->format('Y-m-d H:i'),
                'hours_open' => $open ? round($open->durationHours(), 1) : null,
                'my_pending_request' => $myPending !== null,
                'pending_total' => $pendingTotal,
                'can_open_now' => $canOpenNow,
                'can_resolve_requests' => $canResolveRequests,
            ];
        }
    }

    protected function rowFromShift(OperationalShift $shift): array
    {
        return [
            'id' => $shift->id,
            'scope' => $shift->module_scope,
            'scope_label' => OperationalShiftActionGate::labelForScope($shift->module_scope),
            'reference_date' => $shift->reference_date?->format('Y-m-d'),
            'opened_at' => $shift->opened_at?->format('Y-m-d H:i'),
            'hours_open' => round($shift->durationHours(), 1),
            'is_long' => OperationalShiftActionGate::requiresLongShiftAcknowledgment($shift),
            'long_acknowledged' => OperationalShiftActionGate::hasLongShiftAcknowledgment((int) $shift->id),
        ];
    }

    public function openRequestModal(string $scope): void
    {
        $this->requestScope = $scope;
        $this->requestNote = '';
        $this->showRequestModal = true;
    }

    public function cancelRequestModal(): void
    {
        $this->showRequestModal = false;
        $this->requestScope = '';
        $this->requestNote = '';
    }

    public function openOpenNowModal(string $scope): void
    {
        $this->openNowScope = $scope;
        $this->openNowNote = '';
        $this->showOpenNowModal = true;
    }

    public function cancelOpenNowModal(): void
    {
        $this->showOpenNowModal = false;
        $this->openNowScope = '';
        $this->openNowNote = '';
    }

    public function openShiftNow(): void
    {
        $this->validate([
            'openNowScope' => 'required|string',
            'openNowNote' => 'nullable|string|max:2000',
        ]);

        $user = Auth::user();
        $hotel = Hotel::getHotel();
        if (! $user || ! $hotel) {
            $this->cancelOpenNowModal();

            return;
        }
        if (! OperationalShiftOpenRequestService::userCanOpenOperationalScope($user, $this->openNowScope)) {
            session()->flash('error', 'You do not have permission to open this shift directly.');

            return;
        }

        try {
            $note = trim($this->openNowNote) !== '' ? trim($this->openNowNote) : null;
            OperationalShiftService::openShift($hotel, $this->openNowScope, $user->id, $note);

            // Mark all pending requests for this scope fulfilled if requests workflow exists.
            if (OperationalShiftOpenRequestService::isEnabled()) {
                \App\Models\OperationalShiftOpenRequest::query()
                    ->where('hotel_id', $hotel->id)
                    ->where('module_scope', $this->openNowScope)
                    ->where('status', \App\Models\OperationalShiftOpenRequest::STATUS_PENDING)
                    ->update([
                        'status' => \App\Models\OperationalShiftOpenRequest::STATUS_FULFILLED,
                        'resolved_by' => $user->id,
                        'resolved_at' => now(),
                        'resolution_note' => null,
                    ]);
            }

            session()->flash('message', 'Shift opened successfully. Staff can now continue operations for this area.');
            $this->cancelOpenNowModal();
            $this->loadRows();
            $this->dispatch('operational-shift-updated');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function submitShiftOpenRequest(): void
    {
        $this->validate([
            'requestScope' => 'required|string',
            'requestNote' => 'nullable|string|max:2000',
        ]);

        $user = Auth::user();
        $hotel = Hotel::getHotel();
        if (! $user || ! $hotel) {
            $this->cancelRequestModal();

            return;
        }

        try {
            OperationalShiftOpenRequestService::createRequest(
                $hotel,
                $user,
                $this->requestScope,
                $this->requestNote !== '' ? $this->requestNote : null
            );
            session()->flash('message', 'Your request was sent. A supervisor can open the shift from Shift management.');
            $this->cancelRequestModal();
            $this->loadRows();
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function continueWithShift(int $shiftId): void
    {
        $user = Auth::user();
        $hotel = Hotel::getHotel();
        if (! $user || ! $hotel) {
            return;
        }
        $shift = OperationalShift::where('hotel_id', $hotel->id)
            ->where('id', $shiftId)
            ->where('status', OperationalShift::STATUS_OPEN)
            ->first();
        if (! $shift) {
            $this->loadRows();

            return;
        }

        OperationalShiftActionGate::setLongShiftAcknowledgment($shiftId);
        OperationalShiftActionGate::snoozePromptTenHours((int) $user->id, $shiftId);
        $this->loadRows();
    }

    public function remindInTenHours(int $shiftId): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }
        OperationalShiftActionGate::snoozePromptTenHours((int) $user->id, $shiftId);
        $this->loadRows();
    }

    public function render()
    {
        return view('livewire.shift-acknowledgment-banner');
    }
}
