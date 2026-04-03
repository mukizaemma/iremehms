<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="bg-light rounded p-4">
                <h5 class="mb-4">My Profile</h5>

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

                <!-- Profile Information -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h6 class="mb-4">Profile Information</h6>
                        <form wire:submit.prevent="updateProfile">
                            <div class="row g-3">
                                <!-- Profile Image -->
                                <div class="col-12 text-center mb-3">
                                    <div class="position-relative d-inline-block">
                                        <img 
                                            src="{{ $profileImagePreview }}" 
                                            alt="Profile Image" 
                                            class="rounded-circle" 
                                            style="width: 150px; height: 150px; object-fit: cover; border: 3px solid #dee2e6;"
                                            id="profileImagePreview"
                                        >
                                        <label for="profileImage" class="position-absolute bottom-0 end-0 bg-primary text-white rounded-circle p-2" style="cursor: pointer; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                            <i class="fa fa-camera"></i>
                                        </label>
                                        <input 
                                            type="file" 
                                            id="profileImage" 
                                            wire:model="profileImage" 
                                            class="d-none" 
                                            accept="image/*"
                                        >
                                    </div>
                                    @error('profileImage') 
                                        <div class="text-danger small mt-2">{{ $message }}</div>
                                    @enderror
                                    <p class="text-muted small mt-2">Click the camera icon to change your profile image</p>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" wire:model.defer="name" placeholder="Full Name" required>
                                        <label for="name">Full Name <span class="text-danger">*</span></label>
                                        @error('name') 
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" wire:model.defer="email" placeholder="Email Address" required>
                                        <label for="email">Email Address <span class="text-danger">*</span></label>
                                        @error('email') 
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                                        <span wire:loading.remove wire:target="updateProfile">
                                            <i class="fa fa-save me-2"></i>Update Profile
                                        </span>
                                        <span wire:loading wire:target="updateProfile">
                                            <span class="spinner-border spinner-border-sm me-2"></span>Saving...
                                        </span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Password Change -->
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">Change Password</h6>
                            <button 
                                type="button" 
                                class="btn btn-sm btn-outline-primary" 
                                wire:click="togglePasswordForm"
                            >
                                @if($showPasswordForm)
                                    <i class="fa fa-times me-1"></i>Cancel
                                @else
                                    <i class="fa fa-key me-1"></i>Change Password
                                @endif
                            </button>
                        </div>

                        @if($showPasswordForm)
                            <form wire:submit.prevent="updatePassword">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="form-floating">
                                            <input 
                                                type="password" 
                                                class="form-control @error('currentPassword') is-invalid @enderror" 
                                                id="currentPassword" 
                                                wire:model.defer="currentPassword" 
                                                placeholder="Current Password" 
                                                required
                                            >
                                            <label for="currentPassword">Current Password <span class="text-danger">*</span></label>
                                            @error('currentPassword') 
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input 
                                                type="password" 
                                                class="form-control @error('newPassword') is-invalid @enderror" 
                                                id="newPassword" 
                                                wire:model.defer="newPassword" 
                                                placeholder="New Password" 
                                                required
                                            >
                                            <label for="newPassword">New Password <span class="text-danger">*</span></label>
                                            @error('newPassword') 
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                            <small class="text-muted">Minimum 8 characters</small>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input 
                                                type="password" 
                                                class="form-control @error('newPasswordConfirmation') is-invalid @enderror" 
                                                id="newPasswordConfirmation" 
                                                wire:model.defer="newPasswordConfirmation" 
                                                placeholder="Confirm New Password" 
                                                required
                                            >
                                            <label for="newPasswordConfirmation">Confirm New Password <span class="text-danger">*</span></label>
                                            @error('newPasswordConfirmation') 
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                                            <span wire:loading.remove wire:target="updatePassword">
                                                <i class="fa fa-save me-2"></i>Update Password
                                            </span>
                                            <span wire:loading wire:target="updatePassword">
                                                <span class="spinner-border spinner-border-sm me-2"></span>Updating...
                                            </span>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        @else
                            <p class="text-muted mb-0">Click "Change Password" to update your password.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
