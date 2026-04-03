<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_communications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->string('guest_email');
            $table->string('guest_name')->nullable();
            $table->string('subject');
            $table->text('body');
            $table->string('status', 20)->default('sent'); // sent, failed
            $table->text('error_message')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->index(['hotel_id', 'reservation_id']);
            $table->index(['hotel_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_communications');
    }
};
