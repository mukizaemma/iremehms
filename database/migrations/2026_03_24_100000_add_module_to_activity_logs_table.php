<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activity_logs')) {
            return;
        }

        if (! Schema::hasColumn('activity_logs', 'module')) {
            Schema::table('activity_logs', function (Blueprint $table) {
                $table->string('module', 64)->nullable()->after('action');
            });
        }

        if (Schema::hasColumn('activity_logs', 'module')) {
            DB::table('activity_logs')
                ->whereNull('module')
                ->where(function ($q) {
                    $q->where('action', 'like', 'reservation.%')
                        ->orWhere('action', 'like', 'payment.%');
                })
                ->update(['module' => 'front-office']);
        }

        Schema::table('activity_logs', function (Blueprint $table) {
            if (Schema::hasColumn('activity_logs', 'hotel_id') && Schema::hasColumn('activity_logs', 'module')) {
                $table->index(['hotel_id', 'module', 'created_at'], 'activity_logs_hotel_module_created_idx');
            }
            if (Schema::hasColumn('activity_logs', 'hotel_id')) {
                $table->index(['hotel_id', 'user_id', 'created_at'], 'activity_logs_hotel_user_created_idx');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('activity_logs')) {
            return;
        }
        Schema::table('activity_logs', function (Blueprint $table) {
            if (Schema::hasColumn('activity_logs', 'module')) {
                try {
                    $table->dropIndex('activity_logs_hotel_module_created_idx');
                } catch (\Throwable $e) {
                }
                try {
                    $table->dropIndex('activity_logs_hotel_user_created_idx');
                } catch (\Throwable $e) {
                }
                $table->dropColumn('module');
            }
        });
    }
};
