<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->boolean('receipt_show_vat')->default(true)->after('pos_enforce_stock_on_payment');
            $table->text('receipt_thank_you_text')->nullable()->after('receipt_show_vat');
            $table->string('receipt_momo_label', 100)->nullable()->after('receipt_thank_you_text'); // e.g. "Momo Pay", "Phone", "MoMo Code"
            $table->string('receipt_momo_value', 100)->nullable()->after('receipt_momo_label');     // phone number or momo code
        });
    }

    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropColumn(['receipt_show_vat', 'receipt_thank_you_text', 'receipt_momo_label', 'receipt_momo_value']);
        });
    }
};
