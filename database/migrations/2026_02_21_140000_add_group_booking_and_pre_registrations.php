<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('group_name', 200)->nullable()->after('business_source_detail');
            $table->unsignedSmallInteger('expected_guest_count')->nullable()->after('group_name');
        });

        Schema::create('pre_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained('reservations')->nullOnDelete();
            $table->string('reservation_reference', 100)->nullable()->comment('Reference guest entered or selected');
            $table->string('guest_name');
            $table->string('guest_id_number', 100)->nullable();
            $table->string('guest_country', 100)->nullable();
            $table->string('guest_email')->nullable();
            $table->string('guest_phone')->nullable();
            $table->string('guest_profession', 100)->nullable();
            $table->string('guest_stay_purpose', 100)->nullable();
            $table->string('organization', 200)->nullable();
            $table->string('id_document_path')->nullable()->comment('Uploaded ID or photo path');
            $table->string('status', 30)->default('pending')->comment('pending, assigned, checked_in');
            $table->foreignId('room_unit_id')->nullable()->constrained('room_units')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pre_registrations');
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['group_name', 'expected_guest_count']);
        });
    }
};
