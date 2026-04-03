<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Redirect root to login
Route::get('/', function () {
    return redirect()->route('login');
});

// Public booking pages (no auth)
Route::get('/booking/{slug}', \App\Livewire\Public\BookingHome::class)->name('public.booking')->where('slug', '[a-z0-9]+');
Route::get('/booking/{slug}/room/{roomSlug}', \App\Livewire\Public\RoomDetail::class)->name('public.room')->where('slug', '[a-z0-9]+')->where('roomSlug', '[a-z0-9\-]+');
Route::get('/booking/{slug}/reservation', \App\Livewire\Public\ReservationForm::class)->name('public.reservation')->where('slug', '[a-z0-9]+');

// Self-check-in / pre-arrival registration (no auth) – guests use link or scan QR
Route::get('/welcome', \App\Livewire\Public\SelfCheckIn::class)->name('welcome');

// Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', function () {
        return view('auth.login');
    })->name('login');

    Route::get('/register', function () {
        return view('auth.register');
    })->name('register');

    Route::get('/forgot-password', \App\Livewire\Auth\ForgotPassword::class)->name('password.request');
    Route::get('/reset-password/{token}', \App\Livewire\Auth\ResetPassword::class)->name('password.reset');
});

// Logout Route (clears role-switch state)
Route::post('/logout', function () {
    request()->session()->forget('acting_as_role_id');
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect()->route('login');
})->middleware('auth')->name('logout');

// Ireme (platform) dashboard – Super Admin & Accountant
Route::middleware(['auth', 'ireme'])->prefix('ireme')->name('ireme.')->group(function () {
    Route::get('/dashboard', \App\Livewire\Ireme\IremeDashboard::class)->name('dashboard');
    Route::get('/branding', \App\Livewire\Ireme\IremeBranding::class)->name('branding');
    Route::get('/account', \App\Livewire\Ireme\IremeAccount::class)->name('account');
    Route::get('/hotels', \App\Livewire\Ireme\IremeHotels::class)->name('hotels.index');
    Route::get('/hotels/create', \App\Livewire\Ireme\IremeHotelForm::class)->name('hotels.create');
    Route::get('/hotels/{hotel}/edit', \App\Livewire\Ireme\IremeHotelForm::class)->name('hotels.edit');
    Route::get('/hotels/{hotel}/users', \App\Livewire\Ireme\IremeHotelUsers::class)->name('hotels.users');
    Route::get('/hotels/{hotel}/users/{user}/permissions', \App\Livewire\Ireme\IremeUserPermissions::class)->name('hotels.users.permissions');
    Route::get('/hotels/{hotel}/rooms', \App\Livewire\Ireme\IremeHotelRooms::class)->name('hotels.rooms');
    Route::get('/hotels/{hotel}/menu-items', \App\Livewire\Ireme\IremeHotelMenuItems::class)->name('hotels.menu-items');
    Route::get('/hotels/{hotel}/additional-charges', \App\Livewire\Ireme\IremeHotelAdditionalCharges::class)->name('hotels.additional-charges');
    Route::get('/subscriptions', \App\Livewire\Ireme\IremeSubscriptions::class)->name('subscriptions.index');
    Route::get('/subscriptions/{hotel}', \App\Livewire\Ireme\IremeSubscriptionShow::class)->name('subscriptions.show');
    Route::get('/invoices', \App\Livewire\Ireme\IremeInvoices::class)->name('invoices.index');
    Route::get('/requests', \App\Livewire\Ireme\IremeRequests::class)->name('requests.index');
    Route::get('/subscription-invoice/{invoice}', function (App\Models\SubscriptionInvoice $invoice) {
        return view('subscription-invoice', ['invoice' => $invoice->load('hotel')]);
    })->name('subscription-invoice.show');
});

// Super Admin only: switch "view as" role (no logout required; one role at a time)
Route::post('/switch-role', function () {
    if (! Auth::user()->isSuperAdmin()) {
        abort(403, 'Only Super Admin can switch view-as role.');
    }
    $roleId = request()->input('role_id');
    if ($roleId === null || $roleId === '') {
        request()->session()->forget('acting_as_role_id');
    } else {
        $role = \App\Models\Role::find($roleId);
        if (! $role) {
            return redirect()->back()->with('error', 'Invalid role.');
        }
        request()->session()->put('acting_as_role_id', $role->id);
    }
    // Reset sidebar context so the new role sees only its own module menu
    request()->session()->forget('selected_module');
    return redirect()->route('dashboard');
})->middleware('auth')->name('switch-role');

// Protected Routes – Hotel app (hotel users only)
Route::middleware(['auth', 'hotel'])->group(function () {
    Route::get('/dashboard', \App\Livewire\Dashboard::class)->name('dashboard');
    // Manager / director / GM / hotel-admin: tablet-friendly hub landing pages (see sidebar when canNavigateModules)
    Route::get('/pos/hub', \App\Livewire\Navigation\PosHub::class)->name('pos.hub');
    Route::get('/stock/hub', \App\Livewire\Navigation\StockHub::class)->name('stock.hub');
    Route::get('/back-office/hub', \App\Livewire\Navigation\BackOfficeHub::class)->name('back-office.hub');
    Route::get('/hotel-settings/hub', \App\Livewire\Navigation\HotelSettingsHub::class)->name('hotel-settings.hub');
    Route::get('/account/hub', \App\Livewire\Navigation\AccountHub::class)->name('account.hub');
    Route::get('/accountant/purchases-hub', \App\Livewire\Navigation\AccountantPurchasesHub::class)->name('accountant.purchases.hub');
    Route::get('/accountant/requisitions-hub', \App\Livewire\Navigation\AccountantRequisitionsHub::class)->name('accountant.requisitions.hub');
    Route::get('/accountant/general-report-hub', \App\Livewire\Navigation\AccountantGeneralReportHub::class)->name('accountant.general-report.hub');
    Route::get('/accountant/communications-hub', \App\Livewire\Navigation\AccountantCommunicationsHub::class)->name('accountant.communications.hub');
    Route::get('/accountant/pos-hub', \App\Livewire\Navigation\AccountantPosHub::class)->name('accountant.pos.hub');
    Route::get('/accountant/front-office-hub', \App\Livewire\Navigation\AccountantFrontOfficeHub::class)->name('accountant.front-office.hub');
    Route::get('/accountant/stock-hub', \App\Livewire\Navigation\AccountantStockHub::class)->name('accountant.stock.hub');
    Route::get('/subscription', \App\Livewire\HotelSubscription::class)->name('subscription');
    Route::get('/system-configuration', \App\Livewire\SystemConfiguration::class)->name('system.configuration'); // Super Admin only
    Route::get('/hotel-details', \App\Livewire\HotelDetails::class)->name('hotel-details'); // Super Admin only
    Route::get('/reset-actions', \App\Livewire\ResetActions::class)->name('reset-actions'); // Super Admin only
    Route::get('/approvals', \App\Livewire\ReviewsModeration::class)->name('approvals'); // Super Admin + Manager
    Route::get('/shift-management', \App\Livewire\ShiftManagement::class)->name('shift.management');
    Route::get('/stock-management', \App\Livewire\StockManagement::class)->name('stock.management');
    Route::get('/stock-dashboard', \App\Livewire\StockDashboard::class)->name('stock.dashboard');
    Route::get('/stock-movements', \App\Livewire\StockMovements::class)->name('stock.movements');
    Route::get('/stock-out', \App\Livewire\StockOut::class)->name('stock.out');
    Route::get('/stock-reports', \App\Livewire\StockReports::class)->name('stock.reports');
    Route::get('/stock-opening-closing-report', \App\Livewire\StockOpeningClosingReport::class)->name('stock.opening-closing-report');
    Route::get('/stock-requisitions', \App\Livewire\StockRequisitions::class)->name('stock.requisitions');
    Route::get('/stock-requests', \App\Livewire\StockRequests::class)->name('stock.requests');
    Route::get('/stock-locations', \App\Livewire\StockLocationManagement::class)->name('stock.locations');
    Route::get('/pending-stock-deductions', \App\Livewire\PendingStockDeductions::class)->name('stock.pending-deductions');
    Route::get('/purchase-requisitions', \App\Livewire\PurchaseRequisitions::class)->name('purchase.requisitions');
    Route::get('/goods-receipts', \App\Livewire\GoodsReceipts::class)->name('goods.receipts');
    Route::get('/suppliers', \App\Livewire\SuppliersManagement::class)->name('suppliers.index');
    Route::get('/users', \App\Livewire\UsersCrud::class)->name('users.index');
    Route::get('/permission-requests', \App\Livewire\PermissionRequestApprovals::class)->name('permission-requests.index');
    Route::get('/departments', \App\Livewire\DepartmentsManagement::class)->name('departments.index');
    Route::get('/profile', \App\Livewire\UserProfile::class)->name('profile');
    Route::get('/activity-log', \App\Livewire\ActivityLogViewer::class)->name('activity-log');

    // Phase 5: POS / Menu (Sellable items, Bill of Menu)
    Route::get('/menu-item-types', \App\Livewire\MenuItemTypesManagement::class)->name('menu.item-types');
    Route::get('/menu-categories', \App\Livewire\MenuCategoriesManagement::class)->name('menu.categories');
    Route::get('/menu-items', \App\Livewire\MenuManagement::class)->name('menu.items');
    Route::get('/bill-of-menu', \App\Livewire\BillOfMenuManagement::class)->name('menu.bill-of-menu');

    // Phase 6: POS & Sales (Restaurant operations)
    Route::get('/pos', \App\Livewire\Pos\PosHome::class)->name('pos.home');
    Route::get('/pos/products', \App\Livewire\Pos\PosProducts::class)->name('pos.products');
    Route::get('/pos/orders', \App\Livewire\Pos\PosOrders::class)->name('pos.orders');
    Route::get('/pos/tables', \App\Livewire\Pos\RestaurantTablesManagement::class)->name('pos.tables');
    Route::get('/pos/my-sales', \App\Livewire\Pos\PosMySales::class)->name('pos.my-sales');
    Route::get('/pos/payment/{invoice}', \App\Livewire\Pos\PosPayment::class)->name('pos.payment');
    Route::get('/pos/receipt/{order}', \App\Livewire\Pos\PosReceipt::class)->name('pos.receipt');
    Route::get('/pos/reports', \App\Livewire\Pos\PosReports::class)->name('pos.reports');
    Route::get('/pos/order-history', \App\Livewire\Pos\PosOrderHistory::class)->name('pos.order-history');
    Route::get('/pos/aging-orders', \App\Livewire\Pos\PosAgingOrders::class)->name('pos.aging-orders');
    Route::get('/pos/orders-stations-overview', \App\Livewire\Pos\PosOrdersStationsOverview::class)->name('pos.orders-stations-overview');
    Route::get('/pos/void-requests', \App\Livewire\Pos\PosVoidRequests::class)->name('pos.void-requests');
    Route::get('/pos/receipt-modification-requests', \App\Livewire\Pos\PosReceiptModificationRequests::class)->name('pos.receipt-modification-requests');
    Route::get('/pos/station/{station}', \App\Livewire\Pos\PosStationDisplay::class)->name('pos.station')->where('station', '[a-z0-9_]+');
    Route::get('/pos/station/{station}/report', \App\Livewire\Pos\PosStationReport::class)->name('pos.station-report')->where('station', '[a-z0-9_]+');

    // Restaurant Manager: Waiters list, Preparation & posting stations
    Route::get('/restaurant/waiters', \App\Livewire\RestaurantWaiters::class)->name('restaurant.waiters');
    Route::get('/restaurant/preparation-stations', \App\Livewire\PreparationStationsManagement::class)->name('restaurant.preparation-stations');
    Route::get('/restaurant/posting-stations', \App\Livewire\PreparationStationsManagement::class)->name('restaurant.posting-stations');
    
    // Front Office - Dashboard, Add Reservation & Rooms management
    Route::get('/front-office/hub', \App\Livewire\FrontOffice\FrontOfficeHub::class)->name('front-office.hub');
    Route::get('/front-office/dashboard', \App\Livewire\FrontOffice\FrontOfficeDashboard::class)->name('front-office.dashboard');
    Route::get('/front-office/add-reservation', \App\Livewire\FrontOffice\AddReservation::class)->name('front-office.add-reservation');
    Route::get('/front-office/reservations', \App\Livewire\FrontOffice\ReservationsList::class)->name('front-office.reservations');
    Route::get('/front-office/reservation-details/{reservation}', \App\Livewire\FrontOffice\ReservationDetails::class)->name('front-office.reservation-details');
    Route::get('/room-types', \App\Livewire\FrontOffice\RoomTypesManagement::class)->name('room-types.index');
    Route::get('/rooms', function () {
        return redirect()->route('room-types.index');
    })->name('rooms.index');
    Route::get('/additional-charges', \App\Livewire\FrontOffice\AdditionalChargesManagement::class)->name('additional-charges.index');
    Route::get('/amenities', \App\Livewire\FrontOffice\AmenitiesManagement::class)->name('amenities.index');
    Route::get('/front-office/rooms', \App\Livewire\FrontOffice\FrontOfficeRooms::class)->name('front-office.rooms');
    Route::get('/front-office/reports', \App\Livewire\FrontOffice\FrontOfficeReports::class)->name('front-office.reports');
    Route::get('/front-office/guests-report', \App\Livewire\FrontOffice\GuestsReport::class)->name('front-office.guests-report');
    Route::get('/front-office/guests-report/export', \App\Http\Controllers\FrontOffice\GuestsReportExportController::class)->name('front-office.guests-report.export');
    Route::get('/front-office/guests-report/print', \App\Http\Controllers\FrontOffice\GuestsReportPrintController::class)->name('front-office.guests-report.print');
    Route::get('/front-office/daily-accommodation-report', \App\Livewire\FrontOffice\DailyAccommodationReport::class)->name('front-office.daily-accommodation-report');
    Route::get('/front-office/daily-accommodation-report/print', \App\Http\Controllers\FrontOffice\DailyAccommodationReportPrintController::class)->name('front-office.daily-accommodation-report.print');

    // General monthly report (restaurant + rooms summary)
    Route::get('/front-office/general-report-settings', \App\Livewire\FrontOffice\HotelRevenueReportSettings::class)->name('front-office.general-report-settings');
    Route::get('/general-monthly-sales-summary', \App\Livewire\FrontOffice\GeneralMonthlySalesSummary::class)->name('general.monthly-sales-summary');

    // General daily report (restaurant + rooms summary for a single day)
    Route::get('/general-daily-sales-summary', \App\Livewire\FrontOffice\GeneralDailySalesSummary::class)->name('general.daily-sales-summary');
    Route::get('/front-office/reservation-payment-receipt-preview', \App\Http\Controllers\FrontOffice\ReservationPaymentReceiptController::class.'@preview')->name('front-office.reservation-payment-receipt.preview');
    Route::get('/front-office/reservation-payment-receipt/{payment}', \App\Http\Controllers\FrontOffice\ReservationPaymentReceiptController::class.'@print')->name('front-office.reservation-payment-receipt');
    Route::get('/front-office/hotel-settings', \App\Livewire\FrontOffice\FrontOfficeHotelSettings::class)->name('front-office-hotel-settings');
    Route::get('/front-office/quick-group-booking', \App\Livewire\FrontOffice\QuickGroupBooking::class)->name('front-office.quick-group-booking');
    Route::get('/front-office/self-registered', \App\Livewire\FrontOffice\SelfRegisteredList::class)->name('front-office.self-registered');
    Route::get('/front-office/communications', \App\Livewire\FrontOffice\FrontOfficeCommunications::class)->name('front-office.communications');
    Route::redirect('/front-office/guest-communications', '/front-office/communications')->name('front-office.guest-communications');

    Route::get('/front-office/proforma-invoices', \App\Livewire\FrontOffice\ProformaInvoicesIndex::class)->name('front-office.proforma-invoices');
    Route::get('/front-office/proforma-invoices/create', \App\Livewire\FrontOffice\ProformaInvoiceEdit::class)->name('front-office.proforma-invoices.create');
    Route::get('/front-office/proforma-invoices/{proformaInvoice}/edit', \App\Livewire\FrontOffice\ProformaInvoiceEdit::class)->name('front-office.proforma-invoices.edit');
    Route::get('/front-office/proforma-invoices/{proformaInvoice}/print', [\App\Http\Controllers\FrontOffice\ProformaInvoicePrintController::class, 'show'])->name('front-office.proforma-invoices.print');
    Route::get('/front-office/proforma-line-defaults', \App\Livewire\FrontOffice\ProformaLineDefaults::class)->name('front-office.proforma-line-defaults');
    Route::get('/front-office/wellness', \App\Livewire\FrontOffice\WellnessManagement::class)->name('front-office.wellness');

    // Subscription invoice (hotel users can view their own)
    Route::get('/subscription-invoice/{invoice}', function (App\Models\SubscriptionInvoice $invoice) {
        if (auth()->user()->hotel_id != $invoice->hotel_id) {
            abort(403);
        }
        return view('subscription-invoice', ['invoice' => $invoice->load('hotel')]);
    })->middleware('auth', 'hotel')->name('subscription.invoice.show');

    // Recovery & Credit Control
    Route::get('/recovery', \App\Livewire\Recovery\RecoveryDashboard::class)->name('recovery.dashboard');

    // Module routes - ModulePage component will check if module is enabled
    Route::get('/module/{module}', \App\Livewire\ModulePage::class)->name('module.show');
});
