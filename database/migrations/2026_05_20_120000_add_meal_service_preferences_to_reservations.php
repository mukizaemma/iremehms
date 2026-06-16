<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->time('breakfast_preferred_time')->nullable()->after('meal_plan_supplement');
            $table->time('lunch_preferred_time')->nullable()->after('breakfast_preferred_time');
            $table->time('dinner_preferred_time')->nullable()->after('lunch_preferred_time');
            $table->boolean('breakfast_in_room')->default(false)->after('dinner_preferred_time');
            $table->boolean('lunch_in_room')->default(false)->after('breakfast_in_room');
            $table->boolean('dinner_in_room')->default(false)->after('lunch_in_room');
            $table->text('meal_service_notes')->nullable()->after('dinner_in_room');
        });

        Schema::table('reservation_guests', function (Blueprint $table) {
            $table->time('breakfast_preferred_time')->nullable()->after('check_out_date');
            $table->time('dinner_preferred_time')->nullable()->after('breakfast_preferred_time');
            $table->boolean('breakfast_in_room')->default(false)->after('dinner_preferred_time');
            $table->boolean('dinner_in_room')->default(false)->after('breakfast_in_room');
            $table->string('meal_service_notes', 500)->nullable()->after('dinner_in_room');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn([
                'breakfast_preferred_time',
                'lunch_preferred_time',
                'dinner_preferred_time',
                'breakfast_in_room',
                'lunch_in_room',
                'dinner_in_room',
                'meal_service_notes',
            ]);
        });

        Schema::table('reservation_guests', function (Blueprint $table) {
            $table->dropColumn([
                'breakfast_preferred_time',
                'dinner_preferred_time',
                'breakfast_in_room',
                'dinner_in_room',
                'meal_service_notes',
            ]);
        });
    }
};
