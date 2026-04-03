<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Invoice assignment: assign to guest room (reservation_id, room_id) or hotel covered (names + reason).
     * posted_by_id = who assigned; assigned_at = when.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('hotel_covered_names', 500)->nullable()->after('posted_by_id')->comment('Names when charge_type=hotel_covered');
            $table->string('hotel_covered_reason', 500)->nullable()->after('hotel_covered_names')->comment('Reason when charge_type=hotel_covered');
            $table->timestamp('assigned_at')->nullable()->after('hotel_covered_reason')->comment('When assignment was set (room or hotel covered)');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['hotel_covered_names', 'hotel_covered_reason', 'assigned_at']);
        });
    }
};
