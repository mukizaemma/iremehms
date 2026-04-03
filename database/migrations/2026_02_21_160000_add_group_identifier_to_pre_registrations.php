<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pre_registrations', function (Blueprint $table) {
            $table->string('group_identifier', 64)->nullable()->after('reservation_id')
                ->comment('Shared ID when multiple guests are submitted as one group');
        });
    }

    public function down(): void
    {
        Schema::table('pre_registrations', function (Blueprint $table) {
            $table->dropColumn('group_identifier');
        });
    }
};
