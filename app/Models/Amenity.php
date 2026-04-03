<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Amenity extends Model
{
    public const TYPE_ROOM = 'room';
    public const TYPE_HOTEL = 'hotel';

    protected $fillable = [
        'hotel_id',
        'name',
        'type',
        'icon',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function roomTypes(): BelongsToMany
    {
        return $this->belongsToMany(RoomType::class, 'amenity_room_type');
    }

    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(Room::class, 'amenity_room');
    }

    public function isRoomAmenity(): bool
    {
        return $this->type === self::TYPE_ROOM;
    }

    public function isHotelAmenity(): bool
    {
        return $this->type === self::TYPE_HOTEL;
    }
}
