<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('email_verified')->default(false)->after('email_verified_at');
            $table->foreignId('package_id')->nullable()->constrained('hotel_packages')->onDelete('set null')->after('department_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['package_id']);
            $table->dropColumn(['email_verified', 'package_id']);
        });
    }
};
