<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuCategory extends Model
{
    use HasFactory;

    protected $primaryKey = 'category_id';

    protected $fillable = [
        'name',
        'code',
        'description',
        'pos_report_column_key',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'display_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Menu items in this category
     */
    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'category_id', 'category_id');
    }

    /**
     * Scope for active categories
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
