<?php

namespace App\Livewire;

use App\Models\ActivityLog;
use App\Models\Hotel;
use App\Models\User;
use App\Support\ActivityLogModule;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithPagination;

class ActivityLogViewer extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public string $date_from = '';

    public string $date_to = '';

    /** @var int|string|null All users when empty (management only) */
    public $filter_user_id = null;

    /** @var string Module slug or empty = all modules */
    public string $filter_module = '';

    public string $search = '';

    protected $queryString = [
        'date_from' => ['except' => ''],
        'date_to' => ['except' => ''],
        'filter_user_id' => ['except' => null],
        'filter_module' => ['except' => ''],
        'search' => ['except' => ''],
    ];

    public function mount(): void
    {
        if (! Auth::user()) {
            abort(403);
        }

        $today = Hotel::getHotel() ? Hotel::getTodayForHotel() : now()->toDateString();
        if ($this->date_from === '') {
            $this->date_from = $today;
        }
        if ($this->date_to === '') {
            $this->date_to = $today;
        }

        if (! $this->isManagement() && ($this->filter_user_id !== null && $this->filter_user_id !== '')) {
            $this->filter_user_id = null;
        }
    }

    /** Managers, accountants, auditors — can see all staff and all modules. */
    protected function isManagement(): bool
    {
        $u = Auth::user();
        if (! $u) {
            return false;
        }

        return $u->isSuperAdmin()
            || $u->canNavigateModules()
            || $u->hasPermission('hotel_manage_users')
            || $u->hasPermission('reports_view_all')
            || $u->hasPermission('stock_audit');
    }

    public function setToday(): void
    {
        $today = Hotel::getHotel() ? Hotel::getTodayForHotel() : now()->toDateString();
        $this->date_from = $today;
        $this->date_to = $today;
        $this->resetPage();
    }

    public function setLast7Days(): void
    {
        $hotel = Hotel::getHotel();
        $end = $hotel ? Carbon::parse(Hotel::getTodayForHotel())->endOfDay() : now()->endOfDay();
        $start = $end->copy()->subDays(6)->startOfDay();
        $this->date_from = $start->toDateString();
        $this->date_to = $end->toDateString();
        $this->resetPage();
    }

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function updatedFilterUserId(): void
    {
        if (! $this->isManagement()) {
            $this->filter_user_id = null;

            return;
        }
        $this->resetPage();
    }

    public function updatedFilterModule(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $hotel = Hotel::getHotel();
        $userOptions = collect();
        $moduleLabels = ActivityLogModule::labels();
        $logs = new LengthAwarePaginator([], 0, 50, 1, [
            'path' => request()->url(),
            'pageName' => 'page',
        ]);
        $isManagement = $this->isManagement();

        if ($hotel && Schema::hasTable('activity_logs')) {
            $userOptions = User::query()
                ->where('hotel_id', $hotel->id)
                ->orderBy('name')
                ->get(['id', 'name']);

            $hotelUserIds = $userOptions->pluck('id');

            $q = ActivityLog::query()->with('user');

            if (Schema::hasColumn('activity_logs', 'hotel_id')) {
                $q->where(function ($q2) use ($hotel, $hotelUserIds) {
                    $q2->where('hotel_id', $hotel->id);
                    if ($hotelUserIds->isNotEmpty()) {
                        $q2->orWhere(function ($q3) use ($hotelUserIds) {
                            $q3->whereNull('hotel_id')->whereIn('user_id', $hotelUserIds);
                        });
                    }
                });
            } else {
                $q->whereIn('user_id', $hotelUserIds);
            }

            try {
                $from = Carbon::parse($this->date_from ?: Hotel::getTodayForHotel())->startOfDay();
                $to = Carbon::parse($this->date_to ?: $this->date_from)->endOfDay();
                if ($from->gt($to)) {
                    [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
                }
                $q->whereBetween('created_at', [$from, $to]);
            } catch (\Throwable $e) {
                $today = Hotel::getTodayForHotel();
                $q->whereDate('created_at', $today);
            }

            if (! $isManagement) {
                $q->where('user_id', (int) Auth::id());
            } elseif ($this->filter_user_id !== null && $this->filter_user_id !== '') {
                $q->where('user_id', (int) $this->filter_user_id);
            }

            $mod = trim($this->filter_module);
            if ($mod !== '' && Schema::hasColumn('activity_logs', 'module')) {
                $q->where('module', $mod);
            }

            $term = trim($this->search);
            if ($term !== '') {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(function ($q2) use ($like) {
                    $q2->where('action', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhere('model_type', 'like', $like);
                });
            }

            $logs = $q->orderByDesc('created_at')->paginate(50);
        }

        return view('livewire.activity-log-viewer', [
            'logs' => $logs,
            'userOptions' => $userOptions,
            'hotel' => $hotel,
            'isManagement' => $isManagement,
            'moduleLabels' => $moduleLabels,
        ])->layout('livewire.layouts.app-layout');
    }
}
