<div class="container-fluid py-4">
    @livewire('shift-acknowledgment-banner', ['targetScope' => \App\Models\OperationalShift::SCOPE_POS, 'onlyWhenMissing' => true])
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="bg-white rounded p-3 h-100">
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="mb-0">POS</h5>
                        <span class="text-muted small">Tap items to add them to the cart.</span>
                    </div>
                    @include('livewire.pos.partials.pos-quick-links', ['active' => 'products'])
                </div>

                <!-- Category tabs -->
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <button type="button"
                            class="btn btn-sm {{ $activeCategory === 'all' ? 'btn-primary' : 'btn-outline-secondary' }}"
                            wire:click="setCategory('all')">
                        All
                    </button>
                    @foreach($categories as $cat)
                        <button type="button"
                                class="btn btn-sm {{ (string)$activeCategory === (string)$cat['id'] ? 'btn-primary' : 'btn-outline-secondary' }}"
                                wire:click="setCategory('{{ $cat['id'] }}')">
                            {{ $cat['name'] }}
                        </button>
                    @endforeach
                </div>

                <!-- Products grid -->
                <div class="row g-3 pos-products-grid">
                    @forelse($filteredProducts as $product)
                        <div class="col-sm-6 col-md-4 col-xl-3">
                            <div class="card h-100 shadow-sm border-0 product-card"
                                 wire:click="showProductDetails({{ $product['id'] }})"
                                 style="cursor: pointer; border-radius: 0.9rem; overflow: hidden;">
                                <div class="card-body d-flex flex-column py-3">
                                    <div class="fw-bold small mb-1 text-truncate" title="{{ $product['name'] }}" style="line-height: 1.25;">
                                        {{ $product['name'] }}
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="fw-bold text-primary">{{ number_format($product['price'], 2) }}</span>
                                    </div>
                                    <div class="mt-auto">
                                        <button type="button"
                                                class="btn btn-sm btn-primary w-100"
                                                wire:click.stop="addProduct({{ $product['id'] }})">
                                            <i class="fa fa-plus me-1"></i>Add
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-12">
                            <p class="text-muted mb-0">No products found in this category.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Cart / order summary -->
        <div class="col-lg-4">
            <div class="bg-white rounded p-3 h-100 d-flex flex-column">
                <h6 class="mb-3">Cart</h6>

                <div class="mb-3">
                    <label class="form-label small">Service mode</label>
                    <div class="btn-group w-100">
                        <button type="button"
                                class="btn btn-sm {{ $service_mode === 'dine_in' ? 'btn-primary' : 'btn-outline-secondary' }}"
                                wire:click="$set('service_mode', 'dine_in')">
                            Dine-in
                        </button>
                        <button type="button"
                                class="btn btn-sm {{ $service_mode === 'takeaway' ? 'btn-primary' : 'btn-outline-secondary' }}"
                                wire:click="$set('service_mode', 'takeaway')">
                            Takeaway
                        </button>
                    </div>
                </div>

                @if($service_mode === 'dine_in')
                    <div class="mb-3">
                        <label class="form-label small">Table (optional)</label>
                        <select class="form-select form-select-sm" wire:model="table_id">
                            <option value="">No table selected</option>
                            @foreach($tables as $t)
                                <option value="{{ $t['id'] }}">Table {{ $t['table_number'] }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="flex-grow-1 mb-3 overflow-auto" style="max-height: 320px;">
                    @if(empty($cart))
                        <p class="text-muted small">No items in cart. Click a product to add it.</p>
                    @else
                        @foreach($cart as $item)
                            <div class="d-flex align-items-center justify-content-between border-bottom py-2">
                                <div class="me-2">
                                    <div class="fw-semibold small">{{ $item['name'] }}</div>
                                    <div class="text-muted small">{{ number_format($item['price'], 2) }} each</div>
                                </div>
                                <div class="d-flex align-items-center">
                                    <button type="button" class="btn btn-sm btn-outline-secondary me-1"
                                            wire:click="decrementItem({{ $item['menu_item_id'] }})">
                                        <i class="fa fa-minus"></i>
                                    </button>
                                    <span class="mx-1 small fw-semibold">{{ $item['qty'] }}</span>
                                    <button type="button" class="btn btn-sm btn-outline-secondary ms-1"
                                            wire:click="incrementItem({{ $item['menu_item_id'] }})">
                                        <i class="fa fa-plus"></i>
                                    </button>
                                </div>
                                <div class="text-end small fw-semibold" style="width: 70px;">
                                    {{ number_format($item['price'] * $item['qty'], 2) }}
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>

                <div class="border-top pt-2 mb-2">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted small">Subtotal</span>
                        <span class="fw-semibold small">{{ number_format($this->cartSubtotal, 2) }}</span>
                    </div>
                </div>

                <div class="mt-auto d-grid gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="clearCart" @if(empty($cart)) disabled @endif>
                        Clear cart
                    </button>
                    <button type="button" class="btn btn-primary" wire:click="saveOrder" @if(empty($cart)) disabled @endif>
                        Save order &amp; continue
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Product details modal --}}
    @if($showProductModal && $selectedProduct)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            {{ $selectedProduct['name'] }}
                        </h5>
                        <button type="button" class="btn-close" wire:click="closeProductDetails"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-2">
                            <span class="badge bg-light text-muted me-2">{{ $selectedProduct['category'] ?? 'Uncategorized' }}</span>
                            @if(!empty($selectedProduct['type']))
                                <span class="badge bg-light text-muted">{{ $selectedProduct['type'] }}</span>
                            @endif
                        </div>
                        <p class="fw-semibold mb-2">Price: {{ number_format($selectedProduct['price'], 2) }}</p>

                        @if(!empty($selectedProduct['description']))
                            <div class="mb-3">
                                <h6 class="mb-1">Description</h6>
                                <p class="small mb-0">{{ $selectedProduct['description'] }}</p>
                            </div>
                        @endif

                        <div class="mb-2">
                            <h6 class="mb-1">BoM &amp; stock details</h6>
                            @if(!empty($selectedProductBomLines))
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Ingredient</th>
                                                <th class="text-end">Qty / sale</th>
                                                <th class="text-end">In stock</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($selectedProductBomLines as $line)
                                                <tr>
                                                    <td class="small">{{ $line['stock_name'] ?? '-' }}</td>
                                                    <td class="small text-end">
                                                        {{ number_format($line['qty_per_sale'] ?? 0, 2) }} {{ $line['unit'] ?? '' }}
                                                    </td>
                                                    <td class="small text-end">
                                                        @if(isset($line['current_stock']))
                                                            {{ number_format($line['current_stock'], 2) }}
                                                        @else
                                                            <span class="text-muted">N/A</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <p class="text-muted small mb-0">No active Bill of Menu configured for this product.</p>
                            @endif
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" wire:click="closeProductDetails">Close</button>
                        <button type="button" class="btn btn-primary"
                                wire:click="addProduct({{ $selectedProduct['id'] }})">
                            <i class="fa fa-plus me-1"></i>Add to cart
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
    <style>
        .pos-products-grid .product-card {
            transition: transform 0.12s ease-out, box-shadow 0.12s ease-out;
        }
        .pos-products-grid .product-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1.25rem rgba(15, 23, 42, 0.12);
        }
        .pos-products-grid .product-card .card-body {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        }
    </style>
</div>

