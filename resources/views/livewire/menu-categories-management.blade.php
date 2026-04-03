<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="bg-light rounded p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Menu Management</h5>
                    <button class="btn btn-primary" wire:click="openCategoryForm()">
                        <i class="fa fa-plus me-2"></i>Add Category
                    </button>
                </div>
                <p class="text-muted small mb-3">Organise items into categories (e.g. Drinks, Coffees, Main courses, Bakery) for easier search when taking orders.</p>

                <!-- Tabs: Menu items, Categories (active), Item types -->
                <ul class="nav nav-tabs mb-4">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('menu.items') ? 'active' : '' }}" href="{{ route('menu.items') }}"><i class="fa fa-utensils me-1"></i>Menu items</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('menu.categories') ? 'active' : '' }}" href="{{ route('menu.categories') }}"><i class="fa fa-folder me-1"></i>Categories</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('menu.item-types') ? 'active' : '' }}" href="{{ route('menu.item-types') }}">Item types</a>
                    </li>
                </ul>

                @if (session()->has('message'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('message') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if (session()->has('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                <!-- Search and Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="search" wire:model.live="search" placeholder="Search categories...">
                                    <label for="search">Search Categories</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Categories List -->
                <div class="card">
                    <div class="card-body">
                        @php
                            $posReportLabels = [
                                'food' => 'Food',
                                'beverages' => 'Beverages',
                                'conference_halls' => 'Conference halls',
                                'swimming_pool' => 'Swimming pool',
                                'sauna' => 'Sauna',
                                'massage' => 'Massage',
                                'gym' => 'Gym',
                                'other' => 'Other',
                            ];
                        @endphp
                        @if(count($categories) > 0)
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Code</th>
                                            <th>Display Order</th>
                                            <th>General report bucket</th>
                                            <th>Status</th>
                                            <th>Menu Items</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($categories as $category)
                                            <tr>
                                                <td><strong>{{ $category['name'] }}</strong></td>
                                                <td>{{ $category['code'] ?? 'N/A' }}</td>
                                                <td>{{ $category['display_order'] ?? 0 }}</td>
                                                <td>
                                                    @php
                                                        $bucket = $category['pos_report_column_key'] ?? 'other';
                                                    @endphp
                                                    <span class="badge bg-secondary">{{ $posReportLabels[$bucket] ?? 'Other' }}</span>
                                                </td>
                                                <td>
                                                    @if($category['is_active'])
                                                        <span class="badge bg-success">Active</span>
                                                    @else
                                                        <span class="badge bg-secondary">Inactive</span>
                                                    @endif
                                                </td>
                                                <td>{{ $category['menu_items_count'] ?? 0 }}</td>
                                                <td>
                                                    <button class="btn btn-sm btn-primary" wire:click="openCategoryForm({{ $category['category_id'] }})" title="Edit">
                                                        <i class="fa fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-{{ $category['is_active'] ? 'warning' : 'success' }}" wire:click="toggleActive({{ $category['category_id'] }})" title="{{ $category['is_active'] ? 'Deactivate' : 'Activate' }}">
                                                        <i class="fa fa-{{ $category['is_active'] ? 'ban' : 'check' }}"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" wire:click="deleteCategory({{ $category['category_id'] }})" wire:confirm="Are you sure you want to delete this category?" title="Delete">
                                                        <i class="fa fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="alert alert-info">
                                <i class="fa fa-info-circle me-2"></i>No categories found. Create your first category to get started.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Category Form Modal -->
    @if($showCategoryForm)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5); position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1050; overflow-y: auto;">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="z-index: 1051;">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $editingCategoryId ? 'Edit' : 'New' }} Menu Category</h5>
                        <button type="button" class="btn-close" wire:click="closeCategoryForm"></button>
                    </div>
                    <form wire:submit.prevent="saveCategory">
                        <div class="modal-body">
                            @if ($errors->any())
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="name" wire:model.defer="name" placeholder="Category Name" required>
                                <label for="name">Category Name <span class="text-danger">*</span></label>
                                @error('name')
                                    <div class="text-danger small">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="code" wire:model.defer="code" placeholder="Category Code (Optional)">
                                <label for="code">Category Code (Optional)</label>
                                @error('code')
                                    <div class="text-danger small">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-floating mb-3">
                                <textarea class="form-control" id="description" wire:model.defer="description" placeholder="Description" style="height: 100px"></textarea>
                                <label for="description">Description</label>
                            </div>

                            <div class="form-floating mb-3">
                                <select class="form-select" id="pos_report_column_key" wire:model.defer="pos_report_column_key">
                                    <option value="food">Food</option>
                                    <option value="beverages">Beverages</option>
                                    <option value="conference_halls">Conference halls</option>
                                    <option value="rooms">Rooms (POS packages)</option>
                                    <option value="swimming_pool">Swimming pool</option>
                                    <option value="sauna">Sauna</option>
                                    <option value="massage">Massage</option>
                                    <option value="gym">Gym</option>
                                    <option value="garden">Garden / outdoor events</option>
                                    <option value="outside_catering">Outside catering</option>
                                    <option value="other">Other</option>
                                </select>
                                <label for="pos_report_column_key">General report bucket (POS)</label>
                            </div>

                            <div class="form-floating mb-3">
                                <input type="number" class="form-control" id="display_order" wire:model.defer="display_order" placeholder="Display Order" min="0">
                                <label for="display_order">Display Order</label>
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="is_active" wire:model.defer="is_active">
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="closeCategoryForm">Cancel</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="saveCategory">
                                    <i class="fa fa-save me-2"></i>Save
                                </span>
                                <span wire:loading wire:target="saveCategory">
                                    <span class="spinner-border spinner-border-sm me-2"></span>Saving...
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
