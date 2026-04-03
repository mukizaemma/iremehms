<div class="row g-4">
    <div class="col-12">
        <div class="bg-light rounded p-4">
            <h5 class="mb-4">Department Admin Dashboard</h5>
            <p>Welcome, <strong>{{ $user->name }}</strong>! Managing {{ $user->department->name ?? 'your department' }}.</p>
            
            <div class="alert alert-info mt-3">
                <i class="fa fa-info-circle me-2"></i>
                Department-specific dashboard content will be displayed here.
            </div>
        </div>
    </div>
</div>
