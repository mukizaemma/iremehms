<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('business_days', function (Blueprint $table) {
            $table->foreignId('hotel_id')
                ->nullable()
                ->after('id')
                ->constrained('hotels')
                ->cascadeOnDelete();
        });

        // Backfill existing records: infer hotel_id from the user who opened the business day.
        DB::statement('
            UPDATE business_days bd
            JOIN users u ON bd.opened_by = u.id
            SET bd.hotel_id = u.hotel_id
            WHERE bd.hotel_id IS NULL AND u.hotel_id IS NOT NULL
        ');

        // Ensure uniqueness per hotel + business_date (instead of global unique on business_date).
        // Drop old unique index if it exists.
        try {
            Schema::table('business_days', function (Blueprint $table) {
                $table->dropUnique('business_days_business_date_unique');
            });
        } catch (\Throwable $e) {
            // Index might not exist on some installations; ignore.
        }

        Schema::table('business_days', function (Blueprint $table) {
            $table->unique(['hotel_id', 'business_date'], 'business_days_hotel_date_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_days', function (Blueprint $table) {
            if (Schema::hasColumn('business_days', 'hotel_id')) {
                $table->dropUnique('business_days_hotel_date_unique');
                $table->dropConstrainedForeignId('hotel_id');
            }
        });

        // Restore original unique index on business_date (global).
        Schema::table('business_days', function (Blueprint $table) {
            $table->unique('business_date', 'business_days_business_date_unique');
        });
    }
};

