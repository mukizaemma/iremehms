<?php

namespace App\Livewire\Pos;

use App\Models\PosSession;
use App\Models\RestaurantTable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Database\QueryException;
use Livewire\Component;

class RestaurantTablesManagement extends Component
{
    public $tables = [];
    public $showForm = false;
    public $editingId = null;
    public $table_number = '';
    public $capacity = '';
    public $is_active = true;
    public bool $canManageTables = false;

    public function mount()
    {
        $user = Auth::user();
        $this->canManageTables = (bool) ($user
            && ($user->isSuperAdmin()
                || $user->isManager()
                || $user->isRestaurantManager()
                || $user->hasPermission('pos_full_oversight')
            )
        );
        if (! $this->canManageTables) {
            abort(403, 'You are not allowed to manage restaurant tables.');
        }
        $this->loadTables();
    }

    protected function requireSession()
    {
        if (!PosSession::getOpenForUser(Auth::id())) {
            session()->flash('error', 'Open a POS session first.');
            return $this->redirect(route('pos.home'), navigate: true);
        }
    }

    public function loadTables()
    {
        $this->tables = RestaurantTable::with(['activeOrder.waiter', 'activeOrder.invoice'])
            ->orderBy('table_number')
            ->get()
            ->map(function (RestaurantTable $t) {
                $active = $t->activeOrder;
                $total = null;
                if ($active) {
                    // Prefer invoice total if available, otherwise use order total accessor
                    if ($active->invoice) {
                        $total = (float) ($active->invoice->total_amount ?? 0);
                    } else {
                        $total = $active->total;
                    }
                }

                return [
                    'id' => $t->id,
                    'table_number' => $t->table_number,
                    'capacity' => $t->capacity,
                    'is_active' => $t->is_active,
                    // Occupancy / active order info
                    'is_occupied' => $active !== null,
                    'active_order_id' => $active?->id,
                    'active_order_status' => $active?->order_status,
                    'active_order_waiter' => $active?->waiter->name ?? null,
                    'active_order_total' => $total,
                    'active_order_time' => $active?->created_at?->format('H:i'),
                ];
            })
            ->toArray();
    }

    public function openForm($id = null)
    {
        if (! $this->canManageTables) {
            abort(403, 'You are not allowed to manage restaurant tables.');
        }
        $this->editingId = $id;
        if ($id) {
            $t = RestaurantTable::find($id);
            $this->table_number = $t->table_number;
            $this->capacity = $t->capacity ?? '';
            $this->is_active = $t->is_active;
        } else {
            $this->table_number = '';
            $this->capacity = '';
            $this->is_active = true;
        }
        $this->showForm = true;
    }

    public function closeForm()
    {
        $this->showForm = false;
        $this->editingId = null;
    }

    public function save()
    {
        if (! $this->canManageTables) {
            abort(403, 'You are not allowed to manage restaurant tables.');
        }
        $this->validate([
            'table_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('restaurant_tables', 'table_number')
                    ->ignore($this->editingId),
            ],
            'capacity' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
        ], [
            'table_number.unique' => 'That table number already exists. Please use a different value.',
        ]);

        $data = [
            'table_number' => $this->table_number,
            'capacity' => $this->capacity ?: null,
            'is_active' => $this->is_active,
        ];

        try {
            if ($this->editingId) {
                RestaurantTable::findOrFail($this->editingId)->update($data);
                session()->flash('message', 'Table updated.');
            } else {
                RestaurantTable::create($data);
                session()->flash('message', 'Table added.');
            }
        } catch (QueryException $e) {
            // Race-condition protection: if another user inserted the same table number.
            if ((string) $e->getCode() === '23000' && str_contains(strtolower($e->getMessage()), 'duplicate entry')) {
                session()->flash('error', 'That table number already exists. Please use a different value.');
                $this->loadTables();
                return;
            }
            throw $e;
        }
        $this->loadTables();
        $this->closeForm();
    }

    public function delete($id)
    {
        if (! $this->canManageTables) {
            abort(403, 'You are not allowed to manage restaurant tables.');
        }
        $t = RestaurantTable::find($id);
        if ($t->orders()->whereIn('order_status', ['OPEN', 'CONFIRMED'])->exists()) {
            session()->flash('error', 'Cannot delete table with open or confirmed orders.');
            return;
        }
        $t->delete();
        session()->flash('message', 'Table deleted.');
        $this->loadTables();
    }

    public function render()
    {
        return view('livewire.pos.restaurant-tables-management')->layout('livewire.layouts.app-layout');
    }
}
