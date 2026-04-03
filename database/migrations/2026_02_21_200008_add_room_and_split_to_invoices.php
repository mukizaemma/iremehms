<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Room posting: invoice can be charged to room/reservation.
     * Split bills: parent_invoice_id + split_type for equal/custom splits.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('reservation_id')->nullable()->after('order_id')->constrained('reservations')->nullOnDelete();
            $table->unsignedBigInteger('room_id')->nullable()->after('reservation_id')->comment('Denormalized for display; from reservation');
            $table->string('charge_type', 20)->default('pos')->after('invoice_status')->comment('pos, room');
            $table->foreignId('parent_invoice_id')->nullable()->after('total_amount')->constrained('invoices')->nullOnDelete();
            $table->string('split_type', 20)->nullable()->after('parent_invoice_id')->comment('null=main, equal, custom');
            $table->foreignId('posted_by_id')->nullable()->after('charge_type')->constrained('users')->nullOnDelete()->comment('User who posted (for recovery accountability)');

            $table->index(['charge_type', 'invoice_status']);
            $table->index(['reservation_id', 'invoice_status']);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['reservation_id']);
            $table->dropForeign(['parent_invoice_id']);
            $table->dropColumn([
                'reservation_id', 'room_id', 'charge_type', 'parent_invoice_id', 'split_type', 'posted_by_id',
            ]);
        });
    }
};
