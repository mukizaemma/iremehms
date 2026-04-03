<div>
    <h5 class="mb-4">Platform &amp; login branding</h5>
    <p class="text-muted small">Ireme logo is shown on the login page heading and as fallback when a hotel has no logo.</p>

    @if (session()->has('message'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('message') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-light">Ireme logo</div>
        <div class="card-body">
            <form wire:submit.prevent="save">
                <div class="mb-3">
                    <label class="form-label">Upload Ireme logo (login page &amp; fallback)</label>
                    <input type="file" class="form-control" wire:model="ireme_logo" accept="image/*">
                    @error('ireme_logo') <small class="text-danger">{{ $message }}</small> @enderror
                </div>
                @if($ireme_logo_preview)
                    <div class="mb-3">
                        <img src="{{ $ireme_logo_preview }}" alt="Ireme logo" style="max-height: 80px; width: auto;">
                    </div>
                @endif
                <button type="submit" class="btn btn-primary btn-sm me-2" wire:loading.attr="disabled">Save logo</button>
                @if($ireme_logo_preview)
                    <button type="button" class="btn btn-outline-danger btn-sm" wire:click="removeIremeLogo" wire:confirm="Remove Ireme logo?">Remove</button>
                @endif
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-light">Login background</div>
        <div class="card-body">
            <form wire:submit.prevent="saveLoginBackground">
                <div class="mb-3">
                    <label class="form-label">Upload background image for the login page (left side)</label>
                    <input type="file" class="form-control" wire:model="login_background" accept="image/*">
                    @error('login_background') <small class="text-danger">{{ $message }}</small> @enderror
                </div>
                @if($login_background_preview)
                    <div class="mb-3">
                        <img src="{{ $login_background_preview }}" alt="Login background" style="max-height: 160px; width: auto; border-radius: .5rem;">
                    </div>
                @endif
                <button type="submit" class="btn btn-primary btn-sm me-2" wire:loading.attr="disabled">Save background</button>
                @if($login_background_preview)
                    <button type="button" class="btn btn-outline-danger btn-sm" wire:click="removeLoginBackground" wire:confirm="Remove login background image?">Remove</button>
                @endif
            </form>
        </div>
    </div>

    <p class="small text-muted">Hotel logos are set per hotel in the hotel app (Hotel details) or when editing a hotel in Ireme (Hotels → Edit). Hotel-specific login backgrounds still apply per hotel; when not set, the platform login background is used.</p>
</div>
