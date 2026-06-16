<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_payments', function (Blueprint $table) {
            $table->string('payment_purpose', 32)->default('current_stay')->after('payment_status');
        });

        if (Schema::hasColumn('reservation_payments', 'is_debt_settlement')) {
            DB::table('reservation_payments')
                ->where('is_debt_settlement', true)
                ->update(['payment_purpose' => 'debt_settlement']);
        }
    }

    public function down(): void
    {
        Schema::table('reservation_payments', function (Blueprint $table) {
            $table->dropColumn('payment_purpose');
        });
    }
};
