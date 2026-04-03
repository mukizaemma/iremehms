<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            if (! Schema::hasColumn('hotels', 'operational_shift_scope')) {
                $table->string('operational_shift_scope', 32)->default('per_module')->after('shift_mode');
            }
        });

        if (! Schema::hasTable('operational_shifts')) {
            Schema::create('operational_shifts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
                /** global | pos | front-office | store */
                $table->string('module_scope', 32);
                /** Reporting label: date the shift was opened (hotel-local). */
                $table->date('reference_date');
                $table->dateTime('opened_at');
                $table->dateTime('closed_at')->nullable();
                $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('open_note')->nullable();
                $table->text('close_comment')->nullable();
                $table->string('status', 16)->default('open');
                $table->timestamps();

                $table->index(['hotel_id', 'module_scope', 'status']);
                $table->index(['hotel_id', 'status']);
            });
        }

        if (Schema::hasTable('pos_sessions') && ! Schema::hasColumn('pos_sessions', 'operational_shift_id')) {
            Schema::table('pos_sessions', function (Blueprint $table) {
                $col = Schema::hasColumn('pos_sessions', 'day_shift_id') ? 'day_shift_id' : 'business_day_id';
                $table->foreignId('operational_shift_id')->nullable()->after($col)->constrained('operational_shifts')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pos_sessions') && Schema::hasColumn('pos_sessions', 'operational_shift_id')) {
            Schema::table('pos_sessions', function (Blueprint $table) {
                $table->dropForeign(['operational_shift_id']);
                $table->dropColumn('operational_shift_id');
            });
        }

        Schema::dropIfExists('operational_shifts');

        Schema::table('hotels', function (Blueprint $table) {
            if (Schema::hasColumn('hotels', 'operational_shift_scope')) {
                $table->dropColumn('operational_shift_scope');
            }
        });
    }
};
