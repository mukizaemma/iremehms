<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('guest_id_number', 100)->nullable()->after('guest_country');
            $table->string('guest_profession', 100)->nullable()->after('guest_id_number');
            $table->string('guest_stay_purpose', 100)->nullable()->after('guest_profession');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['guest_id_number', 'guest_profession', 'guest_stay_purpose']);
        });
    }
};
