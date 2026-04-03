<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_requests', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50); // transfer_substock, transfer_department, issue_department, item_edit
            $table->string('status', 20)->default('pending'); // pending, approved, rejected
            $table->foreignId('requested_by_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('to_stock_location_id')->nullable()->constrained('stock_locations')->nullOnDelete();
            $table->foreignId('to_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'type']);
            $table->index('requested_by_id');
        });

        Schema::create('stock_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_request_id')->constrained('stock_requests')->cascadeOnDelete();
            $table->foreignId('stock_id')->constrained('stocks')->cascadeOnDelete();
            $table->decimal('quantity', 16, 4)->default(0);
            $table->foreignId('to_stock_location_id')->nullable()->constrained('stock_locations')->nullOnDelete();
            $table->foreignId('to_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->json('edit_data')->nullable()->comment('For item_edit: field => value');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('stock_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_request_items');
        Schema::dropIfExists('stock_requests');
    }
};
