<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItemOptionGroup extends Model
{
    protected $fillable = [
        'menu_item_id',
        'name',
        'code',
        'type',
        'display_order',
    ];

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id', 'menu_item_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(MenuItemOption::class, 'group_id');
    }
}

