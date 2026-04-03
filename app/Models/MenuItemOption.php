<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItemOption extends Model
{
    protected $fillable = [
        'group_id',
        'label',
        'value',
        'price_delta',
        'is_default',
        'display_order',
    ];

    protected $casts = [
        'price_delta' => 'decimal:2',
        'is_default' => 'boolean',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(MenuItemOptionGroup::class, 'group_id');
    }
}

