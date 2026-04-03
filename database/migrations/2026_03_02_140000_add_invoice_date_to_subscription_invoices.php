<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_invoices', function (Blueprint $table) {
            $table->date('invoice_date')->nullable()->after('invoice_number');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_invoices', function (Blueprint $table) {
            $table->dropColumn('invoice_date');
        });
    }
};
