<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('additional_charges', function (Blueprint $table) {
            $table->string('type', 50)->default('service')->after('hotel_id');
        });
    }

    public function down(): void
    {
        Schema::table('additional_charges', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
