<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_requests', function (Blueprint $table) {
            $table->timestamp('deletion_requested_at')->nullable()->after('to_department_id');
            $table->foreignId('deletion_requested_by_id')->nullable()->after('deletion_requested_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_requests', function (Blueprint $table) {
            $table->dropForeign(['deletion_requested_by_id']);
            $table->dropColumn(['deletion_requested_at', 'deletion_requested_by_id']);
        });
    }
};
