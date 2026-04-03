<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activity_logs')) {
            return;
        }
        Schema::table('activity_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('activity_logs', 'hotel_id')) {
                $table->foreignId('hotel_id')->nullable()->after('user_id')->constrained('hotels')->nullOnDelete();
                $table->index(['hotel_id', 'created_at']);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('activity_logs')) {
            return;
        }
        Schema::table('activity_logs', function (Blueprint $table) {
            if (Schema::hasColumn('activity_logs', 'hotel_id')) {
                $table->dropForeign(['hotel_id']);
                $table->dropColumn('hotel_id');
            }
        });
    }
};
