<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pre_registrations', function (Blueprint $table) {
            $table->text('private_notes')->nullable()->after('organization')
                ->comment('Health issues, special care, or any request guest prefers not to share at reception');
        });
    }

    public function down(): void
    {
        Schema::table('pre_registrations', function (Blueprint $table) {
            $table->dropColumn('private_notes');
        });
    }
};
