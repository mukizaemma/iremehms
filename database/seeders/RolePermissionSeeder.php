<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = Role::where('slug', 'super-admin')->first();
        if ($superAdmin) {
            $superAdmin->permissions()->sync(Permission::pluck('id'));
        }

        $this->assignIremeAccountantPermissions();
        $this->assignByRoleSlug('manager', [
            'back_office_stock_items', 'back_office_rooms', 'back_office_menu_items', 'back_office_bom', 'back_office_stations', 'back_office_cost_margin',
            'pos_take_orders', 'pos_send_to_station', 'pos_request_void', 'pos_approve_void', 'pos_approve_receipt_modification', 'pos_confirm_payment', 'pos_print_receipt', 'pos_transfer_order', 'pos_post_to_room', 'pos_split_bill', 'pos_audit', 'pos_full_oversight', 'pos_view_station_orders', 'pos_mark_station_ready', 'pos_open_shift', 'pos_close_shift',
            'stock_create_requisition', 'stock_receive_goods', 'stock_issue', 'stock_request_internal', 'stock_approve_purchase', 'stock_audit', 'stock_logistics', 'stock_authorize_requests', 'stock_open_shift', 'stock_close_shift',
            'fo_availability', 'fo_create_reservation', 'fo_check_in_out', 'fo_open_shift', 'fo_close_shift', 'fo_view_guest_bills', 'fo_collect_payment', 'fo_post_charges',
            'fo_proforma_manage', 'fo_wellness_manage', 'fo_proforma_verify',
            'shift_open_global', 'shift_close_global',
            'recovery_view_unpaid', 'recovery_view_credits', 'recovery_view_room_charges', 'recovery_flag_accountability',
            'reports_costs_revenues', 'reports_margin', 'reports_financial_export', 'reports_view_all',
        ]);

        $this->assignByRoleSlug('controller', [
            'pos_audit', 'stock_audit', 'reports_view_all',
        ]);

        $this->assignByRoleSlug('accountant', [
            'reports_costs_revenues', 'reports_margin', 'reports_financial_export',
        ]);

        $this->assignByRoleSlug('waiter', [
            'pos_take_orders', 'pos_send_to_station', 'pos_request_void', 'pos_transfer_order', 'pos_post_to_room', 'pos_split_bill',
        ]);

        $this->assignByRoleSlug('barman', [
            'pos_send_to_station', 'pos_request_void', 'pos_mark_station_ready',
        ]);

        $this->assignByRoleSlug('cashier', [
            'pos_confirm_payment', 'pos_print_receipt', 'pos_split_bill',
        ]);

        $this->assignByRoleSlug('restaurant-manager', [
            'pos_take_orders', 'pos_send_to_station', 'pos_request_void', 'pos_approve_void', 'pos_confirm_payment', 'pos_print_receipt', 'pos_transfer_order', 'pos_post_to_room', 'pos_split_bill', 'pos_view_station_orders', 'pos_mark_station_ready', 'pos_open_shift', 'pos_close_shift',
        ]);

        $this->assignByRoleSlug('receptionist', [
            'fo_availability', 'fo_create_reservation', 'fo_check_in_out', 'fo_open_shift', 'fo_close_shift', 'fo_view_guest_bills', 'fo_collect_payment', 'fo_post_charges',
            'fo_guest_comms',
            'fo_proforma_manage', 'fo_wellness_manage',
        ]);

        $this->assignByRoleSlug('store-keeper', [
            'stock_receive_goods', 'stock_issue', 'stock_open_shift', 'stock_close_shift',
        ]);

        $this->assignByRoleSlug('purchaser', [
            'stock_create_requisition',
        ]);

        $this->assignByRoleSlug('supervisor', [
            'stock_request_internal',
        ]);

        $this->assignByRoleSlug('logistics', [
            'stock_receive_goods', 'stock_issue', 'stock_logistics', 'stock_open_shift', 'stock_close_shift', 'reports_costs_revenues',
        ]);

        $this->assignByRoleSlug('recovery', [
            'recovery_view_unpaid', 'recovery_view_credits', 'recovery_view_room_charges', 'recovery_flag_accountability',
        ]);

        $this->assignByRoleSlug('department-admin', [
            'back_office_stock_items', 'back_office_menu_items', 'back_office_bom', 'back_office_stations', 'back_office_cost_margin',
            'pos_take_orders', 'pos_send_to_station', 'pos_request_void', 'pos_approve_void', 'pos_confirm_payment', 'pos_print_receipt', 'pos_transfer_order', 'pos_post_to_room', 'pos_split_bill', 'pos_mark_station_ready', 'pos_open_shift', 'pos_close_shift',
            'stock_create_requisition', 'stock_receive_goods', 'stock_issue', 'stock_request_internal', 'stock_open_shift', 'stock_close_shift',
            'fo_availability', 'fo_create_reservation', 'fo_check_in_out', 'fo_open_shift', 'fo_close_shift', 'fo_view_guest_bills', 'fo_collect_payment', 'fo_post_charges',
            'fo_proforma_manage', 'fo_wellness_manage', 'fo_proforma_verify',
        ]);

        $this->assignByRoleSlug('admin-officer', [
            'fo_availability', 'fo_view_guest_bills', 'back_office_rooms', 'back_office_menu_items',
            'fo_guest_comms',
        ]);

        $this->assignByRoleSlug('pr-officer', [
            'fo_availability', 'fo_guest_comms',
        ]);

        $managerPerms = [
            'hotel_manage_users', 'hotel_configure_details', 'hotel_assign_roles',
            'back_office_stock_items', 'back_office_rooms', 'back_office_menu_items', 'back_office_bom', 'back_office_stations', 'back_office_cost_margin',
            'pos_take_orders', 'pos_send_to_station', 'pos_request_void', 'pos_approve_void', 'pos_approve_receipt_modification', 'pos_confirm_payment', 'pos_print_receipt', 'pos_transfer_order', 'pos_post_to_room', 'pos_split_bill', 'pos_audit', 'pos_full_oversight', 'pos_view_station_orders', 'pos_mark_station_ready', 'pos_open_shift', 'pos_close_shift',
            'stock_create_requisition', 'stock_receive_goods', 'stock_issue', 'stock_request_internal', 'stock_approve_purchase', 'stock_audit', 'stock_logistics', 'stock_authorize_requests', 'stock_open_shift', 'stock_close_shift',
            'fo_availability', 'fo_create_reservation', 'fo_check_in_out', 'fo_open_shift', 'fo_close_shift', 'fo_view_guest_bills', 'fo_collect_payment', 'fo_post_charges',
            'fo_proforma_manage', 'fo_wellness_manage', 'fo_proforma_verify',
            'shift_open_global', 'shift_close_global',
            'recovery_view_unpaid', 'recovery_view_credits', 'recovery_view_room_charges', 'recovery_flag_accountability',
            'reports_costs_revenues', 'reports_margin', 'reports_financial_export', 'reports_view_all',
        ];
        $this->assignByRoleSlug('hotel-admin', $managerPerms);
        $this->assignByRoleSlug('director', $managerPerms);
        $this->assignByRoleSlug('general-manager', $managerPerms);
    }

    private function assignIremeAccountantPermissions(): void
    {
        $role = Role::where('slug', 'ireme-accountant')->first();
        if (! $role) {
            return;
        }
        $ids = Permission::whereIn('slug', [
            'ireme_view_dashboard',
            'ireme_view_hotels',
            'ireme_view_subscriptions',
            'ireme_view_payments',
            'ireme_invoices_generate',
            'ireme_invoices_send',
            'ireme_confirm_payments',
            'ireme_view_requests',
        ])->pluck('id')->all();
        $role->permissions()->sync($ids);
    }

    private function assignByRoleSlug(string $roleSlug, array $permissionSlugs): void
    {
        $role = Role::where('slug', $roleSlug)->first();
        if (! $role) {
            return;
        }
        $ids = Permission::whereIn('slug', $permissionSlugs)->pluck('id')->all();
        $role->permissions()->sync($ids);
    }
}
