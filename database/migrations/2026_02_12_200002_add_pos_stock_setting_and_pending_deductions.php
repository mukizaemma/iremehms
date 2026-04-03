<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->boolean('pos_enforce_stock_on_payment')->default(true)->after('allow_manual_close_business_day');
        });

        Schema::create('pending_stock_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignId('order_item_id')->constrained('order_items')->onDelete('cascade');
            $table->foreignId('stock_id')->constrained('stocks')->onDelete('cascade');
            $table->decimal('quantity_required', 12, 4);
            $table->decimal('quantity_available_at_sale', 12, 4)->default(0);
            $table->string('status', 20)->default('PENDING'); // PENDING, DEDUCTED, WRITTEN_OFF
            $table->timestamp('deducted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'order_id']);
            $table->index('stock_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_stock_deductions');
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropColumn('pos_enforce_stock_on_payment');
        });
    }
};
