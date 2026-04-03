<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_day_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained('hotels')->onDelete('cascade');
            $table->time('day_start_time')->default('03:00:00');
            $table->boolean('auto_close_previous')->default(false);
            $table->boolean('allow_manual_close')->default(true);
            $table->timestamps();

            $table->unique('hotel_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_day_settings');
    }
};
