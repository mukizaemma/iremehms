<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // This migration is not needed - we're using parent_stock_id in stocks table instead
        // Keeping it empty to avoid migration errors
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
