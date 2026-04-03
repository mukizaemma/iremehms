<div class="row g-4">
    <div class="col-12">
        <div class="bg-light rounded p-4">
            <h5 class="mb-4">Manager Dashboard</h5>
            <p class="text-muted small mb-0">
                Welcome, <strong>{{ $user->name }}</strong>! Use the sections below to open daily and monthly (date-range) reports.
            </p>
        </div>
    </div>

    <div class="col-12">
        <div class="bg-white rounded border p-3">
            @livewire('front-office.general-report-summary-dashboard', ['days' => 7], key('general-report-summary-dashboard-manager'))
        </div>
    </div>
</div>
