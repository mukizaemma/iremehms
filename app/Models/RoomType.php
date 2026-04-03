<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class RoomType extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotel_id',
        'name',
        'slug',
        'description',
        'is_active',
        'max_adults',
        'max_children',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'max_adults' => 'integer',
        'max_children' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (RoomType $roomType) {
            if (empty($roomType->slug)) {
                $roomType->slug = Str::slug($roomType->name);
            }
        });
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(RoomTypeImage::class)->orderBy('sort_order');
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class, 'amenity_room_type');
    }

    public function rates(): HasMany
    {
        return $this->hasMany(RoomTypeRate::class)->orderBy('rate_type');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /** Get the configured amount for a rate type (e.g. 'Locals'), or null if not set. */
    public function getAmountForRateType(string $rateType): ?float
    {
        $r = $this->rates()->where('rate_type', $rateType)->first();
        return $r ? (float) $r->amount : null;
    }

    /** Human-readable occupancy, e.g. "2 adults + 1 child" or "1 adult + 2 children". */
    public function getOccupancyLabel(): string
    {
        $a = (int) ($this->max_adults ?? 0);
        $c = (int) ($this->max_children ?? 0);
        if ($a <= 0 && $c <= 0) {
            return '—';
        }
        $parts = [];
        if ($a > 0) {
            $parts[] = $a . ' ' . ($a === 1 ? 'adult' : 'adults');
        }
        if ($c > 0) {
            $parts[] = $c . ' ' . ($c === 1 ? 'child' : 'children');
        }
        return implode(' + ', $parts);
    }
}
