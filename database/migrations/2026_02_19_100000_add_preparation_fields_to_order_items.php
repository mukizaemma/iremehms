<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('line_total');
            $table->string('preparation_status', 20)->default('pending')->after('notes');
            $table->timestamp('preparation_ready_at')->nullable()->after('preparation_status');
            $table->index('preparation_status');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['preparation_status']);
            $table->dropColumn(['notes', 'preparation_status', 'preparation_ready_at']);
        });
    }
};
