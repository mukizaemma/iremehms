# Ireme HMS 4-Star Revamp – Implemented vs Not Yet Solved

This document lists what was **implemented** in the revamp (migrations, models, seeders, Recovery module) and what **remains to be done** (UI enforcement, workflows, and optional features) as per the client blueprint.

---

## 1. Implemented (Database & Structure)

### 1.1 Roles & Access (Blueprint §2)

- **`permissions`** table and **`Permission`** model with slug, module_slug, description.
- **`role_permissions`** pivot and **`Role::permissions()`** — granular permissions; no hardcoded role checks for sensitive actions.
- **`role_departments`** table and **`RoleDepartment`** model for Controller **scope** (GLOBAL vs DEPARTMENT). Structure is in place; assignment and use in queries are not yet wired in UI/config.
- **`User::hasPermission(string $permissionSlug)`** — checks effective role’s permissions; Super Admin has all.
- **RoleSeeder** extended with: Controller, Accountant, Barman, Restaurant Manager, Purchaser, Supervisor, Logistics, Recovery, Admin Officer.
- **PermissionSeeder** + **RolePermissionSeeder** — full set of permissions (POS, Stock, FO, Recovery, Back Office, Reports) and assignment to roles. **Selling is permission-based** (e.g. `pos_take_orders`, `pos_confirm_payment`); default is no selling without permission.

### 1.2 Module Architecture (Blueprint §1)

- **Back Office** and **Recovery** modules added in **ModuleSeeder** with correct role assignments.
- Modules remain enable/restrict per hotel via `Hotel::enabled_modules`.

### 1.3 Preparation Stations (Blueprint §4.2)

- **`preparation_stations`** table and **`PreparationStation`** model (name, slug, display_order, is_active).
- **`menu_item_station`** pivot — many-to-many **menu_item ↔ preparation_station**.
- **`MenuItem::preparationStations()`** relation.
- **PreparationStationSeeder** — seeds Kitchen, Bar, Coffee Station, Grill, Pastry.
- Legacy **`menu_items.preparation_station`** (string) still exists; UI can prefer **menu_item_station** and fall back to the column.

### 1.4 POS – Data Model (Blueprint §4)

- **`order_transfer_logs`** — from_user_id, to_user_id, reason; **`OrderTransferLog`** model; **`Order::transferLogs()`**.
- **`order_item_void_requests`** — requested_by_id, approved_by_id, reason, status (pending/approved/rejected); **`OrderItemVoidRequest`** model; **`OrderItem::voidRequests()`**.
- **Invoices extended**: `reservation_id`, `room_id`, `charge_type` (pos/room), `parent_invoice_id`, `split_type` (null/equal/custom), `posted_by_id`. **`Invoice::isRoomCharge()`**, **`postedBy()`**, **`reservation()`**, **`childInvoices()`**.
- **`hotels.pos_payment_flow`** — `waiter_collects_cashier_confirms` | `waiter_collects_and_confirms` | `cashier_only` (default: waiter_collects_cashier_confirms).
- **`hotels.order_slip_hide_price`** — when true, order slips do not show price (Blueprint §4.3).

### 1.5 Menu Costing (Blueprint §5.4)

- **`menu_items.menu_cost`**, **`cost_extra`**, **`margin_percent`** — structure for “Menu Cost = Ingredient + Extra; Selling = Cost + Margin”. **`MenuItem`** fillable and casts updated.

### 1.6 Recovery Module (Blueprint §7)

- **Recovery** module and **Recovery** role; **RecoveryDashboard** Livewire component:
  - Tabs: Unpaid, Credits, Room charges (unpaid).
  - Lists invoices with **posted by**, waiter, guest/reservation where applicable.
- Route: **`/recovery`** and **`/module/recovery`** (via ModulePage).

---

## 2. Not Solved / To Be Done (Implementation Guidance)

### 2.1 Role & Department Scope (Controller)

- **`role_departments`** data is not seeded (e.g. Controller global vs per-department). Need:
  - Seeder or UI to create **RoleDepartment** rows (scope = global or department, and department_id when department).
  - Wherever “Controller” access is used (reports, audit lists), **filter by department** when the role has department scope (using current user’s department or role_departments).

### 2.2 POS – Permission Enforcement in UI

- **Permission checks in Livewire**: Before allowing “take order”, “confirm payment”, “void”, “post to room”, “split bill”, call **`Auth::user()->hasPermission('pos_take_orders')`** (and corresponding slugs). Hide or disable buttons/links when the user lacks the permission.
- **Selling**: Ensure no one can complete a sale without **`pos_confirm_payment`** (and optionally **`pos_take_orders`** for adding items). Default = no selling unless permission granted.

### 2.3 POS – Order Flow & Stations

- **Sending to stations**: When a waiter adds an item, resolve stations from **`MenuItem::preparationStations()`** (or legacy `preparation_station`). Send/print only to those stations (existing station display/print logic to be wired to this mapping).
- **Order slip vs receipt**: Use **`Hotel::order_slip_hide_price`** when rendering order slips (kitchen/bar slips); receipts can continue to show price as per existing receipt settings.

### 2.4 POS – Order Transfer (Blueprint §4.4)

- When changing waiter (transfer):
  - Update **`Order::waiter_id`** to the new waiter.
  - Create **`OrderTransferLog`** with from_user_id, to_user_id, reason (required field in UI).
  - No item loss; only ownership change.

### 2.5 POS – Split Bills (Blueprint §4.5)

- **Equal split**: Create N child invoices (or N separate invoices for the order) with **`parent_invoice_id`** and **`split_type = 'equal'**; divide total by N.
- **Custom split**: Allow user to assign amounts or items to each split; create one invoice per part with **`split_type = 'custom'** and **`parent_invoice_id`**.
- **Payment tracking**: Each split has its own **`payments`**; ensure POS payment page can handle multiple invoices per order.

### 2.6 POS – Void & Item Removal (Blueprint §4.6)

- **Workflow**: Waiter requests void → **create `OrderItemVoidRequest`** (status pending) → Authorized role (e.g. Restaurant Manager) approves/rejects → on approve, perform item removal and **stock reversal** (reverse any stock deduction already made for that line).
- **No silent deletes**: All voids go through **`order_item_void_requests`**; no after-payment voids without approval and log.
- **UI**: “Request void” button for waiter; “Approve/Reject void” list for manager; show who requested, who approved, reason.

### 2.7 POS – Payment Flow (Blueprint §4.7)

- **Respect `Hotel::pos_payment_flow`**:
  - **waiter_collects_cashier_confirms**: Waiter can receive payment but only Cashier can confirm (set **`payments.submitted_at`** / finalize).
  - **waiter_collects_and_confirms**: Waiter can both collect and confirm.
  - **cashier_only**: Only users with Cashier role (or `pos_confirm_payment`) can receive and confirm.
- Enforce in **PosPayment** (or equivalent) Livewire: check **`hasPermission('pos_confirm_payment')`** and hotel setting before allowing “Confirm payment”.

### 2.8 POS – Room Posting (Blueprint §4.8)

- **Flow**: “Post to room” → search by room number / guest name (reservation) → set **`Invoice::reservation_id`**, **`charge_type = 'room'`**, **`posted_by_id`**, optionally **`room_id`** (from reservation) → invoice status remains UNPAID (room).
- **Front Office**: At checkout, list all invoices where **`charge_type = 'room'`** and **`reservation_id`** = current guest (or room). Allow “Collect payment” for those invoices.
- **UI**: Implement “Post to room” in POS and “Guest folio” (all room + POS charges) in Front Office.

### 2.9 Front Office – Guest Bills (Blueprint §6)

- **Reception = final payment gate**: One place that shows **all** guest bills (accommodation + room-posted POS).
- **To do**: A “Guest folio” or “Checkout” view that:
  - Selects reservation (or current in-house guest).
  - Lists accommodation charges + all **invoices** with **`charge_type = 'room'`** and same **`reservation_id`** (or linked room).
  - Allows collecting payment for each or in total.

### 2.10 Recovery – Escalation (Blueprint §7)

- **Recovery role** can see who posted and department; “Escalation possible” is not yet implemented (e.g. flag for manager, status “escalated”, or simple note field). Optional: add **`recovery_notes`** or **`escalated_at`** on **invoices** or a separate **recovery_follow_ups** table.

### 2.11 Stock – Roles & Lifecycle (Blueprint §5)

- **Roles** (Purchaser, Storekeeper, Supervisor, Logistics, Manager, Controller) and **permissions** are seeded; **enforcement in Stock Livewire** is not yet done. Restrict:
  - Create PR → **stock_create_requisition**
  - Receive goods → **stock_receive_goods**
  - Issue stock → **stock_issue**
  - Request internal → **stock_request_internal**
  - Approve purchase → **stock_approve_purchase**
  - Audit → **stock_audit**
- **Restaurant stock request** (Barman requests → Supervisor approves → Storekeeper issues): Logic and UI for “internal request” from Restaurant/Bar and approval path can be aligned with existing requisition/approval flow and permissions.

### 2.12 Back Office – Single Entry (Blueprint §3)

- Back Office is a **module**; actual CRUD lives in existing pages (Menu, Rooms, Stock, Bill of Menu). **Optional**: A single “Back Office” landing that links to: Stock items, Rooms & room types, Menu items, Bill of Menu, Preparation stations, Cost & margin (menu items). No new CRUD required if existing pages are permission-restricted (e.g. **back_office_* ** permissions).

### 2.13 Audit Logging (Blueprint §8.3)

- **activity_logs** table exists. Ensure **all** of the following are logged (either via a logger or activity_log):
  - Order changes (item add/remove, quantity).
  - Order transfers (done when implementing §2.4).
  - Voids (done via **order_item_void_requests**; ensure one log entry per approve/reject).
  - Payments (already stored in **payments**; optional: explicit audit log on create/update).
  - Room postings (log when invoice is set to charge_type = room and reservation_id).

### 2.14 Housekeeping (Blueprint §1)

- **Housekeeping** module exists in seed; **no dedicated Housekeeping UI** (room status, cleaning status, assign tasks). Out of scope of this revamp but noted.

---

## 3. Summary

- **Done**: Permissions + role_permissions + role_departments; preparation_stations + menu_item_station; order transfer logs and void request tables; invoice room/split/posting fields; pos_payment_flow and order_slip_hide_price; menu costing fields; Recovery module and dashboard; new roles and permission assignment; User::hasPermission.
- **Remaining**: Use **permissions** in all POS/Stock/FO Livewire (hide/disable by permission); implement **void approval workflow** and **stock reversal**; implement **split bill** UI; implement **post to room** and **guest folio**; enforce **pos_payment_flow**; wire **Controller scope** (role_departments) in reports/audit; optional Recovery escalation and full audit logging as above.

Using this list, you can tick off each item as you implement it in the UI and business logic.
