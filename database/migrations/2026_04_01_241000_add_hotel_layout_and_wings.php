<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            if (! Schema::hasColumn('hotels', 'has_multiple_wings')) {
                $table->boolean('has_multiple_wings')->default(false);
            }
            if (! Schema::hasColumn('hotels', 'total_floors')) {
                $table->unsignedSmallInteger('total_floors')->default(1);
            }
        });

        if (! Schema::hasTable('hotel_wings')) {
            Schema::create('hotel_wings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
                $table->string('name', 120);
                $table->string('code', 20)->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['hotel_id', 'name']);
            });
        }

        Schema::table('rooms', function (Blueprint $table) {
            if (! Schema::hasColumn('rooms', 'wing_id')) {
                $table->foreignId('wing_id')->nullable()->after('room_type_id')->constrained('hotel_wings')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            if (Schema::hasColumn('rooms', 'wing_id')) {
                $table->dropConstrainedForeignId('wing_id');
            }
        });

        Schema::dropIfExists('hotel_wings');

        Schema::table('hotels', function (Blueprint $table) {
            if (Schema::hasColumn('hotels', 'has_multiple_wings')) {
                $table->dropColumn('has_multiple_wings');
            }
            if (Schema::hasColumn('hotels', 'total_floors')) {
                $table->dropColumn('total_floors');
            }
        });
    }
};
