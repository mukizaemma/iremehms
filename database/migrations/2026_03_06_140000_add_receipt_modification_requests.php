<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_modification_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('requested_by_id')->constrained('users')->cascadeOnDelete();
            $table->text('reason');
            $table->string('status', 20)->default('pending'); // pending, approved, rejected
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->index(['invoice_id', 'status']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('modification_approved_for_user_id')->nullable()->after('assigned_at')->constrained('users')->nullOnDelete();
            $table->timestamp('modification_approved_at')->nullable()->after('modification_approved_for_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['modification_approved_for_user_id']);
            $table->dropColumn(['modification_approved_for_user_id', 'modification_approved_at']);
        });
        Schema::dropIfExists('receipt_modification_requests');
    }
};
