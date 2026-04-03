<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('payments', 'client_reference')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('client_reference', 500)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payments', 'client_reference')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn('client_reference');
            });
        }
    }
};
