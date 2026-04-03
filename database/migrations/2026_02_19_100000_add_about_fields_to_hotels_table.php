<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->string('fax', 100)->nullable()->after('contact');
            $table->string('reservation_phone', 100)->nullable()->after('reservation_contacts');
            $table->string('hotel_type', 100)->nullable()->after('address');
            $table->string('check_in_time', 20)->nullable()->after('hotel_type'); // e.g. 01:00 PM, 13:00
            $table->string('check_out_time', 20)->nullable()->after('check_in_time');
            $table->text('hotel_information')->nullable()->after('check_out_time');
            $table->text('landmarks_nearby')->nullable()->after('hotel_information');
            $table->text('facilities')->nullable()->after('landmarks_nearby');
            $table->text('check_in_policy')->nullable()->after('facilities');
            $table->text('children_extra_guest_details')->nullable()->after('check_in_policy');
            $table->text('parking_policy')->nullable()->after('children_extra_guest_details');
            $table->text('things_to_do')->nullable()->after('parking_policy');
        });
    }

    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropColumn([
                'fax', 'reservation_phone', 'hotel_type', 'check_in_time', 'check_out_time',
                'hotel_information', 'landmarks_nearby', 'facilities', 'check_in_policy',
                'children_extra_guest_details', 'parking_policy', 'things_to_do',
            ]);
        });
    }
};
