<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->string('public_slug', 64)->nullable()->unique()->after('map_embed_code');
            $table->text('reservation_contacts')->nullable()->after('public_slug');
        });
    }

    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropColumn(['public_slug', 'reservation_contacts']);
        });
    }
};
