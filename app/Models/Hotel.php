<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Hotel extends Model
{
    use HasFactory;

    /**
     * Department slugs that map to a row in `departments` (aligned with enabled modules).
     *
     * @see self::getDepartmentIdsForAssignments()
     */
    private const DEPARTMENT_MODULE_SLUGS = [
        'front-office',
        'back-office',
        'restaurant',
        'store',
        'recovery',
        'housekeeping',
    ];

    protected $fillable = [
        'name',
        'hotel_code',
        'subscription_type',
        'subscription_status',
        'subscription_amount',
        'subscription_start_date',
        'next_due_date',
        'contact',
        'fax',
        'email',
        'address',
        'hotel_type',
        'check_in_time',
        'check_out_time',
        'hotel_information',
        'landmarks_nearby',
        'facilities',
        'check_in_policy',
        'children_extra_guest_details',
        'parking_policy',
        'things_to_do',
        'map_embed_code',
        'public_slug',
        'public_booking_domain',
        'reservation_contacts',
        'reservation_phone',
        'logo',
        'login_background_image',
        'primary_color',
        'secondary_color',
        'font_family',
        'currency',
        'charge_level', // 'room_type' or 'room' - whether rates are per room type or per room
        'enabled_modules',
        'enabled_departments',
        'business_day_rollover_time',
        'shifts_enabled',
        'shift_mode',
        'operational_shift_scope',
        'auto_close_previous_business_day',
        'allow_manual_close_business_day',
        'pos_enforce_stock_on_payment',
        'pos_payment_flow',
        'receipt_show_vat',
        'reports_show_vat',
        'stock_daily_report_audit_enabled',
        'receipt_thank_you_text',
        'receipt_momo_label',
        'receipt_momo_value',
        'order_slip_hide_price',
        'timezone',
        'has_multiple_wings',
        'total_floors',
        'use_bom_for_menu_items',
        'guests_report_signature_prepared_default',
        'guests_report_signature_verified_default',
        'guests_report_signature_approved_default',
    ];

    protected $casts = [
        'subscription_start_date' => 'date',
        'next_due_date' => 'date',
        'has_multiple_wings' => 'boolean',
        'total_floors' => 'integer',
        'enabled_modules' => 'array',
        'enabled_departments' => 'array',
        'business_day_rollover_time' => 'datetime:H:i:s',
        'shifts_enabled' => 'boolean',
        'auto_close_previous_business_day' => 'boolean',
        'allow_manual_close_business_day' => 'boolean',
        'pos_enforce_stock_on_payment' => 'boolean',
        'order_slip_hide_price' => 'boolean',
        'receipt_show_vat' => 'boolean',
        'reports_show_vat' => 'boolean',
        'stock_daily_report_audit_enabled' => 'boolean',
        'use_bom_for_menu_items' => 'boolean',
    ];

    /** VAT line items on front-office accommodation reports (totals still computed internally). */
    public function showsVatOnReports(): bool
    {
        return (bool) ($this->reports_show_vat ?? false);
    }

    public function isStockDailyReportAuditEnabled(): bool
    {
        return (bool) ($this->stock_daily_report_audit_enabled ?? false);
    }

    /** VAT breakdown on POS/guest receipts. */
    public function showsVatOnReceipts(): bool
    {
        return (bool) ($this->receipt_show_vat ?? false);
    }

    /**
     * Whether the Back Office “Subscription” shortcut applies (monthly / recurring billing, not one-time only).
     */
    public function shouldShowSubscriptionHubTile(): bool
    {
        $type = $this->subscription_type;
        if ($type === null || $type === '') {
            return true;
        }
        if ($type === 'one_time') {
            return false;
        }

        return in_array($type, ['monthly', 'recurring', 'yearly'], true);
    }

    /**
     * Whether POS payment requires sufficient stock (block payment if insufficient).
     * When false, payment is allowed and shortfalls are recorded for later reconciliation.
     */
    public function enforcesPosStockOnPayment(): bool
    {
        return (bool) ($this->pos_enforce_stock_on_payment ?? true);
    }

    /**
     * Whether POS requires an open shift (STRICT_SHIFT)
     */
    public function isStrictShiftMode(): bool
    {
        return ($this->shift_mode ?? 'STRICT_SHIFT') === 'STRICT_SHIFT';
    }

    /**
     * Whether shifts are completely disabled (NO_SHIFT)
     */
    public function isNoShiftMode(): bool
    {
        return ($this->shift_mode ?? 'STRICT_SHIFT') === 'NO_SHIFT';
    }

    /**
     * Whether shifts are optional (OPTIONAL_SHIFT)
     */
    public function isOptionalShiftMode(): bool
    {
        return ($this->shift_mode ?? 'STRICT_SHIFT') === 'OPTIONAL_SHIFT';
    }

    /**
     * One operational shift for the whole hotel (POS + FO + store) vs per-module shifts.
     */
    public function isGlobalOperationalShiftScope(): bool
    {
        return ($this->operational_shift_scope ?? 'per_module') === 'global';
    }

    /**
     * Get hotel timezone for business day and display (e.g. Africa/Kigali).
     * Used so business day and POS times follow local time even if server is elsewhere.
     */
    public function getTimezone(): string
    {
        $tz = $this->timezone ?? config('app.timezone', 'UTC');

        return is_string($tz) ? $tz : 'UTC';
    }

    /**
     * Current date (Y-m-d) in the hotel's timezone.
     * Use this for all room status, occupancy and reporting so Dashboard and Room Status stay in sync across roles.
     */
    public function getTodayYmd(): string
    {
        return \Carbon\Carbon::now($this->getTimezone())->format('Y-m-d');
    }

    /**
     * Same as getTodayYmd() for the current hotel (convenience when you already have getHotel()).
     */
    public static function getTodayForHotel(): string
    {
        $hotel = static::getHotel();

        return $hotel ? $hotel->getTodayYmd() : \Carbon\Carbon::now()->format('Y-m-d');
    }

    /**
     * Get the single hotel instance for the current tenant (auth user's hotel).
     * Ireme users (hotel_id null) should not call this in hotel context; hotel routes are protected by middleware.
     */
    public static function getHotel(): ?self
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }
        if ($user->hotel_id) {
            return static::find($user->hotel_id);
        }
        // Ireme user: optional "view as hotel" from session (for future use)
        $hotelId = session('current_hotel_id');

        return $hotelId ? static::find($hotelId) : null;
    }

    /**
     * Generate next available hotel code in range 100-999.
     */
    public static function generateHotelCode(): int
    {
        $max = static::max('hotel_code');
        $next = ($max ? (int) $max + 1 : 100);

        return min(max($next, 100), 999);
    }

    /**
     * Modules enabled for this hotel (pivot).
     */
    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'hotel_module');
    }

    /**
     * Configurable revenue lines for the general / daily / monthly sales reports.
     */
    public function revenueReportLines(): HasMany
    {
        return $this->hasMany(HotelRevenueReportLine::class)->orderBy('sort_order');
    }

    public function proformaInvoices(): HasMany
    {
        return $this->hasMany(ProformaInvoice::class)->orderByDesc('created_at');
    }

    public function wellnessServices(): HasMany
    {
        return $this->hasMany(WellnessService::class)->orderBy('sort_order');
    }

    public function wings(): HasMany
    {
        return $this->hasMany(HotelWing::class)->orderBy('sort_order')->orderBy('name');
    }

    public function proformaLineDefaults(): HasMany
    {
        return $this->hasMany(HotelProformaLineDefault::class);
    }

    /**
     * Enabled module IDs for this hotel.
     *
     * Uses both `hotel_module` (Ireme / onboarding) and `enabled_modules` JSON (System configuration).
     * When both are set they can drift; we take the intersection so only modules agreed by both remain.
     * If that intersection is empty but both lists exist (ID mismatch), we prefer `enabled_modules` (hotel admin).
     */
    public function getEnabledModuleIds(): array
    {
        if ($this->relationLoaded('modules')) {
            $pivotIds = $this->modules->pluck('id')->map(fn ($id) => (int) $id)->all();
        } else {
            $pivotIds = $this->modules()->pluck('modules.id')->map(fn ($id) => (int) $id)->all();
        }

        $jsonIds = array_values(array_unique(array_map('intval', array_filter($this->enabled_modules ?? []))));

        if ($pivotIds !== [] && $jsonIds !== []) {
            $ids = array_values(array_intersect($pivotIds, $jsonIds));
            if ($ids === []) {
                $ids = $jsonIds;
            }
        } elseif ($pivotIds !== []) {
            $ids = $pivotIds;
        } else {
            $ids = $jsonIds;
        }

        if ($ids === []) {
            return [];
        }

        return Module::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->orderBy('order')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Department IDs that apply to this hotel: `enabled_departments` plus departments implied by enabled modules.
     */
    public function getDepartmentIdsForAssignments(): array
    {
        $ids = [];

        $enabledJson = $this->enabled_departments;
        if (is_array($enabledJson) && count($enabledJson) > 0) {
            $ids = array_merge($ids, array_map('intval', $enabledJson));
        }

        $moduleIds = $this->getEnabledModuleIds();
        if (! empty($moduleIds)) {
            $slugs = Module::whereIn('id', $moduleIds)->pluck('slug')->all();
            $mapped = array_values(array_intersect($slugs, self::DEPARTMENT_MODULE_SLUGS));
            if ($mapped !== []) {
                $fromModules = Department::whereIn('slug', $mapped)->pluck('id')->all();
                $ids = array_merge($ids, $fromModules);
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Get enabled department IDs
     */
    public function getEnabledDepartmentIds(): array
    {
        return $this->enabled_departments ?? [];
    }

    /**
     * Get system currency
     */
    public function getCurrency(): string
    {
        return $this->currency ?? 'RWF';
    }

    /**
     * Get system currency symbol
     */
    public function getCurrencySymbol(): string
    {
        $symbols = [
            'RWF' => 'RWF',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'KES' => 'KES',
            'UGX' => 'UGX',
            'TZS' => 'TZS',
            'ETB' => 'ETB',
        ];

        return $symbols[$this->getCurrency()] ?? $this->getCurrency();
    }

    /**
     * Get full URL for the login background image, if set
     */
    public function getLoginBackgroundImageUrl(): ?string
    {
        if (! $this->login_background_image) {
            return null;
        }

        return \Illuminate\Support\Facades\Storage::url($this->login_background_image);
    }

    public function amenities(): HasMany
    {
        return $this->hasMany(Amenity::class)->orderBy('sort_order');
    }

    public function galleryImages(): HasMany
    {
        return $this->hasMany(HotelGalleryImage::class)->orderBy('sort_order');
    }

    public function videoTours(): HasMany
    {
        return $this->hasMany(HotelVideoTour::class)->orderBy('sort_order');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(HotelReview::class)->orderByDesc('created_at');
    }

    public function approvedReviews(): HasMany
    {
        return $this->hasMany(HotelReview::class)->where('is_approved', true)->orderByDesc('created_at');
    }

    public function subscriptionInvoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class)->orderByDesc('due_date');
    }

    public function supportRequests(): HasMany
    {
        return $this->hasMany(SupportRequest::class)->orderByDesc('created_at');
    }
}
