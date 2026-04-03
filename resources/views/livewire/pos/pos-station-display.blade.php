<div class="container-fluid py-3 station-display" wire:poll.5s="loadOrders"
     x-data="{ playNewOrderSound() {
         try {
             const ctx = new (window.AudioContext || window.webkitAudioContext)();
             const o = ctx.createOscillator();
             const g = ctx.createGain();
             o.connect(g);
             g.connect(ctx.destination);
             o.frequency.value = 800;
             g.gain.setValueAtTime(0.3, ctx.currentTime);
             g.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.15);
             o.start(ctx.currentTime);
             o.stop(ctx.currentTime + 0.15);
         } catch (e) {}
     } }"
     wire:on.play-new-order-sound="playNewOrderSound()">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div class="d-flex align-items-center gap-3">
            <h4 class="mb-0 station-title">
                <i class="fa fa-tv me-2"></i>{{ $stationName }}
            </h4>
            <span class="badge bg-primary rounded-pill station-badge" title="Orders to prepare">{{ count($orders) }}</span>
            @if(count($orders) > 0)
                <span class="text-muted small">(latest first)</span>
                @if(!$canMarkReady)
                    <span class="text-muted small">— Your orders only</span>
                @endif
            @endif
        </div>
        <div class="d-flex align-items-center gap-2">
            @if($canMarkReady)
                <a href="{{ route('pos.station-report', ['station' => $station]) }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fa fa-chart-bar me-1"></i>View report
                </a>
            @endif
            <a href="{{ route('pos.home') }}" class="btn btn-outline-secondary btn-sm">POS</a>
        </div>
    </div>

    @if(count($orders) === 0)
        <div class="alert alert-info mb-0 station-text">No orders for this station right now.</div>
    @else
        <div class="row g-3">
            @foreach($orders as $order)
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="card station-card h-100 {{ $order['all_ready'] ? 'border-success bg-light' : 'border-warning' }}">
                        <div class="card-header d-flex justify-content-between align-items-center {{ $order['all_ready'] ? 'bg-success text-white' : 'bg-warning text-dark' }}">
                            <span class="station-order-title">Order #{{ $order['id'] }}</span>
                            <span class="station-order-meta">Table {{ $order['table_number'] }} · {{ $order['created_at'] }}</span>
                        </div>
                        <div class="card-body">
                            <p class="mb-2 station-waiter"><i class="fa fa-user me-1"></i>Waiter: <strong>{{ $order['waiter_name'] }}</strong></p>
                            <ul class="list-unstyled mb-3">
                                @foreach($order['items'] as $item)
                                    <li class="py-2 border-bottom border-light station-item d-flex justify-content-between align-items-start">
                                        <span>
                                            @if(!empty($item['voided_at']))
                                                <s><span class="fw-bold">{{ $item['quantity'] }}×</span> {{ $item['name'] }}</s>
                                                <div class="small text-danger">Voided by {{ $item['voided_by_name'] ?? '—' }}</div>
                                            @else
                                                <span class="fw-bold">{{ $item['quantity'] }}×</span> {{ $item['name'] }}
                                            @endif
                                            @php
                                                $opts = $item['selected_options'] ?? [];
                                                $over = $item['ingredient_overrides'] ?? [];
                                                $parts = [];
                                                if (!empty($opts['temperature']) && $opts['temperature'] !== 'default') {
                                                    $parts[] = ucfirst($opts['temperature']);
                                                }
                                                if (!empty($opts['sugar']) && $opts['sugar'] !== 'default') {
                                                    $parts[] = $opts['sugar'] === 'no'
                                                        ? 'No sugar'
                                                        : ($opts['sugar'] === 'less' ? 'Less sugar' : 'Extra sugar');
                                                }
                                                if (!empty($opts['ice']) && $opts['ice'] !== 'default') {
                                                    $parts[] = $opts['ice'] === 'no' ? 'No ice' : 'Extra ice';
                                                }
                                                if (!empty($over['no'] ?? [])) {
                                                    foreach ($over['no'] as $noIng) {
                                                        $parts[] = 'No ' . $noIng;
                                                    }
                                                }
                                            @endphp
                                            @if(count($parts) > 0 || !empty($item['notes']))
                                                <div class="small text-info mt-0">
                                                    @if(count($parts) > 0)
                                                        <div><i class="fa fa-sliders-h me-1"></i>{{ implode(', ', $parts) }}</div>
                                                    @endif
                                                    @if(!empty($item['notes']))
                                                        <div><i class="fa fa-comment me-1"></i>{{ $item['notes'] }}</div>
                                                    @endif
                                                </div>
                                            @endif
                                            @if(!empty($item['voided_at']) || ($item['preparation_status'] ?? '') === 'ready')
                                                @if(($item['preparation_status'] ?? '') === 'ready')
                                                    <span class="badge bg-success ms-1">Done</span>
                                                @endif
                                            @elseif($canMarkReady)
                                                <button type="button" class="btn btn-sm btn-success ms-1" wire:click="markItemReady({{ $item['id'] }})" title="Mark as ready"><i class="fa fa-check-circle"></i></button>
                                            @endif
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                            @if(!$order['all_ready'] && $canMarkReady)
                                <button type="button" class="btn btn-success btn-lg w-100 station-confirm-btn" wire:click="markOrderReady({{ $order['id'] }})">
                                    <i class="fa fa-check-circle me-2"></i>Mark all ready
                                </button>
                            @elseif(!$order['all_ready'])
                                <p class="small text-muted mb-0">Only station staff can mark items ready.</p>
                            @else
                                <div class="text-center text-success station-text"><i class="fa fa-check-circle me-1"></i>Completed</div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="mt-3 small text-muted">
        @foreach($activeStations as $slug => $label)
            @if($slug !== $station)
                <a href="{{ route('pos.station', ['station' => $slug]) }}">{{ $label }}</a>
                @if(!$loop->last) · @endif
            @endif
        @endforeach
    </div>

    <style>
    .station-display .station-title { font-size: 1.5rem; }
    .station-display .station-badge { font-size: 1.1rem; }
    .station-display .station-card .card-header { font-size: 1.15rem; }
    .station-display .station-order-title { font-weight: 700; }
    .station-display .station-order-meta { font-size: 0.95rem; opacity: 0.95; }
    .station-display .station-waiter { font-size: 1rem; }
    .station-display .station-item { font-size: 1.1rem; }
    .station-display .station-confirm-btn { font-size: 1.2rem; padding: 0.6rem 1rem; }
    .station-display .station-text { font-size: 1rem; }
    @media (min-width: 1200px) {
        .station-display .station-title { font-size: 1.75rem; }
        .station-display .station-item { font-size: 1.2rem; }
        .station-display .station-confirm-btn { font-size: 1.35rem; }
    }
    </style>
</div>
