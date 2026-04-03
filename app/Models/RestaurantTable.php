<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RestaurantTable extends Model
{
    protected $fillable = [
        'table_number',
        'capacity',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'capacity' => 'integer',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'table_id');
    }

    /**
     * Current active order for this table (OPEN or CONFIRMED).
     */
    public function activeOrder(): HasOne
    {
        return $this->hasOne(Order::class, 'table_id')
            ->whereIn('order_status', ['OPEN', 'CONFIRMED'])
            ->latest('created_at');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
