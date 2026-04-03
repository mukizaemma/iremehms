<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('operational_shift_open_requests')) {
            Schema::create('operational_shift_open_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
                $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
                /** global | pos | front-office | store */
                $table->string('module_scope', 32);
                $table->text('note')->nullable();
                $table->string('status', 16)->default('pending');
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('resolved_at')->nullable();
                $table->text('resolution_note')->nullable();
                $table->timestamps();

                $table->index(['hotel_id', 'status', 'module_scope'], 'op_shift_req_hotel_stat_scope');
                $table->index(['hotel_id', 'requested_by', 'status'], 'op_shift_req_hotel_user_stat');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_shift_open_requests');
    }
};
