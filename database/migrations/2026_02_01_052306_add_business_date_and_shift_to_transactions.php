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
        // Add to stocks table
        Schema::table('stocks', function (Blueprint $table) {
            $table->date('business_date')->nullable()->after('department_id');
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->onDelete('restrict')->after('business_date');
        });

        // Add to sales table
        Schema::table('sales', function (Blueprint $table) {
            $table->date('business_date')->nullable()->after('department_id');
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->onDelete('restrict')->after('business_date');
        });

        // Add to expenses table
        Schema::table('expenses', function (Blueprint $table) {
            $table->date('business_date')->nullable()->after('department_id');
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->onDelete('restrict')->after('business_date');
        });

        // Make business_date and shift_id required after initial migration
        // We'll do this in a separate migration to allow existing data to be migrated
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropForeign(['shift_id']);
            $table->dropColumn(['business_date', 'shift_id']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['shift_id']);
            $table->dropColumn(['business_date', 'shift_id']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['shift_id']);
            $table->dropColumn(['business_date', 'shift_id']);
        });
    }
};
