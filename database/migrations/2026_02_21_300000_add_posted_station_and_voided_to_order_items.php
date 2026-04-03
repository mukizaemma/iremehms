<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * posted_to_station: which station this item is sent to (waiter can override menu default).
     * voided_at / voided_by_id: set when a void request is approved; kitchen/bar see who voided and when.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('posted_to_station', 50)->nullable()->after('printed_at');
            $table->timestamp('voided_at')->nullable()->after('posted_to_station');
            $table->foreignId('voided_by_id')->nullable()->after('voided_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['voided_by_id']);
            $table->dropColumn(['posted_to_station', 'voided_at', 'voided_by_id']);
        });
    }
};
