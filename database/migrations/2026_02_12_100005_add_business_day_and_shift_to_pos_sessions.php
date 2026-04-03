<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_sessions', function (Blueprint $table) {
            $table->foreignId('business_day_id')->nullable()->after('id')->constrained('business_days')->onDelete('restrict');
            $table->foreignId('day_shift_id')->nullable()->after('business_day_id')->constrained('day_shifts')->onDelete('set null');
        });
        // New flow can leave shift_log_id null
        if (Schema::hasColumn('pos_sessions', 'shift_log_id')) {
            DB::statement('ALTER TABLE pos_sessions MODIFY shift_log_id BIGINT UNSIGNED NULL');
        }
    }

    public function down(): void
    {
        Schema::table('pos_sessions', function (Blueprint $table) {
            $table->dropForeign(['business_day_id']);
            $table->dropForeign(['day_shift_id']);
            $table->dropColumn(['business_day_id', 'day_shift_id']);
        });
    }
};
