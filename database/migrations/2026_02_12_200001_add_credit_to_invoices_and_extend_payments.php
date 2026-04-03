<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE invoices MODIFY COLUMN invoice_status ENUM('UNPAID', 'PAID', 'CREDIT') DEFAULT 'UNPAID'");

        DB::statement("ALTER TABLE payments MODIFY COLUMN payment_method ENUM('CASH', 'MOMO', 'MOMO_PERSONAL', 'MOMO_HOTEL', 'CARD', 'CREDIT') NOT NULL");

        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('tip_amount', 12, 2)->default(0)->after('amount');
            $table->string('tip_handling', 20)->nullable()->after('tip_amount'); // 'WAITER' | 'HOTEL'
            $table->timestamp('submitted_at')->nullable()->after('received_at'); // null = cash held by waiter, set when submitted
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['tip_amount', 'tip_handling', 'submitted_at']);
        });
        DB::statement("ALTER TABLE payments MODIFY COLUMN payment_method ENUM('CASH', 'MOMO', 'CARD', 'CREDIT') NOT NULL");
        DB::statement("ALTER TABLE invoices MODIFY COLUMN invoice_status ENUM('UNPAID', 'PAID') DEFAULT 'UNPAID'");
    }
};
