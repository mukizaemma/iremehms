<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proforma_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('proforma_invoices', 'submitted_to_manager_at')) {
                $table->timestamp('submitted_to_manager_at')->nullable()->after('invoiced_at');
            }
            if (! Schema::hasColumn('proforma_invoices', 'manager_verified_at')) {
                $table->timestamp('manager_verified_at')->nullable();
            }
            if (! Schema::hasColumn('proforma_invoices', 'manager_verified_by')) {
                $table->foreignId('manager_verified_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('proforma_invoices', 'manager_rejected_at')) {
                $table->timestamp('manager_rejected_at')->nullable();
            }
            if (! Schema::hasColumn('proforma_invoices', 'manager_rejection_note')) {
                $table->text('manager_rejection_note')->nullable();
            }
        });

        if (! Schema::hasTable('hotel_proforma_line_defaults')) {
            Schema::create('hotel_proforma_line_defaults', function (Blueprint $table) {
                $table->id();
                $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
                $table->string('line_type', 40);
                $table->decimal('default_unit_price', 15, 2)->default(0);
                $table->timestamps();

                $table->unique(['hotel_id', 'line_type']);
            });
        }

        Schema::table('wellness_services', function (Blueprint $table) {
            if (! Schema::hasColumn('wellness_services', 'price_per_day')) {
                $table->decimal('price_per_day', 15, 2)->nullable()->after('default_price');
            }
            if (! Schema::hasColumn('wellness_services', 'price_monthly_subscription')) {
                $table->decimal('price_monthly_subscription', 15, 2)->nullable()->after('price_per_day');
            }
        });

        Schema::table('proforma_invoice_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('proforma_invoice_lines', 'wellness_service_id')) {
                $table->foreignId('wellness_service_id')->nullable()->after('metadata')->constrained('wellness_services')->nullOnDelete();
            }
            if (! Schema::hasColumn('proforma_invoice_lines', 'wellness_pricing_mode')) {
                $table->string('wellness_pricing_mode', 24)->nullable()->after('wellness_service_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('proforma_invoice_lines', function (Blueprint $table) {
            if (Schema::hasColumn('proforma_invoice_lines', 'wellness_service_id')) {
                $table->dropConstrainedForeignId('wellness_service_id');
            }
            if (Schema::hasColumn('proforma_invoice_lines', 'wellness_pricing_mode')) {
                $table->dropColumn('wellness_pricing_mode');
            }
        });

        Schema::table('wellness_services', function (Blueprint $table) {
            if (Schema::hasColumn('wellness_services', 'price_per_day')) {
                $table->dropColumn('price_per_day');
            }
            if (Schema::hasColumn('wellness_services', 'price_monthly_subscription')) {
                $table->dropColumn('price_monthly_subscription');
            }
        });

        Schema::dropIfExists('hotel_proforma_line_defaults');

        Schema::table('proforma_invoices', function (Blueprint $table) {
            if (Schema::hasColumn('proforma_invoices', 'manager_verified_by')) {
                $table->dropForeign(['manager_verified_by']);
            }
            foreach (['submitted_to_manager_at', 'manager_verified_at', 'manager_verified_by', 'manager_rejected_at', 'manager_rejection_note'] as $col) {
                if (Schema::hasColumn('proforma_invoices', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
