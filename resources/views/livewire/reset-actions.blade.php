<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="bg-white rounded p-4 border border-danger">
                <h5 class="mb-2 text-danger">
                    <i class="fa fa-trash-alt me-2"></i>Reset Actions
                </h5>
                <p class="text-muted small mb-4">
                    <strong>Warning:</strong> These actions are irreversible. All data in the selected category will be permanently deleted.
                </p>

                @if (session()->has('message'))
                    <div class="alert alert-success alert-dismissible fade show">
                        {{ session('message') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif
                @if (session()->has('error'))
                    <div class="alert alert-danger alert-dismissible fade show">
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                <div class="row g-3">
                    <div class="col-md-6 col-lg-4">
                        <button type="button" class="btn btn-warning w-100" wire:click="confirmReset('stocks')">
                            <i class="fa fa-boxes me-2"></i>Reset Stocks
                        </button>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <button type="button" class="btn btn-warning w-100" wire:click="confirmReset('sales')">
                            <i class="fa fa-shopping-cart me-2"></i>Reset Sales
                        </button>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <button type="button" class="btn btn-warning w-100" wire:click="confirmReset('expenses')">
                            <i class="fa fa-money-bill-wave me-2"></i>Reset Expenses
                        </button>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <button type="button" class="btn btn-warning w-100" wire:click="confirmReset('hr')">
                            <i class="fa fa-users me-2"></i>Reset HR Data
                        </button>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <button type="button" class="btn btn-warning w-100" wire:click="confirmReset('activity_logs')">
                            <i class="fa fa-history me-2"></i>Reset Activity Logs
                        </button>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <button type="button" class="btn btn-danger w-100" wire:click="confirmReset('all')">
                            <i class="fa fa-exclamation-triangle me-2"></i>Reset All Data
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reset Confirmation Modal -->
    @if($showResetConfirmation)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title">
                            <i class="fa fa-exclamation-triangle me-2"></i>Confirm Reset Action
                        </h5>
                        <button type="button" class="btn-close btn-close-white" wire:click="cancelReset"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger">
                            <strong>Warning!</strong> This action cannot be undone. All data will be permanently deleted.
                        </div>
                        <p>You are about to reset: <strong>{{ ucfirst(str_replace('_', ' ', $resetType)) }}</strong></p>
                        <p class="mb-3">Type <strong>"RESET"</strong> in the field below to confirm:</p>
                        <div class="form-floating">
                            <input type="text" class="form-control" id="resetConfirmation"
                                wire:model="resetConfirmation"
                                placeholder="Type RESET to confirm">
                            <label for="resetConfirmation">Confirmation</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="cancelReset">Cancel</button>
                        <button type="button" class="btn btn-danger" wire:click="executeReset">
                            <i class="fa fa-trash me-2"></i>Confirm Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
