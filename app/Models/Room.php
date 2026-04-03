<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotel_id',
        'room_type_id',
        'wing_id',
        'room_number',
        'name',
        'floor',
        'description',
        'is_active',
        'pending_deletion',
        'deletion_requested_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'pending_deletion' => 'boolean',
        'deletion_requested_at' => 'datetime',
    ];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function wing(): BelongsTo
    {
        return $this->belongsTo(HotelWing::class, 'wing_id');
    }

    public function roomUnits(): HasMany
    {
        return $this->hasMany(RoomUnit::class)->orderBy('sort_order');
    }

    public function images(): HasMany
    {
        return $this->hasMany(RoomImage::class)->orderBy('sort_order');
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class, 'amenity_room');
    }

    public function rates(): HasMany
    {
        return $this->hasMany(RoomRate::class)->orderBy('rate_type');
    }

    /** Get the configured amount for a rate type, or null if not set. */
    public function getAmountForRateType(string $rateType): ?float
    {
        $r = $this->rates()->where('rate_type', $rateType)->first();
        return $r ? (float) $r->amount : null;
    }

    /** Whether this room has any room units linked to reservations (past or future). */
    public function hasReservations(): bool
    {
        $unitIds = $this->roomUnits()->pluck('id');
        if ($unitIds->isEmpty()) {
            return false;
        }
        return \Illuminate\Support\Facades\DB::table('reservation_room_unit')
            ->whereIn('room_unit_id', $unitIds)
            ->exists();
    }
}
