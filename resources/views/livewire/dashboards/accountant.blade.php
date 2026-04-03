<div class="row g-4">
    <div class="col-12">
        <div class="bg-light rounded p-4">
            <h5 class="mb-4">Accountant Dashboard</h5>
            <p class="text-muted small mb-0">
                Welcome, <strong>{{ $user->name }}</strong>! Use the sections below to open reports and module pages.
            </p>
        </div>
    </div>

    <div class="col-12">
        <div class="bg-white rounded border p-3">
            @livewire('front-office.general-report-summary-dashboard', ['days' => 7], key('general-report-summary-dashboard-accountant'))
        </div>
    </div>
</div>

