<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_days', function (Blueprint $table) {
            $table->enum('status', ['OPEN', 'CLOSED'])->default('OPEN')->after('closed_by');
        });
        DB::statement("UPDATE business_days SET status = IF(closed_at IS NULL, 'OPEN', 'CLOSED')");
    }

    public function down(): void
    {
        Schema::table('business_days', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
