<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasFactory;

    protected $primaryKey = 'supplier_id';

    protected $fillable = [
        'name',
        'contact_person',
        'phone',
        'email',
        'default_currency',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Purchase requisitions from this supplier
     */
    public function purchaseRequisitions(): HasMany
    {
        return $this->hasMany(PurchaseRequisition::class, 'supplier_id');
    }

    /**
     * Goods receipts from this supplier
     */
    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class, 'supplier_id');
    }

    /**
     * Expenses linked to this supplier
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'supplier_id');
    }

    /**
     * Scope for active suppliers
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
