<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PreparationStation extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'display_order',
        'is_active',
        'has_printer',
        'printer_name',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'has_printer' => 'boolean',
        'display_order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Active stations for POS: slug => name (for dropdowns, station links, validation).
     * Only active stations; manager controls via Preparation Stations Management.
     */
    public static function getActiveForPos(): array
    {
        return static::active()
            ->orderBy('display_order')
            ->orderBy('name')
            ->get()
            ->pluck('name', 'slug')
            ->all();
    }

    /** Check if a station slug is active (posting allowed). */
    public static function isActiveStation(string $slug): bool
    {
        return static::where('slug', $slug)->where('is_active', true)->exists();
    }

    public function menuItems(): BelongsToMany
    {
        return $this->belongsToMany(MenuItem::class, 'menu_item_station', 'preparation_station_id', 'menu_item_id');
    }
}
