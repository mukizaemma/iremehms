<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_daily_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->date('report_date');
            $table->foreignId('stock_location_id')->nullable()->constrained('stock_locations')->nullOnDelete();
            $table->string('scope_key', 80);
            $table->string('status', 20)->default('draft');

            $table->foreignId('prepared_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rejected_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->unique(['hotel_id', 'report_date', 'scope_key'], 'stock_daily_reports_hotel_date_scope_unique');
            $table->index(['hotel_id', 'report_date', 'status'], 'stock_daily_reports_hotel_date_status_idx');
        });

        Schema::create('stock_daily_report_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_daily_report_id')->constrained('stock_daily_reports')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('stage', 30)->nullable();
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_daily_report_comments');
        Schema::dropIfExists('stock_daily_reports');
    }
};
