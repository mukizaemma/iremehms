<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Order slip (Bon de Commande) = internal preparation; Receipt = client document.
     * When true, order slips never show price (optional 4-star setting).
     */
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->boolean('order_slip_hide_price')->default(false)->after('receipt_momo_value')->comment('When true, order slips do not show price');
        });
    }

    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropColumn('order_slip_hide_price');
        });
    }
};
