<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Business day and all POS times use this timezone (hotel local time).
     */
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->string('timezone', 64)->default('Africa/Kigali')->after('shift_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });
    }
};
