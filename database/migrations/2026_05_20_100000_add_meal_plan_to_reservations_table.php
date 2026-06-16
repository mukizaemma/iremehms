<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('meal_plan', 10)->default('bb')->after('rate_plan');
            $table->decimal('room_rate_amount', 14, 2)->nullable()->after('meal_plan');
            $table->decimal('meal_plan_supplement', 14, 2)->default(0)->after('room_rate_amount');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['meal_plan', 'room_rate_amount', 'meal_plan_supplement']);
        });
    }
};
