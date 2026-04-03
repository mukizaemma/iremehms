<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            // Remove old category string field and add category_id FK
            $table->dropColumn('category');
            $table->unsignedBigInteger('category_id')->nullable()->after('department_id');
            $table->foreign('category_id')->references('category_id')->on('expense_categories')->onDelete('set null');
            
            // Add supplier_id (nullable, for expenses that have suppliers)
            $table->unsignedBigInteger('supplier_id')->nullable()->after('category_id');
            $table->foreign('supplier_id')->references('supplier_id')->on('suppliers')->onDelete('set null');
            
            // Add currency field
            $table->string('currency', 3)->default('USD')->after('amount');
            
            // Add created_by column (we'll keep user_id for now to avoid migration issues)
            $table->unsignedBigInteger('created_by')->nullable()->after('shift_id');
        });
        
        // Copy user_id to created_by after the column is created
        DB::statement('UPDATE expenses SET created_by = user_id WHERE created_by IS NULL AND user_id IS NOT NULL');
        
        // Add foreign key constraint after data is copied
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
            
            // Add indexes
            $table->index(['category_id', 'business_date']);
            $table->index(['department_id', 'business_date']);
        });
        
        // Make description required in a separate statement
        Schema::table('expenses', function (Blueprint $table) {
            $table->text('description')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['expenses_category_id_foreign']);
            $table->dropForeign(['expenses_supplier_id_foreign']);
            $table->dropForeign(['expenses_created_by_foreign']);
            $table->dropIndex(['category_id', 'business_date']);
            $table->dropIndex(['department_id', 'business_date']);
            
            $table->dropColumn(['category_id', 'supplier_id', 'currency', 'created_by']);
            $table->string('category')->nullable();
        });
        
        Schema::table('expenses', function (Blueprint $table) {
            $table->text('description')->nullable()->change();
        });
    }
};
