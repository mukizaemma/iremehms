<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->unsignedSmallInteger('hotel_code')->nullable()->unique()->after('id');
            $table->string('subscription_type', 30)->nullable()->after('name'); // monthly, one_time, freemium
            $table->string('subscription_status', 30)->default('active')->after('subscription_type'); // active, past_due, cancelled, suspended
        });

        // Assign first hotel code 100 to existing default hotel
        DB::table('hotels')->where('id', 1)->update(['hotel_code' => 100]);
    }

    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropColumn(['hotel_code', 'subscription_type', 'subscription_status']);
        });
    }
};
