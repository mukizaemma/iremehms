<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HotelPackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'enabled_modules',
        'enabled_departments',
        'price',
        'is_active',
    ];

    protected $casts = [
        'enabled_modules' => 'array',
        'enabled_departments' => 'array',
        'is_active' => 'boolean',
        'price' => 'decimal:2',
    ];

    /**
     * Get the users for the package.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
