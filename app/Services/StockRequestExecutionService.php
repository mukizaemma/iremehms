<?php

namespace App\Services;

use App\Models\Hotel;
use App\Models\Stock;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\StockRequest;
use App\Models\StockRequestItem;
use App\Support\ActivityLogModule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockRequestExecutionService
{
    /**
     * Execute an approved stock request (transfer, issue, or apply item edits).
     */
    public static function execute(StockRequest $request): void
    {
        if (! $request->isApproved()) {
            throw new \InvalidArgumentException('Only approved requests can be executed.');
        }

        $hotel = Hotel::getHotel();
        if ($hotel) {
            OperationalShiftActionGate::assertStoreActionAllowed($hotel);
        }

        $resolved = TimeAndShiftResolver::resolve();
        $approvedById = $request->approved_by_id ?? Auth::id();

        foreach ($request->items as $item) {
            match ($request->type) {
                StockRequest::TYPE_TRANSFER_SUBSTOCK => static::executeTransferSubstock($item, $resolved, $approvedById),
                StockRequest::TYPE_ISSUE_DEPARTMENT, StockRequest::TYPE_TRANSFER_DEPARTMENT, StockRequest::TYPE_ISSUE_BAR_RESTAURANT => static::executeIssueToDepartment($request, $item, $resolved, $approvedById),
                StockRequest::TYPE_ITEM_EDIT => static::executeItemEdit($item),
                default => null,
            };
        }
    }

    /**
     * Issue a single request item (used from Stock Out). Deducts from main stock and transfers/issues.
     * Updates quantity_issued and issue_status on the item.
     */
    public static function issueSingleItem(StockRequestItem $item): bool
    {
        $hotel = Hotel::getHotel();
        if ($hotel) {
            try {
                OperationalShiftActionGate::assertStoreActionAllowed($hotel);
            } catch (\RuntimeException $e) {
                throw $e;
            }
        }

        $request = $item->stockRequest;
        if (! $request || ! $request->isApproved()) {
            return false;
        }
        $remaining = (float) $item->quantity - (float) ($item->quantity_issued ?? 0);
        if ($remaining <= 0) {
            return false;
        }
        $mainStock = Stock::with('stockLocation')->find($item->stock_id);
        if (! $mainStock) {
            return false;
        }
        $available = (float) ($mainStock->current_stock ?? $mainStock->quantity ?? 0);
        $qtyToIssue = min($remaining, $available);
        if ($qtyToIssue <= 0) {
            return false;
        }
        $resolved = TimeAndShiftResolver::resolve();
        $userId = Auth::id();

        if ($request->type === StockRequest::TYPE_TRANSFER_SUBSTOCK) {
            static::executeTransferSubstockWithQty($item, $qtyToIssue, $resolved, $userId);
        } elseif (in_array($request->type, [StockRequest::TYPE_ISSUE_DEPARTMENT, StockRequest::TYPE_TRANSFER_DEPARTMENT, StockRequest::TYPE_ISSUE_BAR_RESTAURANT])) {
            static::executeIssueToDepartmentWithQty($request, $item, $qtyToIssue, $resolved, $userId);
        } else {
            return false;
        }

        $item->quantity_issued = (float) ($item->quantity_issued ?? 0) + $qtyToIssue;
        $item->issue_status = $item->quantity_issued >= (float) $item->quantity ? 'issued' : 'partial';
        $item->save();

        $item->loadMissing('stock');
        ActivityLogger::log(
            'stock_request_issue',
            'Issued '.$qtyToIssue.' '.$mainStock->name.' (stock request #'.$request->id.', line '.$item->id.').',
            StockRequestItem::class,
            $item->id,
            null,
            [
                'quantity_issued' => (float) $item->quantity_issued,
                'issue_status' => $item->issue_status,
                'stock_request_id' => $request->id,
            ],
            ActivityLogModule::STOCK
        );

        return true;
    }

    protected static function executeTransferSubstock(
        \App\Models\StockRequestItem $item,
        array $resolved,
        int $approvedById
    ): void {
        $mainStock = Stock::with('stockLocation')->find($item->stock_id);
        $toLocationId = $item->to_stock_location_id ?? $item->stockRequest->to_stock_location_id;
        if (! $mainStock || ! $toLocationId) {
            return;
        }

        $subLocation = StockLocation::find($toLocationId);
        if (! $subLocation || ! $subLocation->isSubLocation() || $subLocation->parent_location_id != $mainStock->stock_location_id) {
            return;
        }

        $qty = (float) $item->quantity;
        if ($qty <= 0 || $mainStock->current_stock < $qty) {
            return;
        }

        $subStock = Stock::where('name', $mainStock->name)
            ->where('stock_location_id', $subLocation->id)
            ->where('item_type_id', $mainStock->item_type_id)
            ->first();

        if (! $subStock) {
            $subStock = Stock::create([
                'name' => $mainStock->name,
                'code' => $mainStock->code.'_'.$subLocation->code,
                'description' => $mainStock->description,
                'use_barcode' => $mainStock->use_barcode,
                'barcode' => null,
                'item_type_id' => $mainStock->item_type_id,
                'inventory_category' => $mainStock->inventory_category,
                'package_unit' => $mainStock->package_unit,
                'package_size' => $mainStock->package_size,
                'qty_unit' => $mainStock->qty_unit,
                'purchase_price' => $mainStock->purchase_price,
                'sale_price' => $mainStock->sale_price,
                'tax_type' => $mainStock->tax_type,
                'beginning_stock_qty' => 0,
                'current_stock' => 0,
                'safety_stock' => $mainStock->safety_stock,
                'use_expiration' => $mainStock->use_expiration,
                'expiration_date' => $mainStock->expiration_date,
                'stock_location_id' => $subLocation->id,
                'quantity' => 0,
                'unit' => $mainStock->qty_unit,
                'unit_price' => $mainStock->purchase_price,
            ]);
        }

        DB::transaction(function () use ($mainStock, $subStock, $qty, $resolved, $approvedById, $subLocation) {
            StockMovement::create([
                'stock_id' => $mainStock->id,
                'movement_type' => 'TRANSFER',
                'quantity' => -$qty,
                'unit_price' => $mainStock->purchase_price,
                'total_value' => -($qty * $mainStock->purchase_price),
                'from_department_id' => $mainStock->department_id,
                'to_department_id' => $mainStock->department_id,
                'user_id' => $approvedById,
                'shift_id' => $resolved['shift_id'],
                'business_date' => $resolved['business_date'],
                'notes' => 'Transfer to sub-location: '.$subLocation->name.' (stock request).',
            ]);

            StockMovement::create([
                'stock_id' => $subStock->id,
                'movement_type' => 'TRANSFER',
                'quantity' => $qty,
                'unit_price' => $mainStock->purchase_price,
                'total_value' => $qty * $mainStock->purchase_price,
                'from_department_id' => $mainStock->department_id,
                'to_department_id' => $mainStock->department_id,
                'user_id' => $approvedById,
                'shift_id' => $resolved['shift_id'],
                'business_date' => $resolved['business_date'],
                'notes' => 'Transfer from main: '.$mainStock->stockLocation->name.' (stock request).',
            ]);

            $mainStock->current_stock -= $qty;
            $mainStock->quantity = $mainStock->current_stock;
            $mainStock->save();

            $subStock->current_stock += $qty;
            $subStock->quantity = $subStock->current_stock;
            $subStock->save();
        });
    }

    protected static function executeTransferSubstockWithQty(
        StockRequestItem $item,
        float $qty,
        array $resolved,
        int $userId
    ): void {
        $mainStock = Stock::with('stockLocation')->find($item->stock_id);
        $toLocationId = $item->to_stock_location_id ?? $item->stockRequest->to_stock_location_id;
        if (! $mainStock || ! $toLocationId || $qty <= 0) {
            return;
        }
        $subLocation = StockLocation::find($toLocationId);
        if (! $subLocation || ! $subLocation->isSubLocation() || $subLocation->parent_location_id != $mainStock->stock_location_id) {
            return;
        }
        $subStock = Stock::where('name', $mainStock->name)
            ->where('stock_location_id', $subLocation->id)
            ->where('item_type_id', $mainStock->item_type_id)
            ->first();
        if (! $subStock) {
            $subStock = Stock::create([
                'name' => $mainStock->name,
                'code' => $mainStock->code.'_'.$subLocation->code,
                'description' => $mainStock->description,
                'use_barcode' => $mainStock->use_barcode,
                'barcode' => null,
                'item_type_id' => $mainStock->item_type_id,
                'inventory_category' => $mainStock->inventory_category,
                'package_unit' => $mainStock->package_unit,
                'package_size' => $mainStock->package_size,
                'qty_unit' => $mainStock->qty_unit,
                'purchase_price' => $mainStock->purchase_price,
                'sale_price' => $mainStock->sale_price,
                'tax_type' => $mainStock->tax_type,
                'beginning_stock_qty' => 0,
                'current_stock' => 0,
                'safety_stock' => $mainStock->safety_stock,
                'use_expiration' => $mainStock->use_expiration,
                'expiration_date' => $mainStock->expiration_date,
                'stock_location_id' => $subLocation->id,
                'quantity' => 0,
                'unit' => $mainStock->qty_unit,
                'unit_price' => $mainStock->purchase_price,
            ]);
        }
        DB::transaction(function () use ($mainStock, $subStock, $qty, $resolved, $userId, $subLocation) {
            StockMovement::create([
                'stock_id' => $mainStock->id,
                'movement_type' => 'TRANSFER',
                'quantity' => -$qty,
                'unit_price' => $mainStock->purchase_price,
                'total_value' => -($qty * $mainStock->purchase_price),
                'from_department_id' => $mainStock->department_id,
                'to_department_id' => $mainStock->department_id,
                'user_id' => $userId,
                'shift_id' => $resolved['shift_id'],
                'business_date' => $resolved['business_date'],
                'notes' => 'Transfer to sub-location: '.$subLocation->name.' (Stock Out).',
            ]);
            StockMovement::create([
                'stock_id' => $subStock->id,
                'movement_type' => 'TRANSFER',
                'quantity' => $qty,
                'unit_price' => $mainStock->purchase_price,
                'total_value' => $qty * $mainStock->purchase_price,
                'from_department_id' => $mainStock->department_id,
                'to_department_id' => $mainStock->department_id,
                'user_id' => $userId,
                'shift_id' => $resolved['shift_id'],
                'business_date' => $resolved['business_date'],
                'notes' => 'Transfer from main: '.$mainStock->stockLocation->name.' (Stock Out).',
            ]);
            $mainStock->current_stock -= $qty;
            $mainStock->quantity = $mainStock->current_stock;
            $mainStock->save();
            $subStock->current_stock += $qty;
            $subStock->quantity = $subStock->current_stock;
            $subStock->save();
        });
    }

    protected static function executeIssueToDepartmentWithQty(
        StockRequest $request,
        StockRequestItem $item,
        float $qty,
        array $resolved,
        int $userId
    ): void {
        $mainStock = Stock::with('stockLocation')->find($item->stock_id);
        $toDepartmentId = $item->to_department_id ?? $request->to_department_id;
        if (! $mainStock || ! $toDepartmentId || $qty <= 0) {
            return;
        }
        DB::transaction(function () use ($mainStock, $qty, $resolved, $userId, $toDepartmentId) {
            StockMovement::create([
                'stock_id' => $mainStock->id,
                'movement_type' => 'TRANSFER',
                'quantity' => -$qty,
                'unit_price' => $mainStock->purchase_price,
                'total_value' => -($qty * $mainStock->purchase_price),
                'from_department_id' => $mainStock->department_id,
                'to_department_id' => $toDepartmentId,
                'user_id' => $userId,
                'shift_id' => $resolved['shift_id'],
                'business_date' => $resolved['business_date'],
                'notes' => 'Issue to department (Stock Out).',
            ]);
            $mainStock->current_stock -= $qty;
            $mainStock->quantity = $mainStock->current_stock;
            $mainStock->save();
        });
    }

    protected static function executeIssueToDepartment(
        StockRequest $request,
        \App\Models\StockRequestItem $item,
        array $resolved,
        int $approvedById
    ): void {
        $mainStock = Stock::with('stockLocation')->find($item->stock_id);
        $toDepartmentId = $item->to_department_id ?? $request->to_department_id;
        if (! $mainStock || ! $toDepartmentId) {
            return;
        }

        $qty = (float) $item->quantity;
        if ($qty <= 0 || $mainStock->current_stock < $qty) {
            return;
        }

        DB::transaction(function () use ($mainStock, $qty, $resolved, $approvedById, $toDepartmentId) {
            StockMovement::create([
                'stock_id' => $mainStock->id,
                'movement_type' => 'TRANSFER',
                'quantity' => -$qty,
                'unit_price' => $mainStock->purchase_price,
                'total_value' => -($qty * $mainStock->purchase_price),
                'from_department_id' => $mainStock->department_id,
                'to_department_id' => $toDepartmentId,
                'user_id' => $approvedById,
                'shift_id' => $resolved['shift_id'],
                'business_date' => $resolved['business_date'],
                'notes' => 'Issue to department (stock request).',
            ]);

            $mainStock->current_stock -= $qty;
            $mainStock->quantity = $mainStock->current_stock;
            $mainStock->save();
        });
    }

    protected static function executeItemEdit(\App\Models\StockRequestItem $item): void
    {
        $stock = Stock::find($item->stock_id);
        $data = $item->edit_data;
        if (! $stock || ! is_array($data) || empty($data)) {
            return;
        }

        $allowed = ['name', 'description', 'purchase_price', 'safety_stock'];
        $updates = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $updates[$field] = $data[$field];
            }
        }
        if (! empty($updates)) {
            $stock->update($updates);
        }
    }
}
