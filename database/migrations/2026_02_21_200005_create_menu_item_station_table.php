<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Many-to-many: menu item can be sent to one or more preparation stations.
     * When waiter adds item, order is sent ONLY to mapped station(s).
     */
    public function up(): void
    {
        Schema::create('menu_item_station', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('menu_item_id');
            $table->foreignId('preparation_station_id')->constrained('preparation_stations')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['menu_item_id', 'preparation_station_id'], 'menu_item_station_unique');
        });

        Schema::table('menu_item_station', function (Blueprint $table) {
            $table->foreign('menu_item_id')->references('menu_item_id')->on('menu_items')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_station');
    }
};
