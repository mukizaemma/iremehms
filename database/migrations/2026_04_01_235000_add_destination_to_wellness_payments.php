<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wellness_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('wellness_payments', 'destination')) {
                $table->string('destination', 24)->default('direct_payment')->after('payment_kind');
            }
            if (! Schema::hasColumn('wellness_payments', 'reservation_id')) {
                $table->foreignId('reservation_id')->nullable()->after('hotel_id')->constrained('reservations')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('wellness_payments', function (Blueprint $table) {
            if (Schema::hasColumn('wellness_payments', 'reservation_id')) {
                $table->dropConstrainedForeignId('reservation_id');
            }
            if (Schema::hasColumn('wellness_payments', 'destination')) {
                $table->dropColumn('destination');
            }
        });
    }
};
