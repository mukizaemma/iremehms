<?php

namespace App\Models;

use App\Enums\MealPlan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reservation extends Model
{
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_CHECKED_IN = 'checked_in';
    public const STATUS_CHECKED_OUT = 'checked_out';
    public const STATUS_NO_SHOW = 'no_show';

    protected $fillable = [
        'hotel_id',
        'reservation_number',
        'guest_name',
        'booker_name',
        'guest_email',
        'guest_phone',
        'guest_country',
        'guest_address',
        'guest_id_number',
        'guest_profession',
        'guest_stay_purpose',
        'check_in_date',
        'check_out_date',
        'check_in_time',
        'check_out_time',
        'room_type_id',
        'rate_plan',
        'meal_plan',
        'room_rate_amount',
        'meal_plan_supplement',
        'breakfast_preferred_time',
        'lunch_preferred_time',
        'dinner_preferred_time',
        'breakfast_in_room',
        'lunch_in_room',
        'dinner_in_room',
        'meal_service_notes',
        'is_room_complimentary',
        'is_meal_complimentary',
        'complimentary_reason',
        'adult_count',
        'child_count',
        'total_amount',
        'paid_amount',
        'currency',
        'status',
        'booking_source',
        'reservation_type',
        'business_source',
        'business_source_detail',
        'group_name',
        'expected_guest_count',
    ];

    protected $casts = [
        'check_in_date' => 'date',
        'check_out_date' => 'date',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'room_rate_amount' => 'decimal:2',
        'meal_plan_supplement' => 'decimal:2',
        'breakfast_in_room' => 'boolean',
        'lunch_in_room' => 'boolean',
        'dinner_in_room' => 'boolean',
        'is_room_complimentary' => 'boolean',
        'is_meal_complimentary' => 'boolean',
    ];

    public function mealPlanEnum(): MealPlan
    {
        if ($this->meal_plan === MealPlan::COMP->value) {
            return MealPlan::BB;
        }

        return MealPlan::parse($this->meal_plan);
    }

    public function isRoomComplimentary(): bool
    {
        return (bool) $this->is_room_complimentary;
    }

    public function isMealComplimentary(): bool
    {
        return (bool) $this->is_meal_complimentary || $this->meal_plan === MealPlan::COMP->value;
    }

    public function hasComplimentaryService(): bool
    {
        return $this->isRoomComplimentary() || $this->isMealComplimentary();
    }

    public function complimentaryServicesLabel(): string
    {
        $room = $this->isRoomComplimentary();
        $meal = $this->isMealComplimentary();

        if ($room && $meal) {
            return 'Room & meals';
        }
        if ($room) {
            return 'Room only';
        }
        if ($meal) {
            return 'Meals only';
        }

        return '—';
    }

    public function mealIncludes(string $meal): bool
    {
        if ($this->isMealComplimentary()) {
            return true;
        }

        return match ($meal) {
            'breakfast' => $this->mealPlanEnum()->includesBreakfast(),
            'lunch' => $this->mealPlanEnum()->includesLunch(),
            'dinner' => $this->mealPlanEnum()->includesDinner(),
            default => false,
        };
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function roomUnits(): BelongsToMany
    {
        return $this->belongsToMany(RoomUnit::class, 'reservation_room_unit');
    }

    public function preRegistrations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PreRegistration::class);
    }

    public function guests(): HasMany
    {
        return $this->hasMany(ReservationGuest::class)->orderBy('sort_order');
    }

    /** POS/Restaurant invoices assigned to this reservation (room charge or otherwise linked). */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** Payments recorded against this reservation (partial payments supported). */
    public function reservationPayments(): HasMany
    {
        return $this->hasMany(ReservationPayment::class);
    }

    public function guestCommunications(): HasMany
    {
        return $this->hasMany(GuestCommunication::class)->orderByDesc('sent_at');
    }

    public function isPaid(): bool
    {
        $total = (float) ($this->total_amount ?? 0);
        $paid = (float) ($this->paid_amount ?? 0);
        return $total > 0 && $paid >= $total;
    }

    /**
     * Phone and email in one column for guests report (newline when both are present).
     */
    public function guestsReportPhoneEmailDisplay(): string
    {
        $phone = trim((string) ($this->guest_phone ?? ''));
        $email = trim((string) ($this->guest_email ?? ''));
        if ($phone === '' && $email === '') {
            return '—';
        }
        if ($phone !== '' && $email !== '') {
            return $phone."\n".$email;
        }

        return $phone !== '' ? $phone : $email;
    }

    /** Same data as one CSV cell (pipe separator). */
    public function guestsReportPhoneEmailCsv(): string
    {
        $phone = trim((string) ($this->guest_phone ?? ''));
        $email = trim((string) ($this->guest_email ?? ''));
        if ($phone === '' && $email === '') {
            return '—';
        }
        if ($phone !== '' && $email !== '') {
            return $phone.' | '.$email;
        }

        return $phone !== '' ? $phone : $email;
    }

    /** Generate a unique reservation number (date-based, shareable as reference). */
    public static function generateUniqueNumber(int $hotelId): string
    {
        $prefix = 'RES-' . date('Ymd') . '-';
        for ($i = 0; $i < 100; $i++) {
            $num = $prefix . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            if (! static::where('reservation_number', $num)->exists()) {
                return $num;
            }
        }
        return $prefix . uniqid('');
    }
}
