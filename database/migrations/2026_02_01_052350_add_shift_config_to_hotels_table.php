<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->time('business_day_rollover_time')->default('03:00:00')->after('font_family');
            $table->boolean('shifts_enabled')->default(true)->after('business_day_rollover_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropColumn(['business_day_rollover_time', 'shifts_enabled']);
        });
    }
};
