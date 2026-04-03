<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    public $timestamps = false;

    public const CHARGE_TYPE_POS = 'pos';
    public const CHARGE_TYPE_ROOM = 'room';
    public const CHARGE_TYPE_HOTEL_COVERED = 'hotel_covered';

    protected $fillable = [
        'order_id',
        'reservation_id',
        'room_id',
        'invoice_number',
        'total_amount',
        'invoice_status',
        'charge_type',
        'parent_invoice_id',
        'split_type',
        'posted_by_id',
        'hotel_covered_names',
        'hotel_covered_reason',
        'assigned_at',
        'modification_approved_for_user_id',
        'modification_approved_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'created_at' => 'datetime',
        'assigned_at' => 'datetime',
        'modification_approved_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function parentInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'parent_invoice_id');
    }

    public function childInvoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'parent_invoice_id');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function modificationApprovedForUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modification_approved_for_user_id');
    }

    public function modificationRequests(): HasMany
    {
        return $this->hasMany(ReceiptModificationRequest::class);
    }

    /** Invoice is locked once payment/assignment was confirmed (PAID or CREDIT). */
    public function isModificationLocked(): bool
    {
        return $this->invoice_status === 'PAID' || $this->invoice_status === 'CREDIT';
    }

    /** User can modify if they are authorized (GM or permission) or have an approved modification request. */
    public function canBeModifiedBy(User $user): bool
    {
        if (!$this->isModificationLocked()) {
            return true;
        }
        if ($user->hasPermission('pos_approve_receipt_modification') || $user->isEffectiveGeneralManager() || $user->canNavigateModules()) {
            return true;
        }
        if ($this->modification_approved_for_user_id && (int) $this->modification_approved_for_user_id === (int) $user->id && $this->modification_approved_at) {
            return true;
        }
        return false;
    }

    /** Only the order creator/controller (waiter) can request modification. */
    public function canRequestModificationBy(User $user): bool
    {
        if (!$this->isModificationLocked()) {
            return false;
        }
        $order = $this->order;
        return $order && (int) $order->waiter_id === (int) $user->id;
    }

    public function clearModificationApproval(): void
    {
        $this->update([
            'modification_approved_for_user_id' => null,
            'modification_approved_at' => null,
        ]);
    }

    public function isRoomCharge(): bool
    {
        return $this->charge_type === self::CHARGE_TYPE_ROOM;
    }

    public function isHotelCovered(): bool
    {
        return $this->charge_type === self::CHARGE_TYPE_HOTEL_COVERED;
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isPaid(): bool
    {
        return $this->invoice_status === 'PAID';
    }

    public function isCredit(): bool
    {
        return $this->invoice_status === 'CREDIT';
    }

    public function isUnpaid(): bool
    {
        return $this->invoice_status === 'UNPAID';
    }

    public function getTotalPaidAttribute(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    public function getBalanceAttribute(): float
    {
        return $this->total_amount - $this->total_paid;
    }
}
