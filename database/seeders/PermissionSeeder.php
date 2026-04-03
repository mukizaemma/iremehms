<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // System / Super Admin
            ['name' => 'Assign roles', 'slug' => 'assign_roles', 'module_slug' => 'settings', 'description' => 'Assign roles to users'],
            ['name' => 'Configure modules', 'slug' => 'configure_modules', 'module_slug' => 'settings', 'description' => 'Enable/restrict modules per hotel'],
            ['name' => 'Define hotel structure', 'slug' => 'define_hotel_structure', 'module_slug' => 'settings', 'description' => 'Define departments and hotel config'],

            // Hotel main users (Director, GM, Manager): manage team and hotel config within their hotel
            ['name' => 'Hotel: Manage users', 'slug' => 'hotel_manage_users', 'module_slug' => 'settings', 'description' => 'Add users, assign roles and permissions within the hotel'],
            ['name' => 'Hotel: Configure details', 'slug' => 'hotel_configure_details', 'module_slug' => 'settings', 'description' => 'Edit hotel details, branding, and settings'],
            ['name' => 'Hotel: Assign roles & permissions', 'slug' => 'hotel_assign_roles', 'module_slug' => 'settings', 'description' => 'Approve permission requests and assign roles to team members'],

            // Back Office
            ['name' => 'Manage stock items', 'slug' => 'back_office_stock_items', 'module_slug' => 'store', 'description' => 'CRUD stock items'],
            ['name' => 'Manage rooms and room types', 'slug' => 'back_office_rooms', 'module_slug' => 'front-office', 'description' => 'CRUD rooms and room types'],
            ['name' => 'Manage menu items', 'slug' => 'back_office_menu_items', 'module_slug' => 'restaurant', 'description' => 'CRUD menu items'],
            ['name' => 'Manage Bill of Menu', 'slug' => 'back_office_bom', 'module_slug' => 'restaurant', 'description' => 'CRUD Bill of Menu'],
            ['name' => 'Manage preparation stations', 'slug' => 'back_office_stations', 'module_slug' => 'restaurant', 'description' => 'CRUD preparation stations'],
            ['name' => 'Manage cost and margin', 'slug' => 'back_office_cost_margin', 'module_slug' => 'restaurant', 'description' => 'Cost & margin configuration'],

            // POS – Selling is permission-based; default = no selling
            ['name' => 'Take orders', 'slug' => 'pos_take_orders', 'module_slug' => 'restaurant', 'description' => 'Take orders and send to kitchen'],
            ['name' => 'Send to kitchen / station', 'slug' => 'pos_send_to_station', 'module_slug' => 'restaurant', 'description' => 'Send order to preparation station'],
            ['name' => 'Request void', 'slug' => 'pos_request_void', 'module_slug' => 'restaurant', 'description' => 'Request item void (requires approval)'],
            ['name' => 'Approve void', 'slug' => 'pos_approve_void', 'module_slug' => 'restaurant', 'description' => 'Approve/reject void requests'],
            ['name' => 'Approve receipt modification', 'slug' => 'pos_approve_receipt_modification', 'module_slug' => 'restaurant', 'description' => 'Approve/reject receipt modification requests (GM can always approve)'],
            ['name' => 'Confirm payment', 'slug' => 'pos_confirm_payment', 'module_slug' => 'restaurant', 'description' => 'Confirm payments and print receipt'],
            ['name' => 'Print receipt', 'slug' => 'pos_print_receipt', 'module_slug' => 'restaurant', 'description' => 'Print client receipt'],
            ['name' => 'Transfer order', 'slug' => 'pos_transfer_order', 'module_slug' => 'restaurant', 'description' => 'Transfer order to another waiter'],
            ['name' => 'Post to room', 'slug' => 'pos_post_to_room', 'module_slug' => 'restaurant', 'description' => 'Charge invoice to room'],
            ['name' => 'Split bill', 'slug' => 'pos_split_bill', 'module_slug' => 'restaurant', 'description' => 'Split bill (equal or custom)'],
            ['name' => 'Audit POS', 'slug' => 'pos_audit', 'module_slug' => 'restaurant', 'description' => 'Audit sales, shifts, variances'],
            ['name' => 'Full POS oversight', 'slug' => 'pos_full_oversight', 'module_slug' => 'restaurant', 'description' => 'Manager full oversight'],
            ['name' => 'View station orders', 'slug' => 'pos_view_station_orders', 'module_slug' => 'restaurant', 'description' => 'View orders at preparation/posting stations and tables overview'],
            ['name' => 'Mark station ready / View station report', 'slug' => 'pos_mark_station_ready', 'module_slug' => 'restaurant', 'description' => 'Mark items ready at station and view station report (all users)'],
            ['name' => 'Open shift', 'slug' => 'pos_open_shift', 'module_slug' => 'restaurant', 'description' => 'Open the POS operational shift (or legacy day shift when not using operational shifts)'],
            ['name' => 'Close shift', 'slug' => 'pos_close_shift', 'module_slug' => 'restaurant', 'description' => 'Close the current shift and its POS sessions'],
            ['name' => 'Front office: Open operational shift', 'slug' => 'fo_open_shift', 'module_slug' => 'front-office', 'description' => 'Open the Front office operational shift session'],
            ['name' => 'Front office: Close operational shift', 'slug' => 'fo_close_shift', 'module_slug' => 'front-office', 'description' => 'Close the Front office operational shift (comment required)'],
            ['name' => 'Store: Open operational shift', 'slug' => 'stock_open_shift', 'module_slug' => 'store', 'description' => 'Open the Store / stock operational shift session'],
            ['name' => 'Store: Close operational shift', 'slug' => 'stock_close_shift', 'module_slug' => 'store', 'description' => 'Close the Store operational shift (comment required)'],
            ['name' => 'Open global operational shift', 'slug' => 'shift_open_global', 'module_slug' => 'settings', 'description' => 'Open one shift for all modules (when hotel uses global scope)'],
            ['name' => 'Close global operational shift', 'slug' => 'shift_close_global', 'module_slug' => 'settings', 'description' => 'Close the global operational shift'],
            ['name' => 'Create purchase requisition', 'slug' => 'stock_create_requisition', 'module_slug' => 'store', 'description' => 'Purchaser: create PR'],
            ['name' => 'Receive goods', 'slug' => 'stock_receive_goods', 'module_slug' => 'store', 'description' => 'Storekeeper: receive and store'],
            ['name' => 'Issue stock', 'slug' => 'stock_issue', 'module_slug' => 'store', 'description' => 'Storekeeper: issue to department'],
            ['name' => 'Request internal stock', 'slug' => 'stock_request_internal', 'module_slug' => 'store', 'description' => 'Supervisor: request stock'],
            ['name' => 'Approve purchase', 'slug' => 'stock_approve_purchase', 'module_slug' => 'store', 'description' => 'Manager: approve purchases'],
            ['name' => 'Audit stock', 'slug' => 'stock_audit', 'module_slug' => 'store', 'description' => 'Controller: audit stock'],
            ['name' => 'Logistics oversight', 'slug' => 'stock_logistics', 'module_slug' => 'store', 'description' => 'Assets & valuation'],
            ['name' => 'Authorize stock requests', 'slug' => 'stock_authorize_requests', 'module_slug' => 'store', 'description' => 'Approve or reject transfer, issue, and item-edit requests from store'],
            ['name' => 'Approve Bar & restaurant requisitions', 'slug' => 'approve_bar_restaurant_requisitions', 'module_slug' => 'store', 'description' => 'Approve or reject Bar & Restaurant stock requisitions (items from main stock)'],

            // Front Office
            ['name' => 'Room availability', 'slug' => 'fo_availability', 'module_slug' => 'front-office', 'description' => 'View room availability'],
            ['name' => 'Create reservation', 'slug' => 'fo_create_reservation', 'module_slug' => 'front-office', 'description' => 'Create reservations'],
            ['name' => 'Check-in / Check-out', 'slug' => 'fo_check_in_out', 'module_slug' => 'front-office', 'description' => 'Check-in and check-out guests'],
            ['name' => 'View all guest bills', 'slug' => 'fo_view_guest_bills', 'module_slug' => 'front-office', 'description' => 'View rooms + POS charges'],
            ['name' => 'Collect payment', 'slug' => 'fo_collect_payment', 'module_slug' => 'front-office', 'description' => 'Collect payment at checkout'],
            ['name' => 'Post charges', 'slug' => 'fo_post_charges', 'module_slug' => 'front-office', 'description' => 'Post accommodation charges'],
            ['name' => 'Guest communications', 'slug' => 'fo_guest_comms', 'module_slug' => 'front-office', 'description' => 'View guest lists and send one-to-one or bulk communications (email/SMS).'],
            ['name' => 'Proforma invoices', 'slug' => 'fo_proforma_manage', 'module_slug' => 'front-office', 'description' => 'Create proforma quotations for groups and events; record post-event payments'],
            ['name' => 'Wellness services', 'slug' => 'fo_wellness_manage', 'module_slug' => 'front-office', 'description' => 'Manage wellness offerings and record visit, daily, or subscription payments'],
            ['name' => 'Verify proforma invoices', 'slug' => 'fo_proforma_verify', 'module_slug' => 'front-office', 'description' => 'Approve or reject proformas submitted by reception'],

            // Recovery
            ['name' => 'View unpaid invoices', 'slug' => 'recovery_view_unpaid', 'module_slug' => 'recovery', 'description' => 'See all unpaid invoices'],
            ['name' => 'View credits', 'slug' => 'recovery_view_credits', 'module_slug' => 'recovery', 'description' => 'See credit invoices'],
            ['name' => 'View room charges', 'slug' => 'recovery_view_room_charges', 'module_slug' => 'recovery', 'description' => 'See room-posted charges'],
            ['name' => 'Flag accountability', 'slug' => 'recovery_flag_accountability', 'module_slug' => 'recovery', 'description' => 'Flag who posted, follow up'],

            // Reports / Accountant
            ['name' => 'View costs and revenues', 'slug' => 'reports_costs_revenues', 'module_slug' => 'reports', 'description' => 'Accountant: costs & revenues'],
            ['name' => 'Profit margin analysis', 'slug' => 'reports_margin', 'module_slug' => 'reports', 'description' => 'Profit margin analysis'],
            ['name' => 'Financial exports', 'slug' => 'reports_financial_export', 'module_slug' => 'reports', 'description' => 'Prepare financial exports'],
            ['name' => 'View all reports', 'slug' => 'reports_view_all', 'module_slug' => 'reports', 'description' => 'Manager: view all reports'],

            // Ireme (platform) – Super Admin & Accountant
            ['name' => 'Ireme: View dashboard', 'slug' => 'ireme_view_dashboard', 'module_slug' => 'settings', 'description' => 'Access Ireme dashboard'],
            ['name' => 'Ireme: Onboard hotels', 'slug' => 'ireme_onboard_hotels', 'module_slug' => 'settings', 'description' => 'Create and edit hotels'],
            ['name' => 'Ireme: Manage hotel users', 'slug' => 'ireme_manage_hotel_users', 'module_slug' => 'settings', 'description' => 'Create hotel admin, director, GM and assign permissions'],
            ['name' => 'Ireme: Assign modules', 'slug' => 'ireme_assign_modules', 'module_slug' => 'settings', 'description' => 'Assign modules to hotels'],
            ['name' => 'Ireme: Manage subscriptions', 'slug' => 'ireme_manage_subscriptions', 'module_slug' => 'settings', 'description' => 'Set subscription type and status'],
            ['name' => 'Ireme: View hotels', 'slug' => 'ireme_view_hotels', 'module_slug' => 'settings', 'description' => 'View list of hotels'],
            ['name' => 'Ireme: View subscriptions', 'slug' => 'ireme_view_subscriptions', 'module_slug' => 'settings', 'description' => 'View subscription details'],
            ['name' => 'Ireme: View payments', 'slug' => 'ireme_view_payments', 'module_slug' => 'settings', 'description' => 'View payment details'],
            ['name' => 'Ireme: Generate invoices', 'slug' => 'ireme_invoices_generate', 'module_slug' => 'settings', 'description' => 'Generate invoices for hotels'],
            ['name' => 'Ireme: Send invoices', 'slug' => 'ireme_invoices_send', 'module_slug' => 'settings', 'description' => 'Send invoices to hotels'],
            ['name' => 'Ireme: Confirm payments', 'slug' => 'ireme_confirm_payments', 'module_slug' => 'settings', 'description' => 'Confirm payments from hotels'],
            ['name' => 'Ireme: View requests', 'slug' => 'ireme_view_requests', 'module_slug' => 'settings', 'description' => 'View requests from hotels'],
        ];

        foreach ($permissions as $p) {
            Permission::updateOrCreate(['slug' => $p['slug']], $p);
        }
    }
}
