<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amenities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 20)->default('room'); // 'room' or 'hotel'
            $table->string('icon', 100)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('amenity_room_type', function (Blueprint $table) {
            $table->foreignId('amenity_id')->constrained('amenities')->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained('room_types')->cascadeOnDelete();
            $table->primary(['amenity_id', 'room_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amenity_room_type');
        Schema::dropIfExists('amenities');
    }
};
