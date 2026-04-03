<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShiftTemplate extends Model
{
    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    public function dayShifts(): HasMany
    {
        return $this->hasMany(DayShift::class, 'shift_template_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
