<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional printer config for posting: each station can have a printer for order tickets.
     */
    public function up(): void
    {
        Schema::table('preparation_stations', function (Blueprint $table) {
            $table->boolean('has_printer')->default(false)->after('is_active');
            $table->string('printer_name', 100)->nullable()->after('has_printer');
        });
    }

    public function down(): void
    {
        Schema::table('preparation_stations', function (Blueprint $table) {
            $table->dropColumn(['has_printer', 'printer_name']);
        });
    }
};
