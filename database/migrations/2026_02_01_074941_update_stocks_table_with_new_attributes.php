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
        Schema::table('stocks', function (Blueprint $table) {
            // Remove old location/parent stock fields (will be replaced)
            // Add new fields based on requirements
            
            // Barcode fields
            $table->boolean('use_barcode')->default(false)->after('code');
            $table->string('barcode')->nullable()->after('use_barcode');
            
            // Item type - update to match new requirements (Assets, Expenses, Finished Product, Raw Material, Service)
            // We'll keep item_type_id but update the seeder
            
            // Package and quantity units
            $table->string('package_unit')->nullable()->after('unit'); // e.g., "Box", "Carton"
            $table->string('qty_unit')->nullable()->after('package_unit'); // e.g., "kg", "pcs", "liters"
            
            // Prices
            $table->decimal('purchase_price', 10, 2)->default(0)->after('unit_price');
            $table->decimal('sale_price', 10, 2)->default(0)->after('purchase_price');
            $table->enum('tax_type', ['0%', '18%'])->default('0%')->after('sale_price');
            
            // Stock quantities
            $table->decimal('beginning_stock_qty', 10, 2)->default(0)->after('quantity');
            $table->decimal('current_stock', 10, 2)->default(0)->after('beginning_stock_qty');
            $table->decimal('safety_stock', 10, 2)->default(0)->after('current_stock');
            
            // Expiration
            $table->boolean('use_expiration')->default(false)->after('safety_stock');
            $table->date('expiration_date')->nullable()->after('use_expiration');
            
            // Stock location (reference to stock_locations table)
            $table->foreignId('stock_location_id')->nullable()->after('department_id')->constrained('stock_locations')->onDelete('restrict');
            
            // Indexes
            $table->index('barcode');
            $table->index('stock_location_id');
            $table->index('use_expiration');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropForeign(['stock_location_id']);
            $table->dropIndex(['barcode']);
            $table->dropIndex(['stock_location_id']);
            $table->dropIndex(['use_expiration']);
            
            $table->dropColumn([
                'use_barcode',
                'barcode',
                'package_unit',
                'qty_unit',
                'purchase_price',
                'sale_price',
                'tax_type',
                'beginning_stock_qty',
                'current_stock',
                'safety_stock',
                'use_expiration',
                'expiration_date',
                'stock_location_id',
            ]);
        });
    }
};
