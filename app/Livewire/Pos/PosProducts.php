<?php

namespace App\Livewire\Pos;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PosSession;
use App\Models\RestaurantTable;
use App\Models\BillOfMenu;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Product browser + cart UI for restaurant.
 *
 * Used by waiter, cashier, and manager to quickly add items to a cart-like box,
 * optionally attach to a table, and save an OPEN order which can then be paid/printed
 * using the existing POS flows.
 */
class PosProducts extends Component
{
    /** @var array<int, array> */
    public array $categories = [];

    /** currently selected category id or 'all' */
    public string $activeCategory = 'all';

    /** @var array<int, array> cached products list */
    public array $products = [];

    /** @var array<int, array{menu_item_id:int,name:string,price:float,qty:int}> */
    public array $cart = [];

    public ?int $table_id = null;
    /** @var array<int, array> */
    public array $tables = [];

    public string $service_mode = 'dine_in'; // 'dine_in' | 'takeaway'

    public ?array $selectedProduct = null;
    /** @var array<int, array> */
    public array $selectedProductBomLines = [];
    public bool $showProductModal = false;

    public function mount()
    {
        // Ensure user has permission and an open POS session
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('pos_take_orders')) {
            abort(403, 'You are not allowed to take POS orders.');
        }
        $session = PosSession::getOpenForUser(Auth::id());
        if (! $session) {
            session()->flash('error', 'Open a POS session first.');
            return $this->redirect(route('pos.home'), navigate: true);
        }

        $this->loadCategoriesAndProducts();
        $this->tables = RestaurantTable::active()
            ->orderBy('table_number')
            ->get(['id', 'table_number'])
            ->toArray();
    }

    protected function loadCategoriesAndProducts(): void
    {
        $this->categories = MenuCategory::active()
            ->orderBy('display_order')
            ->orderBy('name')
            ->get(['category_id', 'name'])
            ->map(fn ($cat) => [
                'id' => $cat->category_id,
                'name' => $cat->name,
            ])
            ->toArray();

        $this->products = MenuItem::with('category')
            ->active()
            ->orderBy('display_order')
            ->orderBy('name')
            ->get()
            ->map(function (MenuItem $item) {
                return [
                    'id' => $item->menu_item_id,
                    'name' => $item->name,
                    'price' => (float) $item->sale_price,
                    'category_id' => $item->category_id,
                    'category_name' => optional($item->category)->name,
                    'preparation_station' => $item->preparation_station,
                    'image_url' => $item->image
                        ? \Illuminate\Support\Facades\Storage::url($item->image)
                        : null,
                ];
            })
            ->toArray();
    }

    public function setCategory($categoryId): void
    {
        $this->activeCategory = $categoryId === 'all' ? 'all' : (string) $categoryId;
    }

    public function getFilteredProductsProperty(): array
    {
        if ($this->activeCategory === 'all') {
            return $this->products;
        }

        $id = (int) $this->activeCategory;
        return array_values(array_filter($this->products, fn ($p) => (int) $p['category_id'] === $id));
    }

    public function showProductDetails(int $menuItemId): void
    {
        $item = MenuItem::with([
            'category',
            'menuItemType',
            'activeBillOfMenuRelation.items.stockItem',
        ])->find($menuItemId);

        if (! $item) {
            return;
        }

        $this->selectedProduct = [
            'id' => $item->menu_item_id,
            'name' => $item->name,
            'category' => optional($item->category)->name,
            'type' => optional($item->menuItemType)->name,
            'price' => (float) $item->sale_price,
            'description' => $item->description,
        ];

        $this->selectedProductBomLines = [];

        $bom = $item->activeBillOfMenuRelation;
        if ($bom instanceof BillOfMenu) {
            $bom->loadMissing('items.stockItem');
            $this->selectedProductBomLines = $bom->items->map(function ($line) {
                $stock = $line->stockItem;
                return [
                    'stock_name' => $stock?->name,
                    'qty_per_sale' => (float) $line->quantity,
                    'unit' => $line->unit ?: ($stock->qty_unit ?? $stock->unit ?? 'unit'),
                    'current_stock' => $stock?->current_stock,
                ];
            })->toArray();
        }

        $this->showProductModal = true;
    }

    public function closeProductDetails(): void
    {
        $this->showProductModal = false;
        $this->selectedProduct = null;
        $this->selectedProductBomLines = [];
    }

    public function addProduct(int $menuItemId): void
    {
        foreach ($this->cart as &$item) {
            if ($item['menu_item_id'] === $menuItemId) {
                $item['qty']++;
                return;
            }
        }

        $product = collect($this->products)->firstWhere('id', $menuItemId);
        if (! $product) {
            return;
        }

        $this->cart[] = [
            'menu_item_id' => $product['id'],
            'name' => $product['name'],
            'price' => $product['price'],
            'qty' => 1,
            'station' => $product['preparation_station'] ?? null,
        ];
    }

    public function incrementItem(int $menuItemId): void
    {
        foreach ($this->cart as &$item) {
            if ($item['menu_item_id'] === $menuItemId) {
                $item['qty']++;
                return;
            }
        }
    }

    public function decrementItem(int $menuItemId): void
    {
        foreach ($this->cart as $index => $item) {
            if ($item['menu_item_id'] === $menuItemId) {
                $newQty = max(0, $item['qty'] - 1);
                if ($newQty === 0) {
                    unset($this->cart[$index]);
                    $this->cart = array_values($this->cart);
                } else {
                    $this->cart[$index]['qty'] = $newQty;
                }
                return;
            }
        }
    }

    public function clearCart(): void
    {
        $this->cart = [];
    }

    public function getCartSubtotalProperty(): float
    {
        return collect($this->cart)->sum(fn ($item) => $item['price'] * $item['qty']);
    }

    public function saveOrder(): void
    {
        if (empty($this->cart)) {
            session()->flash('error', 'Add at least one product to the cart.');
            return;
        }

        $session = PosSession::getOpenForUser(Auth::id());
        if (! $session) {
            session()->flash('error', 'Open a POS session first.');
            return;
        }

        $order = Order::create([
            'table_id' => $this->service_mode === 'dine_in' ? $this->table_id : null,
            'waiter_id' => Auth::id(),
            'session_id' => $session->id,
            'order_status' => 'OPEN',
        ]);

        foreach ($this->cart as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'menu_item_id' => $item['menu_item_id'],
                'quantity' => $item['qty'],
                'unit_price' => $item['price'],
                'posted_to_station' => $item['station'] ?? null,
            ]);
        }

        session()->flash('message', 'Order saved. You can continue from Orders or proceed to payment.');
        $this->clearCart();

        // Redirect to order detail so user can continue, print, or pay
        $this->redirect(route('pos.orders', ['order' => $order->id]), navigate: true);
    }

    public function render()
    {
        return view('livewire.pos.pos-products', [
            'filteredProducts' => $this->filteredProducts,
        ])->layout('livewire.layouts.app-layout');
    }
}

