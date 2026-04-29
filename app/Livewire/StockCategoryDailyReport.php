<?php

namespace App\Livewire;

use App\Enums\InventoryCategory;
use App\Models\Hotel;
use App\Models\StockDailyReport;
use App\Models\StockDailyReportComment;
use App\Models\StockLocation;
use App\Services\ActivityLogger;
use App\Services\StockCategoryReportService;
use App\Support\ActivityLogModule;
use App\Traits\ChecksModuleStatus;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

class StockCategoryDailyReport extends Component
{
    use ChecksModuleStatus;

    #[Url]
    public string $reportDate = '';

    #[Url]
    public ?string $detailCategory = null;

    #[Url]
    public ?int $stockLocationId = null;

    public array $summaryRows = [];

    public array $detailLines = [];

    public array $stockLocations = [];

    public string $prepared_by_name = '';

    public string $checked_by_name = '';

    public string $received_by_name = '';

    public string $approved_by_name = '';

    public bool $auditEnabled = false;

    public ?array $auditReport = null;

    public array $auditComments = [];

    public string $auditComment = '';

    public string $rejectionReason = '';

    public function mount(): void
    {
        $this->ensureModuleEnabled('store');
        $user = Auth::user();
        if (! $user || ! $user->canViewStockReports()) {
            abort(403, 'You do not have access to this stock report.');
        }

        $hotel = Hotel::getHotel();
        $this->reportDate = $this->reportDate !== ''
            ? $this->reportDate
            : ($hotel ? Hotel::getTodayForHotel() : now()->format('Y-m-d'));

        $this->stockLocations = $this->stockLocationsForPicker();
        $this->auditEnabled = (bool) $hotel?->isStockDailyReportAuditEnabled();

        $this->loadReport();
        $this->loadAuditState();
    }

    public function updatedReportDate(): void
    {
        $this->loadReport();
        $this->loadAuditState();
    }

    public function updatedStockLocationId(): void
    {
        $this->loadReport();
        $this->loadAuditState();
    }

    public function updatedDetailCategory(): void
    {
        $this->loadReport();
    }

    public function selectCategory(?string $category): void
    {
        $this->detailCategory = $category;
        $this->loadReport();
    }

    public function loadReport(): void
    {
        if ($this->requiresStockLocationSelection() && ! $this->stockLocationId) {
            $this->summaryRows = [];
            $this->detailLines = [];

            return;
        }

        $service = app(StockCategoryReportService::class);
        $d = Carbon::parse($this->reportDate)->format('Y-m-d');
        $loc = $this->stockLocationId ?: null;

        $this->summaryRows = $service->categorySummaryForDate($d, $loc);

        if ($this->detailCategory && InventoryCategory::tryFrom($this->detailCategory)) {
            $this->detailLines = $service->linesForCategory($d, $this->detailCategory, $loc);
        } else {
            $this->detailLines = [];
        }
    }

    protected function requiresStockLocationSelection(): bool
    {
        return false;
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    protected function stockLocationsForPicker(): array
    {
        return StockLocation::active()->orderBy('name')->get(['id', 'name'])->toArray();
    }

    protected function auditScopeKey(): string
    {
        return $this->stockLocationId ? 'location:'.$this->stockLocationId : 'all';
    }

    protected function isSellingLocationReport(): bool
    {
        return false;
    }

    protected function recentApprovedReportsForView(): Collection
    {
        $hotel = Hotel::getHotel();
        if (! $this->auditEnabled || ! $hotel) {
            return collect();
        }

        return StockDailyReport::query()
            ->with(['approvedBy', 'verifiedBy', 'stockLocation'])
            ->where('hotel_id', $hotel->id)
            ->where('status', StockDailyReport::STATUS_APPROVED)
            ->orderByDesc('report_date')
            ->limit(14)
            ->get();
    }

    public function toggleAuditEnabled(bool $enabled): void
    {
        $user = Auth::user();
        $hotel = Hotel::getHotel();
        if (! $user || ! $hotel) {
            return;
        }
        if (! $this->canManageAuditConfig()) {
            session()->flash('error', 'You are not allowed to change audit workflow settings.');

            return;
        }

        $hotel->stock_daily_report_audit_enabled = $enabled;
        $hotel->save();

        ActivityLogger::log(
            'stock_daily_audit_config',
            $enabled ? 'Daily stock report audit workflow enabled.' : 'Daily stock report audit workflow disabled.',
            Hotel::class,
            $hotel->id,
            null,
            ['stock_daily_report_audit_enabled' => $enabled],
            ActivityLogModule::STOCK
        );

        $this->auditEnabled = $enabled;
        if (! $enabled) {
            $this->auditReport = null;
            $this->auditComments = [];
        } else {
            $this->loadAuditState();
        }

        session()->flash('message', 'Daily stock report audit workflow setting updated.');
    }

    public function submitForVerification(): void
    {
        if (! $this->auditEnabled || ! $this->canSubmitAudit()) {
            return;
        }

        $report = $this->resolveAuditReport(true);
        if (! $report) {
            session()->flash('error', $this->requiresStockLocationSelection()
                ? 'Select a selling location before using the audit workflow.'
                : 'Could not open daily report record.');

            return;
        }
        $report->status = StockDailyReport::STATUS_SUBMITTED;
        $report->prepared_by_id = $report->prepared_by_id ?: Auth::id();
        $report->submitted_by_id = Auth::id();
        $report->submitted_at = now();
        $report->save();

        if (trim($this->auditComment) !== '') {
            $this->createAuditComment($report, 'submitted', $this->auditComment);
        }

        ActivityLogger::log(
            'stock_daily_report_submit',
            'Daily category stock report submitted for verification ('.$this->auditScopeKey().', '.$this->reportDate.').',
            StockDailyReport::class,
            $report->id,
            null,
            ['scope_key' => $this->auditScopeKey(), 'report_date' => $this->reportDate],
            ActivityLogModule::STOCK
        );

        $this->auditComment = '';
        $this->loadAuditState();
        session()->flash('message', 'Report submitted for verification.');
    }

    public function verifyReport(): void
    {
        if (! $this->auditEnabled || ! $this->canVerifyAudit()) {
            return;
        }

        $report = $this->resolveAuditReport(true);
        if (! $report) {
            session()->flash('error', $this->requiresStockLocationSelection()
                ? 'Select a selling location before using the audit workflow.'
                : 'Could not open daily report record.');

            return;
        }
        if (! in_array($report->status, [StockDailyReport::STATUS_SUBMITTED, StockDailyReport::STATUS_REJECTED], true)) {
            session()->flash('error', 'Only submitted or corrected reports can be verified.');

            return;
        }

        $report->status = StockDailyReport::STATUS_VERIFIED;
        $report->verified_by_id = Auth::id();
        $report->verified_at = now();
        $report->rejected_by_id = null;
        $report->rejected_at = null;
        $report->rejection_reason = null;
        $report->save();

        if (trim($this->auditComment) !== '') {
            $this->createAuditComment($report, 'verified', $this->auditComment);
        }

        ActivityLogger::log(
            'stock_daily_report_verify',
            'Daily category stock report verified ('.$this->auditScopeKey().', '.$this->reportDate.').',
            StockDailyReport::class,
            $report->id,
            null,
            ['scope_key' => $this->auditScopeKey()],
            ActivityLogModule::STOCK
        );

        $this->auditComment = '';
        $this->loadAuditState();
        session()->flash('message', 'Report verified.');
    }

    public function approveReport(): void
    {
        if (! $this->auditEnabled || ! $this->canApproveAudit()) {
            return;
        }

        $report = $this->resolveAuditReport(true);
        if (! $report) {
            session()->flash('error', $this->requiresStockLocationSelection()
                ? 'Select a selling location before using the audit workflow.'
                : 'Could not open daily report record.');

            return;
        }
        if (! in_array($report->status, [StockDailyReport::STATUS_VERIFIED, StockDailyReport::STATUS_SUBMITTED], true)) {
            session()->flash('error', 'Only verified/submitted reports can be approved.');

            return;
        }

        $report->status = StockDailyReport::STATUS_APPROVED;
        $report->approved_by_id = Auth::id();
        $report->approved_at = now();
        $report->save();

        if (trim($this->auditComment) !== '') {
            $this->createAuditComment($report, 'approved', $this->auditComment);
        }

        ActivityLogger::log(
            'stock_daily_report_approve',
            'Daily category stock report approved ('.$this->auditScopeKey().', '.$this->reportDate.').',
            StockDailyReport::class,
            $report->id,
            null,
            ['scope_key' => $this->auditScopeKey()],
            ActivityLogModule::STOCK
        );

        $this->auditComment = '';
        $this->loadAuditState();
        session()->flash('message', 'Report approved.');
    }

    public function rejectReport(): void
    {
        if (! $this->auditEnabled || ! ($this->canVerifyAudit() || $this->canApproveAudit())) {
            return;
        }

        $reason = trim($this->rejectionReason);
        if ($reason === '') {
            session()->flash('error', 'Please provide a reason for rejection.');

            return;
        }

        $report = $this->resolveAuditReport(true);
        if (! $report) {
            session()->flash('error', $this->requiresStockLocationSelection()
                ? 'Select a selling location before using the audit workflow.'
                : 'Could not open daily report record.');
            $this->rejectionReason = '';

            return;
        }
        $report->status = StockDailyReport::STATUS_REJECTED;
        $report->rejected_by_id = Auth::id();
        $report->rejected_at = now();
        $report->rejection_reason = $reason;
        $report->save();

        $this->createAuditComment($report, 'rejected', $reason);
        ActivityLogger::log(
            'stock_daily_report_reject',
            'Daily category stock report rejected ('.$this->auditScopeKey().', '.$this->reportDate.').',
            StockDailyReport::class,
            $report->id,
            null,
            ['scope_key' => $this->auditScopeKey(), 'reason' => $reason],
            ActivityLogModule::STOCK
        );
        $this->rejectionReason = '';
        $this->loadAuditState();
        session()->flash('message', 'Report rejected and returned for correction.');
    }

    public function addAuditComment(): void
    {
        if (! $this->auditEnabled) {
            return;
        }
        $body = trim($this->auditComment);
        if ($body === '') {
            return;
        }
        $report = $this->resolveAuditReport(true);
        if (! $report) {
            return;
        }
        $this->createAuditComment($report, $report->status, $body);
        $this->auditComment = '';
        $this->loadAuditState();
    }

    protected function loadAuditState(): void
    {
        if (! $this->auditEnabled) {
            $this->auditReport = null;
            $this->auditComments = [];

            return;
        }

        $report = $this->resolveAuditReport(false);
        if (! $report) {
            $this->auditReport = null;
            $this->auditComments = [];

            return;
        }

        $this->auditReport = [
            'id' => $report->id,
            'status' => $report->status,
            'submitted_at' => optional($report->submitted_at)->format('Y-m-d H:i'),
            'verified_at' => optional($report->verified_at)->format('Y-m-d H:i'),
            'approved_at' => optional($report->approved_at)->format('Y-m-d H:i'),
            'prepared_by' => $report->preparedBy?->name,
            'submitted_by' => $report->submittedBy?->name,
            'verified_by' => $report->verifiedBy?->name,
            'approved_by' => $report->approvedBy?->name,
            'rejected_by' => $report->rejectedBy?->name,
            'rejection_reason' => $report->rejection_reason,
        ];

        $this->auditComments = $report->comments
            ->map(fn (StockDailyReportComment $comment) => [
                'user' => $comment->user?->name ?? 'System',
                'stage' => $comment->stage ?? 'note',
                'body' => $comment->body,
                'created_at' => optional($comment->created_at)->format('Y-m-d H:i'),
            ])
            ->all();
    }

    protected function resolveAuditReport(bool $createIfMissing): ?StockDailyReport
    {
        $hotel = Hotel::getHotel();
        if (! $hotel) {
            return null;
        }

        if ($this->requiresStockLocationSelection() && ! $this->stockLocationId) {
            return null;
        }

        $scopeKey = $this->auditScopeKey();
        $query = StockDailyReport::query()
            ->with(['preparedBy', 'submittedBy', 'verifiedBy', 'approvedBy', 'rejectedBy', 'comments.user'])
            ->where('hotel_id', $hotel->id)
            ->whereDate('report_date', Carbon::parse($this->reportDate)->format('Y-m-d'))
            ->where('scope_key', $scopeKey);

        $report = $query->first();
        if ($report || ! $createIfMissing) {
            return $report;
        }

        return StockDailyReport::create([
            'hotel_id' => $hotel->id,
            'report_date' => Carbon::parse($this->reportDate)->format('Y-m-d'),
            'stock_location_id' => $this->stockLocationId ?: null,
            'scope_key' => $scopeKey,
            'status' => StockDailyReport::STATUS_DRAFT,
            'prepared_by_id' => Auth::id(),
        ])->load(['preparedBy', 'submittedBy', 'verifiedBy', 'approvedBy', 'rejectedBy', 'comments.user']);
    }

    protected function createAuditComment(StockDailyReport $report, string $stage, string $body): void
    {
        StockDailyReportComment::create([
            'stock_daily_report_id' => $report->id,
            'user_id' => Auth::id(),
            'stage' => $stage,
            'body' => trim($body),
        ]);
    }

    protected function canManageAuditConfig(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        return $user->isSuperAdmin() || $user->canNavigateModules() || $user->hasPermission('hotel_configure_details');
    }

    protected function canSubmitAudit(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        return $user->canManageStockItems() || $user->isEffectiveStoreKeeper() || $user->hasPermission('stock_receive_goods');
    }

    protected function canVerifyAudit(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        return $user->hasPermission('stock_audit') || $user->hasPermission('reports_view_all') || $user->canNavigateModules();
    }

    protected function canApproveAudit(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        return $user->isSuperAdmin() || $user->isEffectiveGeneralManager() || $user->isEffectiveDirector() || $user->hasPermission('reports_view_all');
    }

    protected function reportViewData(): array
    {
        $hotel = Hotel::getHotel();

        return [
            'hotelName' => $hotel?->name ?? config('app.name'),
            'categoryLabels' => collect(InventoryCategory::ordered())->mapWithKeys(
                fn (InventoryCategory $c) => [$c->value => $c->label()]
            )->all(),
            'recentApprovedReports' => $this->recentApprovedReportsForView(),
            'canManageAuditConfig' => $this->canManageAuditConfig(),
            'canSubmitAudit' => $this->canSubmitAudit(),
            'canVerifyAudit' => $this->canVerifyAudit(),
            'canApproveAudit' => $this->canApproveAudit(),
            'isSellingLocationReport' => $this->isSellingLocationReport(),
        ];
    }

    public function render()
    {
        return view('livewire.stock-category-daily-report', $this->reportViewData())->layout('livewire.layouts.app-layout');
    }
}
