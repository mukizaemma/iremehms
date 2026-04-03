<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->decimal('subscription_amount', 12, 2)->nullable()->after('subscription_status');
            $table->date('subscription_start_date')->nullable()->after('subscription_amount');
            $table->date('next_due_date')->nullable()->after('subscription_start_date');
        });
    }

    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropColumn(['subscription_amount', 'subscription_start_date', 'next_due_date']);
        });
    }
};
