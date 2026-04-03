<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_module', function (Blueprint $table) {
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->primary(['hotel_id', 'module_id']);
        });

        // Backfill from hotels.enabled_modules JSON so existing data is preserved
        $hotels = DB::table('hotels')->get();
        foreach ($hotels as $hotel) {
            $enabled = $hotel->enabled_modules ? json_decode($hotel->enabled_modules, true) : null;
            if (is_array($enabled) && !empty($enabled)) {
                foreach ($enabled as $moduleId) {
                    DB::table('hotel_module')->insertOrIgnore([
                        'hotel_id' => $hotel->id,
                        'module_id' => $moduleId,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_module');
    }
};
