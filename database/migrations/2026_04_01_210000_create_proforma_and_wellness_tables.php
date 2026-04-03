<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proforma_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('proforma_number', 32);
            $table->string('status', 24)->default('draft'); // draft, sent, accepted, invoiced, cancelled
            $table->string('client_organization')->nullable();
            $table->string('client_name');
            $table->string('client_email')->nullable();
            $table->string('client_phone')->nullable();
            $table->string('event_title')->nullable();
            $table->date('service_start_date')->nullable();
            $table->date('service_end_date')->nullable();
            $table->text('notes')->nullable();
            $table->text('payment_terms')->nullable();
            $table->string('currency', 8)->default('RWF');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->foreignId('reservation_id')->nullable()->constrained('reservations')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('invoiced_at')->nullable();
            $table->timestamps();

            $table->unique(['hotel_id', 'proforma_number']);
            $table->index(['hotel_id', 'status']);
        });

        Schema::create('proforma_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proforma_invoice_id')->constrained('proforma_invoices')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('line_type', 40)->default('custom'); // room_night, meal, break, beverage, transport, venue, decoration, sound, outside_catering, wellness, conference_halls, garden, other, custom
            $table->string('description');
            $table->string('report_bucket_key', 40)->default('other');
            $table->decimal('quantity', 12, 3)->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('proforma_invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('proforma_invoice_id')->constrained('proforma_invoices')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->timestamp('received_at');
            $table->string('payment_method', 40)->default('cash');
            $table->string('reference')->nullable();
            $table->string('report_bucket_key', 40)->default('other');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['hotel_id', 'received_at']);
        });

        Schema::create('wellness_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 32)->nullable();
            $table->text('description')->nullable();
            $table->string('billing_type', 24)->default('per_visit'); // per_visit, daily, subscription
            $table->decimal('default_price', 15, 2)->default(0);
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->string('report_bucket_key', 40)->default('other');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['hotel_id', 'code']);
            $table->index(['hotel_id', 'is_active']);
        });

        Schema::create('wellness_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wellness_service_id')->nullable()->constrained('wellness_services')->nullOnDelete();
            $table->string('service_name_snapshot');
            $table->string('billing_type_snapshot', 24)->default('per_visit');
            $table->string('payment_kind', 24)->default('visit'); // visit, daily, subscription
            $table->decimal('amount', 15, 2);
            $table->timestamp('received_at');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('guest_name')->nullable();
            $table->string('report_bucket_key', 40)->default('other');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['hotel_id', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wellness_payments');
        Schema::dropIfExists('wellness_services');
        Schema::dropIfExists('proforma_invoice_payments');
        Schema::dropIfExists('proforma_invoice_lines');
        Schema::dropIfExists('proforma_invoices');
    }
};
