<?php

use App\Support\PaymentCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->foreignId('reservation_id')->constrained('reservations')->cascadeOnDelete();

            // The same reservation can receive many payments (partial / multiple receipts).
            $table->decimal('amount', 12, 2);
            $table->string('currency', 10)->nullable();

            // Modal uses: payment_type (City Ledger/Cash/Bank) + payment_method (Cash/Card/etc).
            $table->string('payment_type', 50)->nullable();
            $table->string('payment_method', 100)->nullable();
            $table->string('payment_status', 30)->default(PaymentCatalog::STATUS_PAID);

            // Receiver (who recorded/received the money at the desk).
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('received_at')->useCurrent();

            // Receipt number generated at creation time (can be printed & audited).
            $table->string('receipt_number', 50)->nullable()->unique();

            $table->string('status', 30)->default('confirmed'); // confirmed|voided
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();

            $table->text('comment')->nullable();

            // Snapshot to ensure receipts always show the correct balance after THIS payment.
            $table->decimal('total_paid_after', 12, 2)->default(0);
            $table->decimal('balance_after', 12, 2)->default(0);

            $table->timestamps();

            $table->index(['reservation_id', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_payments');
    }
};

