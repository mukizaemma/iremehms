<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Preparation stations (kitchen, bar, pastry, etc.) — configurable per hotel.
     * Orders are sent only to mapped stations (menu_item_station_map).
     */
    public function up(): void
    {
        Schema::create('preparation_stations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 50);
            $table->unsignedTinyInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preparation_stations');
    }
};
