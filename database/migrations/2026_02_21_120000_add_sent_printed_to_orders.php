<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->timestamp('sent_to_station_at')->nullable()->after('preparation_ready_at');
            $table->timestamp('printed_at')->nullable()->after('sent_to_station_at');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('order_ticket_printed_at')->nullable()->after('order_status');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['sent_to_station_at', 'printed_at']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('order_ticket_printed_at');
        });
    }
};
