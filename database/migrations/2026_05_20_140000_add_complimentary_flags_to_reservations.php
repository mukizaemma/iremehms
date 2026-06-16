<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->boolean('is_room_complimentary')->default(false)->after('meal_plan_supplement');
            $table->boolean('is_meal_complimentary')->default(false)->after('is_room_complimentary');
            $table->text('complimentary_reason')->nullable()->after('is_meal_complimentary');
        });

        DB::table('reservations')
            ->where('meal_plan', 'comp')
            ->update([
                'is_room_complimentary' => true,
                'is_meal_complimentary' => true,
                'complimentary_reason' => 'Legacy full complimentary (room & meals)',
                'meal_plan' => 'bb',
            ]);
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['is_room_complimentary', 'is_meal_complimentary', 'complimentary_reason']);
        });
    }
};
