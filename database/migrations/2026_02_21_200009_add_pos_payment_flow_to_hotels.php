<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payment flow: waiter_collects_cashier_confirms | waiter_collects_and_confirms | cashier_only.
     * Controlled via permissions as well; this is the hotel default.
     */
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->string('pos_payment_flow', 50)->default('waiter_collects_cashier_confirms')
                ->after('pos_enforce_stock_on_payment')
                ->comment('waiter_collects_cashier_confirms, waiter_collects_and_confirms, cashier_only');
        });
    }

    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropColumn('pos_payment_flow');
        });
    }
};
