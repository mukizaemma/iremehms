<?php

namespace App\Livewire\FrontOffice;

use App\Mail\ProformaInvoiceMail;
use App\Models\Hotel;
use App\Models\HotelProformaLineDefault;
use App\Models\ProformaInvoice;
use App\Models\ProformaInvoiceLine;
use App\Models\ProformaInvoicePayment;
use App\Models\WellnessService;
use App\Models\User;
use App\Notifications\ProformaApprovalRequestNotification;
use App\Services\HotelRevenueReportColumnService;
use App\Services\ProformaInvoiceNumberService;
use App\Support\GeneralReportPosBuckets;
use App\Support\PaymentCatalog;
use App\Support\ProformaCatalog;
use App\Support\ProformaInvoicePermissions;
use App\Traits\ChecksModuleStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Component;

class ProformaInvoiceEdit extends Component
{
    use ChecksModuleStatus;

    public ?int $proformaId = null;

    public string $proforma_number = '';

    public string $status = 'draft';

    public string $client_organization = '';

    public string $client_name = '';

    public string $client_email = '';

    public string $client_phone = '';

    public string $event_title = '';

    public ?string $service_start_date = null;

    public ?string $service_end_date = null;

    public string $notes = '';

    public string $payment_terms = '';

    public string $currency = 'RWF';

    public string $discount_amount = '0';

    public string $tax_amount = '0';

    public ?int $reservation_id = null;

    public string $manager_reject_note = '';

    public bool $showRejectForm = false;

    /** @var array<int, array<string, mixed>> */
    public array $lines = [];

    public string $pay_amount = '';

    public string $pay_received_at = '';

    public string $pay_method = '';

    public string $pay_bucket = 'other';

    public string $pay_reference = '';

    public string $pay_notes = '';

    public function mount(?ProformaInvoice $proformaInvoice = null): void
    {
        session(['selected_module' => 'front-office']);
        $this->ensureModuleEnabled('front-office');
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('fo_proforma_manage')) {
            abort(403, 'You do not have permission to manage proforma invoices.');
        }
        $hotel = Hotel::getHotel();
        if (! $hotel) {
            abort(403, 'Hotel context required.');
        }

        $this->pay_method = PaymentCatalog::METHOD_CASH;
        $tz = $hotel->getTimezone();
        $this->pay_received_at = now($tz)->format('Y-m-d\TH:i');

        if ($proformaInvoice) {
            if ($proformaInvoice->hotel_id !== $hotel->id) {
                abort(403);
            }
            $this->proformaId = $proformaInvoice->id;
            $this->loadFromModel($proformaInvoice);
        } else {
            $this->proforma_number = ProformaInvoiceNumberService::next($hotel);
            $this->lines = [$this->blankLine()];
        }
    }

    public function userCanEdit(): bool
    {
        if (in_array($this->status, ['invoiced', 'cancelled'], true)) {
            return false;
        }
        $u = Auth::user();
        if (ProformaInvoicePermissions::canVerifyProforma($u)) {
            return true;
        }

        return in_array($this->status, ['draft', 'rejected'], true);
    }


    protected function configuredBucketForLine(string $lineType, ?int $wellnessServiceId = null): string
    {
        $hotel = Hotel::getHotel();
        if (! $hotel) {
            return ProformaCatalog::defaultBucketForLineType()[$lineType] ?? 'other';
        }

        if ($lineType === 'wellness' && $wellnessServiceId) {
            $svc = WellnessService::query()->where('hotel_id', $hotel->id)->whereKey($wellnessServiceId)->first();
            if ($svc && $svc->report_bucket_key) {
                return (string) $svc->report_bucket_key;
            }
        }

        $cfg = HotelProformaLineDefault::query()
            ->where('hotel_id', $hotel->id)
            ->where('line_type', $lineType)
            ->value('report_bucket_key');

        if ($cfg && in_array((string) $cfg, GeneralReportPosBuckets::COLUMN_KEYS, true)) {
            return (string) $cfg;
        }

        $map = ProformaCatalog::defaultBucketForLineType();

        return $map[$lineType] ?? 'other';
    }

    protected function dominantPaymentBucket(): string
    {
        $hotel = Hotel::getHotel();
        if (! $hotel) {
            return 'other';
        }

        if ($this->proformaId) {
            $rows = ProformaInvoiceLine::query()
                ->where('proforma_invoice_id', $this->proformaId)
                ->selectRaw('report_bucket_key, SUM(line_total) as total')
                ->groupBy('report_bucket_key')
                ->orderByDesc('total')
                ->get();
            if ($rows->isNotEmpty()) {
                return (string) ($rows->first()->report_bucket_key ?? 'other');
            }
        }

        if ($this->lines !== []) {
            $first = $this->lines[0] ?? [];
            $lineType = (string) ($first['line_type'] ?? 'other');
            $widRaw = $first['wellness_service_id'] ?? null;
            $wid = ($widRaw === '' || $widRaw === null) ? null : (int) $widRaw;

            return $this->configuredBucketForLine($lineType, $wid);
        }

        return 'other';
    }

    /**
     * @return array<string, mixed>
     */
    protected function blankLine(): array
    {
        return [
            'line_type' => 'custom',
            'description' => '',
            'report_bucket_key' => 'other',
            'quantity' => '1',
            'unit_price' => '0',
            'discount_percent' => '0',
            'wellness_service_id' => '',
            'wellness_pricing_mode' => 'visit',
        ];
    }

    protected function loadFromModel(ProformaInvoice $p): void
    {
        $this->proforma_number = $p->proforma_number;
        $this->status = $p->status;
        $this->client_organization = (string) ($p->client_organization ?? '');
        $this->client_name = $p->client_name;
        $this->client_email = (string) ($p->client_email ?? '');
        $this->client_phone = (string) ($p->client_phone ?? '');
        $this->event_title = (string) ($p->event_title ?? '');
        $this->service_start_date = $p->service_start_date ? \Carbon\Carbon::parse($p->service_start_date)->format('Y-m-d') : null;
        $this->service_end_date = $p->service_end_date ? \Carbon\Carbon::parse($p->service_end_date)->format('Y-m-d') : null;
        $this->notes = (string) ($p->notes ?? '');
        $this->payment_terms = (string) ($p->payment_terms ?? '');
        $this->currency = $p->currency ?: 'RWF';
        $this->discount_amount = (string) $p->discount_amount;
        $this->tax_amount = (string) $p->tax_amount;
        $this->reservation_id = $p->reservation_id;

        $this->lines = $p->lines->map(function (ProformaInvoiceLine $l) {
            return [
                'line_type' => $l->line_type,
                'description' => $l->description,
                'report_bucket_key' => $l->report_bucket_key,
                'quantity' => (string) $l->quantity,
                'unit_price' => (string) $l->unit_price,
                'discount_percent' => (string) $l->discount_percent,
                'wellness_service_id' => $l->wellness_service_id ? (string) $l->wellness_service_id : '',
                'wellness_pricing_mode' => $l->wellness_pricing_mode ?: 'visit',
            ];
        })->values()->all();
        if ($this->lines === []) {
            $this->lines = [$this->blankLine()];
        }
    }

    public function addLine(): void
    {
        if (! $this->userCanEdit()) {
            return;
        }
        $this->lines[] = $this->blankLine();
    }

    public function removeLine(int $index): void
    {
        if (! $this->userCanEdit()) {
            return;
        }
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
        if ($this->lines === []) {
            $this->lines[] = $this->blankLine();
        }
    }



    public function updateLine(int $index): void
    {
        if (! $this->userCanEdit() || ! isset($this->lines[$index])) {
            return;
        }

        $this->validate([
            "lines.$index.quantity" => 'required|numeric|min:0',
            "lines.$index.unit_price" => 'required|numeric|min:0',
            "lines.$index.discount_percent" => 'nullable|numeric|min:0|max:100',
            "lines.$index.description" => 'nullable|string|max:500',
        ]);

        $this->lines[$index]['description'] = trim((string) ($this->lines[$index]['description'] ?? ''));
        $this->lines[$index]['quantity'] = (string) ((float) ($this->lines[$index]['quantity'] ?? 0));
        $this->lines[$index]['unit_price'] = (string) ((float) ($this->lines[$index]['unit_price'] ?? 0));
        $this->lines[$index]['discount_percent'] = (string) ((float) ($this->lines[$index]['discount_percent'] ?? 0));

        if (($this->lines[$index]['line_type'] ?? '') === 'wellness') {
            $this->applyWellnessUnitPrice($index);
        }

        session()->flash('message', 'Row updated. Save draft to persist all changes.');
    }
    public function applyLineTypeDefaults(int $index): void
    {
        if (! isset($this->lines[$index])) {
            return;
        }
        $type = $this->lines[$index]['line_type'] ?? 'custom';
        $widRaw = $this->lines[$index]['wellness_service_id'] ?? null;
        $wid = ($widRaw === '' || $widRaw === null) ? null : (int) $widRaw;
        $this->lines[$index]['report_bucket_key'] = $this->configuredBucketForLine($type, $wid);

        $hotel = Hotel::getHotel();
        if ($hotel) {
            $def = HotelProformaLineDefault::query()
                ->where('hotel_id', $hotel->id)
                ->where('line_type', $type)
                ->value('default_unit_price');
            if ($def !== null && (float) $def >= 0) {
                $this->lines[$index]['unit_price'] = (string) $def;
            }
        }

        if ($type !== 'wellness') {
            $this->lines[$index]['wellness_service_id'] = '';
            $this->lines[$index]['wellness_pricing_mode'] = 'visit';
        } else {
            $this->applyWellnessUnitPrice($index);
        }
    }

    public function applyWellnessUnitPrice(int $index): void
    {
        if (! isset($this->lines[$index])) {
            return;
        }
        if (($this->lines[$index]['line_type'] ?? '') !== 'wellness') {
            return;
        }
        $sid = $this->lines[$index]['wellness_service_id'] ?? null;
        if ($sid === '' || $sid === null) {
            return;
        }
        $hotel = Hotel::getHotel();
        if (! $hotel) {
            return;
        }
        $svc = WellnessService::query()->where('hotel_id', $hotel->id)->whereKey($sid)->first();
        if (! $svc) {
            return;
        }
        $mode = (string) ($this->lines[$index]['wellness_pricing_mode'] ?? 'visit');
        $price = match ($mode) {
            'daily' => $svc->price_per_day !== null ? $svc->price_per_day : $svc->default_price,
            'monthly' => $svc->price_monthly_subscription !== null ? $svc->price_monthly_subscription : $svc->default_price,
            default => $svc->default_price,
        };
        $this->lines[$index]['unit_price'] = (string) ($price ?? 0);
    }

    public function updated($name): void
    {
        if (preg_match('/^lines\.(\d+)\.line_type$/', (string) $name, $m)) {
            $this->applyLineTypeDefaults((int) $m[1]);
        }
        if (preg_match('/^lines\.(\d+)\.wellness_service_id$/', (string) $name, $m)
            || preg_match('/^lines\.(\d+)\.wellness_pricing_mode$/', (string) $name, $m)) {
            $this->applyWellnessUnitPrice((int) $m[1]);
        }
    }

    /**
     * @return array{0: float, 1: float, 2: float}
     */
    protected function computeTotals(): array
    {
        $sub = 0.0;
        foreach ($this->lines as $line) {
            $qty = (float) ($line['quantity'] ?? 0);
            $unit = (float) ($line['unit_price'] ?? 0);
            $disc = (float) ($line['discount_percent'] ?? 0);
            $lineTotal = $qty * $unit * (1 - min(100.0, max(0.0, $disc)) / 100.0);
            $sub += round($lineTotal, 2);
        }
        $discH = (float) $this->discount_amount;
        $tax = (float) $this->tax_amount;
        $grand = max(0.0, round($sub - $discH + $tax, 2));

        return [$sub, $discH, $grand];
    }

    public function saveDraft(): void
    {
        if (! $this->userCanEdit()) {
            abort(403);
        }
        if (! ProformaInvoicePermissions::canVerifyProforma(Auth::user())) {
            $this->status = 'draft';
        }
        $this->persistProforma();
        session()->flash('message', 'Draft saved.');
        $this->redirect(route('front-office.proforma-invoices.edit', $this->proformaId));
    }

    public function submitToManager(): void
    {
        if (! in_array($this->status, ['draft', 'rejected'], true)) {
            session()->flash('error', 'Only draft or rejected proformas can be submitted for verification.');

            return;
        }
        if (ProformaInvoicePermissions::canVerifyProforma(Auth::user())) {
            session()->flash('error', 'Use Save draft as manager instead of submit.');

            return;
        }
        $this->status = 'pending_manager';
        $this->persistProforma();
        $p = ProformaInvoice::query()->whereKey($this->proformaId)->first();
        if ($p) {
            $p->submitted_to_manager_at = now();
            $p->manager_rejected_at = null;
            $p->manager_rejection_note = null;
            $p->save();

            $recipients = User::query()
                ->where('hotel_id', $hotel->id)
                ->get()
                ->filter(fn (User $u) => $u->id !== Auth::id() && ProformaInvoicePermissions::canVerifyProforma($u));
            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new ProformaApprovalRequestNotification($p));
            }
        }
        session()->flash('message', 'Submitted to manager for verification.');
        $this->redirect(route('front-office.proforma-invoices.edit', $this->proformaId));
    }

    public function verifyByManager(): void
    {
        if (! ProformaInvoicePermissions::canVerifyProforma(Auth::user())) {
            abort(403);
        }
        if ($this->status !== 'pending_manager') {
            session()->flash('error', 'Only pending submissions can be verified.');

            return;
        }
        $this->status = 'verified';
        $this->showRejectForm = false;
        $this->persistProforma();
        $p = ProformaInvoice::query()->whereKey($this->proformaId)->first();
        if ($p) {
            $p->manager_verified_at = now();
            $p->manager_verified_by = Auth::id();
            $p->manager_rejected_at = null;
            $p->manager_rejection_note = null;
            $p->save();
        }
        session()->flash('message', 'Proforma verified.');
        $this->redirect(route('front-office.proforma-invoices.edit', $this->proformaId));
    }

    public function rejectByManager(): void
    {
        if (! ProformaInvoicePermissions::canVerifyProforma(Auth::user())) {
            abort(403);
        }
        if ($this->status !== 'pending_manager') {
            session()->flash('error', 'Only pending submissions can be rejected.');

            return;
        }
        $this->validate(['manager_reject_note' => 'required|string|max:2000']);
        $this->status = 'rejected';
        $this->persistProforma();
        $p = ProformaInvoice::query()->whereKey($this->proformaId)->first();
        if ($p) {
            $p->manager_rejected_at = now();
            $p->manager_rejection_note = $this->manager_reject_note;
            $p->save();
        }
        $this->showRejectForm = false;
        session()->flash('message', 'Returned to staff with feedback.');
        $this->redirect(route('front-office.proforma-invoices.edit', $this->proformaId));
    }

    public function sendEmail(): void
    {
        $hotel = Hotel::getHotel();
        if (! $this->proformaId || ! $hotel) {
            session()->flash('error', 'Save the proforma first.');

            return;
        }
        if (! in_array($this->status, ['verified', 'sent', 'accepted'], true)) {
            session()->flash('error', 'Verify the proforma before emailing the client.');

            return;
        }
        $this->validate(['client_email' => 'required|email']);
        if ($this->userCanEdit()) {
            $this->persistProforma();
        }
        $p = ProformaInvoice::query()->where('hotel_id', $hotel->id)->whereKey($this->proformaId)->firstOrFail();
        $to = $p->client_email;
        if (! $to) {
            session()->flash('error', 'Client email is missing.');

            return;
        }
        Mail::to($to)->send(new ProformaInvoiceMail($p->fresh(['lines', 'hotel'])));
        $p->sent_at = now();
        if ($p->status === 'verified') {
            $p->status = 'sent';
        }
        $p->save();
        $this->status = $p->status;
        session()->flash('message', 'Email sent to '.$to.'.');
    }

    protected function persistProforma(): void
    {
        $hotel = Hotel::getHotel();
        if (! $hotel) {
            return;
        }

        $this->validate([
            'client_name' => 'required|string|max:190',
            'client_organization' => 'nullable|string|max:190',
            'client_email' => 'nullable|email|max:190',
            'client_phone' => 'nullable|string|max:64',
            'event_title' => 'nullable|string|max:190',
            'service_start_date' => 'nullable|date',
            'service_end_date' => 'nullable|date',
            'currency' => 'required|string|max:8',
            'discount_amount' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:5000',
            'payment_terms' => 'nullable|string|max:5000',
            'lines' => 'required|array|min:1',
            'lines.*.line_type' => 'required|string|max:40',
            'lines.*.description' => 'nullable|string|max:500',
            'lines.*.quantity' => 'required|numeric|min:0',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.discount_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        [$subtotal, $discH, $grand] = $this->computeTotals();
        $userId = Auth::id();

        DB::transaction(function () use ($hotel, $subtotal, $discH, $grand, $userId) {
            if ($this->proformaId) {
                $p = ProformaInvoice::query()->where('hotel_id', $hotel->id)->whereKey($this->proformaId)->firstOrFail();
            } else {
                $p = new ProformaInvoice(['hotel_id' => $hotel->id, 'created_by' => $userId]);
                $p->proforma_number = $this->proforma_number;
            }

            $p->fill([
                'status' => $this->status,
                'client_organization' => $this->client_organization ?: null,
                'client_name' => $this->client_name,
                'client_email' => $this->client_email ?: null,
                'client_phone' => $this->client_phone ?: null,
                'event_title' => $this->event_title ?: null,
                'service_start_date' => $this->service_start_date ?: null,
                'service_end_date' => $this->service_end_date ?: null,
                'notes' => $this->notes ?: null,
                'payment_terms' => $this->payment_terms ?: null,
                'currency' => $this->currency,
                'subtotal' => $subtotal,
                'discount_amount' => $discH,
                'tax_amount' => (float) $this->tax_amount,
                'grand_total' => $grand,
                'reservation_id' => $this->reservation_id,
            ]);
            $p->save();

            $p->lines()->delete();
            $order = 0;
            foreach ($this->lines as $line) {
                $qty = (float) ($line['quantity'] ?? 0);
                $unit = (float) ($line['unit_price'] ?? 0);
                $disc = (float) ($line['discount_percent'] ?? 0);
                $lineTotal = round($qty * $unit * (1 - min(100.0, max(0.0, $disc)) / 100.0), 2);
                $wellnessId = $line['wellness_service_id'] ?? null;
                if ($wellnessId === '' || $wellnessId === false || $wellnessId === null) {
                    $wellnessId = null;
                } else {
                    $wellnessId = (int) $wellnessId;
                }
                $lineType = (string) ($line['line_type'] ?? 'custom');
                $wMode = ($lineType === 'wellness') ? ($line['wellness_pricing_mode'] ?? null) : null;
                $bucket = $this->configuredBucketForLine($lineType, $wellnessId);

                ProformaInvoiceLine::create([
                    'proforma_invoice_id' => $p->id,
                    'sort_order' => $order++,
                    'line_type' => $lineType,
                    'description' => (string) ($line['description'] ?? ''),
                    'report_bucket_key' => $bucket,
                    'quantity' => $qty,
                    'unit_price' => $unit,
                    'discount_percent' => $disc,
                    'line_total' => $lineTotal,
                    'wellness_service_id' => $wellnessId ? (int) $wellnessId : null,
                    'wellness_pricing_mode' => $wMode,
                ]);
            }

            $this->proformaId = $p->id;
        });
    }

    public function markSent(): void
    {
        $this->updateStatus('sent', ['sent_at' => now()]);
    }

    public function markAccepted(): void
    {
        $this->updateStatus('accepted', ['accepted_at' => now()]);
    }

    public function markInvoiced(): void
    {
        $this->updateStatus('invoiced', ['invoiced_at' => now()]);
    }

    public function markCancelled(): void
    {
        $this->updateStatus('cancelled', []);
    }

    protected function updateStatus(string $status, array $extra): void
    {
        $hotel = Hotel::getHotel();
        if (! $this->proformaId || ! $hotel) {
            session()->flash('error', 'Save the proforma first.');

            return;
        }
        $p = ProformaInvoice::query()->where('hotel_id', $hotel->id)->whereKey($this->proformaId)->first();
        if (! $p) {
            return;
        }
        $p->status = $status;
        foreach ($extra as $k => $v) {
            $p->{$k} = $v;
        }
        $p->save();
        $this->status = $status;
        session()->flash('message', 'Status updated.');
    }

    public function recordPayment(): void
    {
        $hotel = Hotel::getHotel();
        if (! $this->proformaId || ! $hotel) {
            session()->flash('error', 'Save the proforma first.');

            return;
        }

        $this->validate([
            'pay_amount' => 'required|numeric|min:0.01',
            'pay_received_at' => 'required|date',
            'pay_method' => 'required|string|max:40',
            'pay_reference' => 'nullable|string|max:190',
            'pay_notes' => 'nullable|string|max:500',
        ]);

        $bucket = $this->dominantPaymentBucket();

        ProformaInvoicePayment::create([
            'hotel_id' => $hotel->id,
            'proforma_invoice_id' => $this->proformaId,
            'amount' => (float) $this->pay_amount,
            'received_at' => Carbon::parse($this->pay_received_at),
            'payment_method' => PaymentCatalog::normalizeUnifiedChoice($this->pay_method),
            'reference' => $this->pay_reference ?: null,
            'report_bucket_key' => $bucket,
            'notes' => $this->pay_notes ?: null,
            'recorded_by' => Auth::id(),
        ]);

        $this->pay_amount = '';
        $this->pay_reference = '';
        $this->pay_notes = '';
        session()->flash('message', 'Payment recorded. It will appear on the general report for the payment date.');
    }

    public function render()
    {
        $hotel = Hotel::getHotel();
        $preview = $this->computeTotals();
        $payments = collect();
        if ($this->proformaId && $hotel) {
            $payments = ProformaInvoicePayment::query()
                ->where('proforma_invoice_id', $this->proformaId)
                ->orderByDesc('received_at')
                ->get();
        }

        $defs = HotelRevenueReportColumnService::defaultDefinitions();
        $reportBucketOptions = [];
        foreach (GeneralReportPosBuckets::COLUMN_KEYS as $k) {
            $reportBucketOptions[$k] = $defs[$k] ?? $k;
        }

        $wellnessServices = $hotel
            ? WellnessService::query()->where('hotel_id', $hotel->id)->where('is_active', true)->orderBy('name')->get()
            : collect();

        $u = Auth::user();
        $autoBucket = $this->dominantPaymentBucket();

        return view('livewire.front-office.proforma-invoice-edit', [
            'subtotalPreview' => $preview[0],
            'grandPreview' => $preview[2],
            'lineTypes' => ProformaCatalog::lineTypes(),
            'reportBucketOptions' => $reportBucketOptions,
            'paymentMethods' => PaymentCatalog::unifiedAccommodationOptions(),
            'payments' => $payments,
            'wellnessServices' => $wellnessServices,
            'canEdit' => $this->userCanEdit(),
            'canVerify' => $u ? ProformaInvoicePermissions::canVerifyProforma($u) : false,
            'printUrl' => $this->proformaId && $hotel
                ? route('front-office.proforma-invoices.print', ['proformaInvoice' => $this->proformaId])
                : null,
            'defaultsSettingsUrl' => route('front-office.proforma-line-defaults'),
            'autoPaymentBucket' => $autoBucket,
            'autoPaymentBucketLabel' => $reportBucketOptions[$autoBucket] ?? strtoupper($autoBucket),
        ])->layout('livewire.layouts.app-layout');
    }
}
