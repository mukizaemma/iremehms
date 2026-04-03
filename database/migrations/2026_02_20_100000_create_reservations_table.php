<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->string('reservation_number', 50)->unique();
            $table->string('guest_name');
            $table->string('guest_email')->nullable();
            $table->string('guest_phone')->nullable();
            $table->string('guest_country', 100)->nullable();
            $table->string('guest_address')->nullable();
            $table->date('check_in_date');
            $table->date('check_out_date');
            $table->time('check_in_time')->nullable();
            $table->time('check_out_time')->nullable();
            $table->foreignId('room_type_id')->nullable()->constrained('room_types')->nullOnDelete();
            $table->string('rate_plan', 50)->nullable();
            $table->unsignedTinyInteger('adult_count')->default(1);
            $table->unsignedTinyInteger('child_count')->default(0);
            $table->decimal('total_amount', 12, 2)->nullable();
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->string('currency', 10)->nullable();
            $table->string('status', 30)->default('confirmed');
            $table->string('booking_source', 50)->nullable();
            $table->string('reservation_type', 50)->nullable();
            $table->timestamps();
        });

        Schema::create('reservation_room_unit', function (Blueprint $table) {
            $table->foreignId('reservation_id')->constrained('reservations')->cascadeOnDelete();
            $table->foreignId('room_unit_id')->constrained('room_units')->cascadeOnDelete();
            $table->primary(['reservation_id', 'room_unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_room_unit');
        Schema::dropIfExists('reservations');
    }
};
