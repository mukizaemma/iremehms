<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('day_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_day_id')->constrained('business_days')->onDelete('cascade');
            $table->foreignId('shift_template_id')->nullable()->constrained('shift_templates')->onDelete('set null');
            $table->string('name');
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->foreignId('opened_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('closed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('status', ['PENDING', 'OPEN', 'CLOSED'])->default('PENDING');
            $table->timestamps();

            $table->index(['business_day_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('day_shifts');
    }
};
