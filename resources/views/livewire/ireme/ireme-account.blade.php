<div>
    @if(!auth()->user() || !auth()->user()->isSuperAdmin())
        <p class="text-danger">Only Ireme Super Admin can access this page.</p>
        @return
    @endif

    <h5 class="mb-4">Ireme account &amp; invoice details</h5>
    <p class="text-muted small">These details appear on subscription invoices. Only Super Admin can edit.</p>

    @if (session()->has('message'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('message') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <form wire:submit.prevent="save">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-light">Company details (Ireme HMS)</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Company name</label>
                        <input type="text" class="form-control" wire:model="ireme_company_name" placeholder="Ireme HMS">
                        @error('ireme_company_name') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone</label>
                        <input type="text" class="form-control" wire:model="ireme_phone" placeholder="+250 ...">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" wire:model="ireme_email" placeholder="contact@ireme.com">
                        @error('ireme_email') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">TIN</label>
                        <input type="text" class="form-control" wire:model="ireme_tin" placeholder="Tax ID / TIN">
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-light">Payment methods (shown on invoices)</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Bank account number</label>
                        <input type="text" class="form-control" wire:model="ireme_bank_account" placeholder="Account number">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Momo Pay code</label>
                        <input type="text" class="form-control" wire:model="ireme_momo_code" placeholder="Mobile money code">
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-light">Invoice text</div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Short description (line on invoice)</label>
                    <input type="text" class="form-control" wire:model="ireme_invoice_description" placeholder="e.g. Hotel management system subscription.">
                    @error('ireme_invoice_description') <small class="text-danger">{{ $message }}</small> @enderror
                </div>
                <div class="mb-0">
                    <label class="form-label">Thank you message</label>
                    <textarea class="form-control" wire:model="ireme_invoice_thank_you" rows="2" placeholder="Thank you for your business."></textarea>
                    @error('ireme_invoice_thank_you') <small class="text-danger">{{ $message }}</small> @enderror
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Save</button>
    </form>
</div>
