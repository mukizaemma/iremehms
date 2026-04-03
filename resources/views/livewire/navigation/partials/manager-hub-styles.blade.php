@once
@push('styles')
<style>
    .manager-hub .hub-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        min-height: 7.75rem;
        -webkit-tap-highlight-color: transparent;
        border: 1px solid rgba(0, 0, 0, 0.06) !important;
        border-radius: 0.625rem;
        background: #fff;
        /* Lifted “button” shadow — stronger at bottom */
        box-shadow:
            0 1px 2px rgba(0, 0, 0, 0.05),
            0 4px 8px rgba(0, 0, 0, 0.06),
            0 12px 24px rgba(0, 0, 0, 0.08) !important;
    }
    @media (min-width: 576px) {
        .manager-hub .hub-card {
            min-height: 8.5rem;
            border-radius: 0.75rem;
        }
    }
    .manager-hub .hub-tile-body {
        min-height: inherit;
    }
    .manager-hub .hub-tile-icon-wrap {
        width: 3.25rem;
        height: 3.25rem;
    }
    .manager-hub .hub-tile-icon {
        font-size: 1.75rem;
        line-height: 1;
        color: #343a40;
        transition: transform 0.2s ease, color 0.2s ease;
    }
    @media (min-width: 768px) {
        .manager-hub .hub-tile-icon-wrap {
            width: 3.5rem;
            height: 3.5rem;
        }
        .manager-hub .hub-tile-icon {
            font-size: 2rem;
        }
    }
    .manager-hub .hub-tile-title {
        font-weight: 700;
        font-size: 0.9375rem;
        line-height: 1.35;
        letter-spacing: -0.02em;
    }
    @media (min-width: 576px) {
        .manager-hub .hub-tile-title {
            font-size: 1.0625rem;
        }
    }
    .manager-hub .hub-card:hover {
        transform: translateY(-4px);
        border-color: rgba(0, 0, 0, 0.1) !important;
        box-shadow:
            0 4px 8px rgba(0, 0, 0, 0.06),
            0 12px 24px rgba(0, 0, 0, 0.1),
            0 20px 40px rgba(0, 0, 0, 0.08) !important;
    }
    .manager-hub .hub-card:hover .hub-tile-icon {
        color: #212529;
        transform: scale(1.06);
    }
    .manager-hub .hub-card:active {
        transform: translateY(-2px);
    }
</style>
@endpush
@endonce
