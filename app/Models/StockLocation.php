<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'parent_location_id',
        'is_main_location',
        'is_active',
    ];

    protected $casts = [
        'is_main_location' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Parent location (if this is a sub-location)
     */
    public function parentLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'parent_location_id');
    }

    /**
     * Sub-locations
     */
    public function subLocations(): HasMany
    {
        return $this->hasMany(StockLocation::class, 'parent_location_id');
    }

    /**
     * Stocks in this location
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class, 'stock_location_id');
    }

    /**
     * Check if this is a main location
     */
    public function isMainLocation(): bool
    {
        return $this->is_main_location && $this->parent_location_id === null;
    }

    /**
     * Check if this is a sub-location
     */
    public function isSubLocation(): bool
    {
        return !$this->is_main_location && $this->parent_location_id !== null;
    }

    /**
     * Get full name with parent
     */
    public function getFullNameAttribute(): string
    {
        if ($this->parentLocation) {
            return $this->parentLocation->name . ' > ' . $this->name;
        }
        return $this->name;
    }

    /**
     * Get active locations
     */
    public static function active()
    {
        return static::where('is_active', true);
    }

    /**
     * Get main locations only
     */
    public static function mainLocations()
    {
        return static::where('is_main_location', true)
            ->whereNull('parent_location_id')
            ->where('is_active', true);
    }
}
