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
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->bigIncrements('category_id');
            $table->string('name'); // Utilities, Maintenance, etc.
            $table->foreignId('department_id')->nullable()->constrained('departments')->onDelete('set null');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['department_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};
