<div class="bg-light rounded p-4">
    <h5 class="mb-4">System configuration</h5>
    <p class="text-muted small mb-4">POS &amp; Stock behaviour, receipt settings, system currency, enabled departments, and feature (module) control.</p>

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

    <form wire:submit.prevent="save">
        <!-- POS & Stock -->
        <div class="row g-4 mb-4">
            <div class="col-12">
                <h6 class="mb-3">POS &amp; Stock</h6>
            </div>
            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="posEnforceStockOnPayment" wire:model="posEnforceStockOnPayment">
                    <label class="form-check-label" for="posEnforceStockOnPayment">
                        Enforce stock when receiving payment (block payment if insufficient stock)
                    </label>
                </div>
                <p class="text-muted small mb-0 mt-1">
                    If unchecked, payments can be completed even when stock is low. Sales not deducted from stock will appear under <strong>Pending stock deductions</strong> for the store keeper / manager to apply or write off later.
                </p>
            </div>
            <div class="col-12 mt-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="useBomForMenuItems" wire:model="useBomForMenuItems">
                    <label class="form-check-label" for="useBomForMenuItems">Use Bill of Menu (BoM) for menu items</label>
                </div>
                <p class="text-muted small mb-0 mt-1">When checked, the hotel uses BoM for menu items. When unchecked, manager or authorized users can add menu items and set price without requiring BoM.</p>
            </div>
        </div>

        <!-- Receipt settings -->
        <div class="row g-4 mb-4">
            <div class="col-12">
                <h6 class="mb-3">Receipt settings</h6>
            </div>
            <div class="col-12">
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="receiptShowVat" wire:model="receiptShowVat">
                    <label class="form-check-label" for="receiptShowVat">Show VAT details on receipts (Subtotal net, VAT %, Total)</label>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="reportsShowVat" wire:model="reportsShowVat">
                    <label class="form-check-label" for="reportsShowVat">Show VAT column &amp; VAT totals on Front Office daily / accommodation reports</label>
                </div>
                <p class="text-muted small">When unchecked, VAT is still calculated in the background for accounting; only the visible report columns are hidden.</p>
            </div>
            <div class="col-md-6">
                <label class="form-label">Thank you text (shown at bottom of receipt)</label>
                <textarea class="form-control" wire:model="receiptThankYouText" rows="2" placeholder="e.g. Thank you for your visit!"></textarea>
            </div>
            <div class="col-12">
                <p class="text-muted small mb-2">Optionally add a MoMo / payment line to receipts:</p>
            </div>
            <div class="col-md-4">
                <label class="form-label">MoMo / Payment label</label>
                <input type="text" class="form-control" wire:model="receiptMomoLabel" placeholder="e.g. Momo Pay, Phone">
            </div>
            <div class="col-md-4">
                <label class="form-label">MoMo / Payment value</label>
                <input type="text" class="form-control" wire:model="receiptMomoValue" placeholder="e.g. 0781234567">
            </div>
        </div>

        <!-- System Currency -->
        <div class="row g-4 mb-4">
            <div class="col-12">
                <h6 class="mb-3">System currency</h6>
            </div>
            <div class="col-md-6">
                <div class="form-floating mb-3">
                    <select class="form-select" id="currency" wire:model="currency" required>
                        <option value="RWF">RWF - Rwandan Franc</option>
                        <option value="USD">USD - US Dollar</option>
                        <option value="EUR">EUR - Euro</option>
                        <option value="GBP">GBP - British Pound</option>
                        <option value="KES">KES - Kenyan Shilling</option>
                        <option value="UGX">UGX - Ugandan Shilling</option>
                        <option value="TZS">TZS - Tanzanian Shilling</option>
                        <option value="ETB">ETB - Ethiopian Birr</option>
                    </select>
                    <label for="currency">System currency <span class="text-danger">*</span></label>
                    <small class="text-muted">Used throughout the system for all financial transactions.</small>
                </div>
            </div>
        </div>

        <!-- Enabled Departments -->
        <div class="row g-4 mb-4">
            <div class="col-12">
                <h6 class="mb-3">Enabled departments</h6>
            </div>
            <div class="col-md-6">
                <div class="bg-white rounded p-3">
                    @foreach($availableDepartments as $department)
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" value="{{ $department->id }}"
                                id="dept{{ $department->id }}"
                                wire:model="enabledDepartments">
                            <label class="form-check-label" for="dept{{ $department->id }}">
                                {{ $department->name }}
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Feature Control: Enable/Disable Modules -->
        <div class="row g-4 mb-4">
            <div class="col-12">
                <div class="bg-white rounded p-4 border border-warning">
                    <h6 class="mb-3 text-danger">
                        <i class="fa fa-exclamation-triangle me-2"></i>Feature control
                    </h6>
                    <p class="text-muted small mb-3">
                        Enable or disable modules for the hotel. Disabled modules will be hidden from all users (except Super Admin) and cannot be accessed.
                    </p>
                    <div class="row">
                        @foreach($availableModules as $module)
                            <div class="col-md-4 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="{{ $module->id }}"
                                        id="module{{ $module->id }}"
                                        wire:model="enabledModules">
                                    <label class="form-check-label" for="module{{ $module->id }}">
                                        <strong>{{ $module->name }}</strong>
                                        @if($module->description)
                                            <br><small class="text-muted">{{ $module->description }}</small>
                                        @endif
                                    </label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end">
            <button type="submit" class="btn btn-primary">Save system configuration</button>
        </div>
    </form>
</div>
