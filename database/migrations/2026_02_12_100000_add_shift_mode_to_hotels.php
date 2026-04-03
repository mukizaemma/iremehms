<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->enum('shift_mode', ['NO_SHIFT', 'OPTIONAL_SHIFT', 'STRICT_SHIFT'])
                ->default('STRICT_SHIFT')
                ->after('shifts_enabled');
            $table->boolean('auto_close_previous_business_day')->default(false)->after('shift_mode');
            $table->boolean('allow_manual_close_business_day')->default(true)->after('auto_close_previous_business_day');
        });
    }

    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropColumn(['shift_mode', 'auto_close_previous_business_day', 'allow_manual_close_business_day']);
        });
    }
};
