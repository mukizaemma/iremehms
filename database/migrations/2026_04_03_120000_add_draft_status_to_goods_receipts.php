<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // MySQL enum: add DRAFT for save-without-posting-stock workflow
        DB::statement("ALTER TABLE goods_receipts MODIFY receipt_status ENUM('DRAFT', 'PARTIAL', 'COMPLETE') NOT NULL DEFAULT 'COMPLETE'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("UPDATE goods_receipts SET receipt_status = 'PARTIAL' WHERE receipt_status = 'DRAFT'");
        DB::statement("ALTER TABLE goods_receipts MODIFY receipt_status ENUM('PARTIAL', 'COMPLETE') NOT NULL DEFAULT 'COMPLETE'");
    }
};
