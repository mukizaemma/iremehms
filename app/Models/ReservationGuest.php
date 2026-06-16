<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationGuest extends Model
{
    protected $fillable = [
        'hotel_id',
        'reservation_id',
        'is_primary',
        'sort_order',
        'full_name',
        'id_number',
        'phone',
        'email',
        'country',
        'check_in_date',
        'check_out_date',
        'breakfast_preferred_time',
        'dinner_preferred_time',
        'breakfast_in_room',
        'dinner_in_room',
        'meal_service_notes',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'check_in_date' => 'date',
        'check_out_date' => 'date',
        'breakfast_in_room' => 'boolean',
        'dinner_in_room' => 'boolean',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }
}
