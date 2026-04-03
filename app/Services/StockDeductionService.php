<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PendingStockDeduction;
use App\Models\Stock;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class StockDeductionService
{
    /**
     * Check if we can deduct stock for this order (all items with BoM have sufficient stock).
     * Returns list of insufficient items: ['message' => ..., 'stock' => name] or empty array.
     */
    public static function checkSufficientStock(Order $order): array
    {
        $insufficient = [];
        $order->load(['orderItems.menuItem.activeBillOfMenuRelation.items.stockItem']);

        foreach ($order->orderItems as $orderItem) {
            $bom = $orderItem->menuItem->activeBillOfMenuRelation;
            if (!$bom || !$bom->relationLoaded('items')) {
                continue;
            }
            $saleQty = (float) $orderItem->quantity;
            foreach ($bom->items as $line) {
                $stock = $line->stockItem;
                if (!$stock) {
                    continue;
                }
                $required = $saleQty * (float) $line->quantity;
                $available = (float) ($stock->current_stock ?? 0);
                if ($available < $required) {
                    $insufficient[] = [
                        'message' => sprintf(
                            '%s: need %s %s, only %s available',
                            $stock->name,
                            number_format($required, 2),
                            $stock->qty_unit ?? $stock->unit ?? 'unit',
                            number_format($available, 2)
                        ),
                        'stock_name' => $stock->name,
                    ];
                }
            }
        }

        return $insufficient;
    }

    /**
     * Deduct stock for a paid order. Creates SALE movements and decrements current_stock.
     * Call after invoice is marked PAID. Throws if insufficient stock.
     */
    public static function deductForOrder(Order $order): void
    {
        $insufficient = self::checkSufficientStock($order);
        if (!empty($insufficient)) {
            throw new \RuntimeException(
                'Insufficient stock: ' . implode('; ', array_column($insufficient, 'message'))
            );
        }

        $session = $order->session;
        $shiftId = null;
        $businessDate = null;
        if ($session->business_day_id && $session->relationLoaded('businessDay')) {
            $businessDate = $session->businessDay->business_date;
        } elseif ($session->shift_log_id && $session->relationLoaded('shiftLog')) {
            $shiftId = $session->shiftLog->shift_id;
            $businessDate = $session->shiftLog->business_date;
        }
        if ($businessDate === null) {
            $session->load(['businessDay', 'shiftLog', 'operationalShift']);
            if ($session->business_day_id) {
                $businessDate = $session->businessDay->business_date;
            } elseif ($session->shiftLog) {
                $shiftId = $session->shiftLog->shift_id;
                $businessDate = $session->shiftLog->business_date;
            } elseif ($session->operational_shift_id && $session->operationalShift) {
                $ref = $session->operationalShift->reference_date;
                $businessDate = $ref ? (\is_string($ref) ? $ref : $ref->format('Y-m-d')) : null;
            }
        }
        if ($businessDate === null) {
            throw new \RuntimeException('Order session has no business date (link an operational shift, business day, or shift log).');
        }

        DB::transaction(function () use ($order, $shiftId, $businessDate) {
            foreach ($order->orderItems as $orderItem) {
                self::deductForOrderItem($orderItem, $shiftId, $businessDate);
            }
        });
    }

    protected static function deductForOrderItem(OrderItem $orderItem, $shiftId, $businessDate): void
    {
        $menuItem = $orderItem->menuItem;
        $bom = $menuItem->activeBillOfMenuRelation;
        if (!$bom) {
            return;
        }

        $bom->load('items.stockItem');
        $saleQty = (float) $orderItem->quantity;

        foreach ($bom->items as $line) {
            $stock = $line->stockItem;
            if (!$stock) {
                continue;
            }
            $qty = $saleQty * (float) $line->quantity;
            if ($qty <= 0) {
                continue;
            }

            $outQty = -$qty;
            $unitPrice = (float) ($stock->purchase_price ?? $stock->sale_price ?? 0);
            $totalValue = $unitPrice * $qty;

            StockMovement::create([
                'stock_id' => $stock->id,
                'movement_type' => 'SALE',
                'quantity' => $outQty,
                'unit_price' => $unitPrice,
                'total_value' => $totalValue,
                'user_id' => auth()->id(),
                'shift_id' => $shiftId, // nullable when using business_day-only flow
                'business_date' => $businessDate,
                'order_item_id' => $orderItem->id,
                'notes' => 'POS sale',
            ]);

            $stock->decrement('current_stock', $qty);
            if ($stock->getAttribute('quantity') !== null) {
                $stock->quantity = $stock->current_stock;
                $stock->saveQuietly();
            }
        }
    }

    /**
     * Record pending stock deductions for a paid order when stock was insufficient.
     * Store keeper / manager can later apply these when stock is available or write off.
     */
    public static function recordPendingDeductionsForOrder(Order $order): void
    {
        $order->load(['orderItems.menuItem.activeBillOfMenuRelation.items.stockItem']);

        foreach ($order->orderItems as $orderItem) {
            $bom = $orderItem->menuItem->activeBillOfMenuRelation;
            if (!$bom || !$bom->relationLoaded('items')) {
                continue;
            }
            $saleQty = (float) $orderItem->quantity;
            foreach ($bom->items as $line) {
                $stock = $line->stockItem;
                if (!$stock) {
                    continue;
                }
                $required = $saleQty * (float) $line->quantity;
                if ($required <= 0) {
                    continue;
                }
                $available = (float) ($stock->current_stock ?? 0);

                PendingStockDeduction::create([
                    'order_id' => $order->id,
                    'order_item_id' => $orderItem->id,
                    'stock_id' => $stock->id,
                    'quantity_required' => $required,
                    'quantity_available_at_sale' => $available,
                    'status' => PendingStockDeduction::STATUS_PENDING,
                ]);
            }
        }
    }

    /**
     * Apply a single pending deduction (when stock is now available). Creates SALE movement and marks pending as DEDUCTED.
     */
    public static function applyPendingDeduction(PendingStockDeduction $pending): void
    {
        if ($pending->status !== PendingStockDeduction::STATUS_PENDING) {
            throw new \RuntimeException('This pending deduction is already applied or written off.');
        }

        $stock = $pending->stock;
        $available = (float) ($stock->current_stock ?? 0);
        if ($available < (float) $pending->quantity_required) {
            throw new \RuntimeException(
                sprintf('%s: still insufficient. Need %s, only %s available.', $stock->name, $pending->quantity_required, $available)
            );
        }

        $order = $pending->order;
        $session = $order->session;
        $shiftId = null;
        $businessDate = null;
        if ($session->business_day_id) {
            $session->load('businessDay');
            $businessDate = $session->businessDay?->business_date;
        }
        if ($businessDate === null && $session->shift_log_id) {
            $session->load('shiftLog');
            $shiftId = $session->shiftLog?->shift_id;
            $businessDate = $session->shiftLog?->business_date;
        }
        if ($businessDate === null && $session->operational_shift_id) {
            $session->load('operationalShift');
            $ref = $session->operationalShift?->reference_date;
            $businessDate = $ref ? (\is_string($ref) ? $ref : $ref->format('Y-m-d')) : null;
        }
        if ($businessDate === null) {
            throw new \RuntimeException('Order session has no business date (link an operational shift, business day, or shift log).');
        }

        $qty = (float) $pending->quantity_required;
        $unitPrice = (float) ($stock->purchase_price ?? $stock->sale_price ?? 0);
        $totalValue = $unitPrice * $qty;

        DB::transaction(function () use ($pending, $stock, $qty, $unitPrice, $totalValue, $shiftId, $businessDate) {
            StockMovement::create([
                'stock_id' => $stock->id,
                'movement_type' => 'SALE',
                'quantity' => -$qty,
                'unit_price' => $unitPrice,
                'total_value' => $totalValue,
                'user_id' => auth()->id(),
                'shift_id' => $shiftId,
                'business_date' => $businessDate,
                'order_item_id' => $pending->order_item_id,
                'notes' => 'POS sale (applied from pending)',
            ]);

            $stock->decrement('current_stock', $qty);
            if ($stock->getAttribute('quantity') !== null) {
                $stock->quantity = $stock->current_stock;
                $stock->saveQuietly();
            }

            $pending->update([
                'status' => PendingStockDeduction::STATUS_DEDUCTED,
                'deducted_at' => now(),
            ]);
        });
    }
}
