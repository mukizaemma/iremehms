<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->boolean('pending_deletion')->default(false)->after('is_active');
            $table->timestamp('deletion_requested_at')->nullable()->after('pending_deletion');
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn(['pending_deletion', 'deletion_requested_at']);
        });
    }
};
