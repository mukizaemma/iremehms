<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // hotel user who created
            $table->string('subject');
            $table->text('message');
            $table->string('status', 20)->default('open'); // open, in_progress, resolved
            $table->timestamps();

            $table->index(['hotel_id', 'status']);
        });

        Schema::create('support_request_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_request_id')->constrained('support_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // Ireme user who replied
            $table->text('message');
            $table->timestamps();

            $table->index('support_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_request_responses');
        Schema::dropIfExists('support_requests');
    }
};
