<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_types', function (Blueprint $table) {
            $table->unsignedTinyInteger('max_adults')->default(2)->after('is_active');
            $table->unsignedTinyInteger('max_children')->default(0)->after('max_adults');
        });
    }

    public function down(): void
    {
        Schema::table('room_types', function (Blueprint $table) {
            $table->dropColumn(['max_adults', 'max_children']);
        });
    }
};
