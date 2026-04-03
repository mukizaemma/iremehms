<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreRegistration extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_CHECKED_IN = 'checked_in';

    protected $fillable = [
        'hotel_id',
        'reservation_id',
        'group_identifier',
        'reservation_reference',
        'guest_name',
        'guest_id_number',
        'guest_country',
        'guest_email',
        'guest_phone',
        'guest_profession',
        'guest_stay_purpose',
        'organization',
        'private_notes',
        'id_document_path',
        'status',
        'room_unit_id',
        'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function roomUnit(): BelongsTo
    {
        return $this->belongsTo(RoomUnit::class);
    }
}
