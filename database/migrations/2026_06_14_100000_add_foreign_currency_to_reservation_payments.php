<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_payments', function (Blueprint $table) {
            $table->string('foreign_currency', 10)->nullable()->after('currency');
            $table->decimal('foreign_amount', 12, 2)->nullable()->after('foreign_currency');
            $table->decimal('exchange_rate', 16, 6)->nullable()->after('foreign_amount');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_payments', function (Blueprint $table) {
            $table->dropColumn(['foreign_currency', 'foreign_amount', 'exchange_rate']);
        });
    }
};
