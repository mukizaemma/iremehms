<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            if (! Schema::hasColumn('hotels', 'stock_daily_report_audit_enabled')) {
                $table->boolean('stock_daily_report_audit_enabled')
                    ->default(false)
                    ->after('reports_show_vat');
            }
        });
    }

    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            if (Schema::hasColumn('hotels', 'stock_daily_report_audit_enabled')) {
                $table->dropColumn('stock_daily_report_audit_enabled');
            }
        });
    }
};
