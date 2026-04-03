<?php

use App\Support\PaymentCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (! Schema::hasColumn('payments', 'payment_status')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('payment_status', 30)->default(PaymentCatalog::STATUS_PAID)->after('payment_method');
            });
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE payments MODIFY payment_method VARCHAR(40) NOT NULL');
        }

        DB::table('payments')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                $method = PaymentCatalog::normalizePosMethod($row->payment_method);
                $status = PaymentCatalog::STATUS_PAID;
                if ($method === PaymentCatalog::METHOD_CASH && $row->submitted_at === null) {
                    $status = PaymentCatalog::STATUS_PENDING;
                }
                DB::table('payments')->where('id', $row->id)->update([
                    'payment_method' => $method,
                    'payment_status' => $status,
                ]);
            }
        });

        if (! Schema::hasColumn('reservation_payments', 'payment_status')) {
            Schema::table('reservation_payments', function (Blueprint $table) {
                $table->string('payment_status', 30)->default(PaymentCatalog::STATUS_PAID)->after('payment_method');
            });
        }

        $rows = DB::table('reservation_payments')->select('*')->get();
        foreach ($rows as $row) {
            $method = PaymentCatalog::normalizeReservationMethod($row->payment_method ?? $row->payment_type);
            $status = PaymentCatalog::STATUS_PAID;
            if ($row->payment_type && strcasecmp((string) $row->payment_type, 'City Ledger') === 0) {
                $status = PaymentCatalog::STATUS_DEBITS;
            }
            $status = PaymentCatalog::normalizeStatus($status);
            DB::table('reservation_payments')->where('id', $row->id)->update([
                'payment_method' => $method,
                'payment_status' => $status,
            ]);
        }

        $resIds = DB::table('reservation_payments')
            ->where('status', 'confirmed')
            ->distinct()
            ->pluck('reservation_id');
        foreach ($resIds as $rid) {
            \App\Models\ReservationPayment::recomputeBalancesForReservation((int) $rid);
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        DB::table('payments')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                $legacy = match (PaymentCatalog::normalizePosMethod($row->payment_method)) {
                    PaymentCatalog::METHOD_CASH => 'CASH',
                    PaymentCatalog::METHOD_MOMO => 'MOMO',
                    PaymentCatalog::METHOD_POS_CARD => 'CARD',
                    PaymentCatalog::METHOD_BANK => 'CREDIT',
                    default => 'CASH',
                };
                DB::table('payments')->where('id', $row->id)->update([
                    'payment_method' => $legacy,
                ]);
            }
        });

        if (Schema::hasColumn('payments', 'payment_status')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn('payment_status');
            });
        }

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE payments MODIFY COLUMN payment_method ENUM('CASH', 'MOMO', 'MOMO_PERSONAL', 'MOMO_HOTEL', 'CARD', 'CREDIT') NOT NULL");
        }

        if (Schema::hasColumn('reservation_payments', 'payment_status')) {
            Schema::table('reservation_payments', function (Blueprint $table) {
                $table->dropColumn('payment_status');
            });
        }
    }
};
