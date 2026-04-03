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
        Schema::create('hr_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('employee_id')->unique()->nullable();
            $table->date('hire_date')->nullable();
            $table->date('termination_date')->nullable();
            $table->decimal('salary', 10, 2)->nullable();
            $table->string('position')->nullable();
            $table->json('attendance_records')->nullable();
            $table->json('leave_records')->nullable();
            $table->json('performance_records')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hr_data');
    }
};
