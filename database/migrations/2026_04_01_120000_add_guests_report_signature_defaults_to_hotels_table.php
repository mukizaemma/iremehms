<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->string('guests_report_signature_prepared_default', 255)->nullable();
            $table->string('guests_report_signature_verified_default', 255)->nullable();
            $table->string('guests_report_signature_approved_default', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropColumn([
                'guests_report_signature_prepared_default',
                'guests_report_signature_verified_default',
                'guests_report_signature_approved_default',
            ]);
        });
    }
};
