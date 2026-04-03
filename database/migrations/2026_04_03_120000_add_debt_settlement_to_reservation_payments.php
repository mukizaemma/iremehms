<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_payments', function (Blueprint $table) {
            $table->boolean('is_debt_settlement')->default(false)->after('balance_after');
            $table->date('revenue_attribution_date')->nullable()->after('is_debt_settlement');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_payments', function (Blueprint $table) {
            $table->dropColumn(['is_debt_settlement', 'revenue_attribution_date']);
        });
    }
};
