<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->json('selected_options')->nullable()->after('notes');
            $table->json('ingredient_overrides')->nullable()->after('selected_options');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['selected_options', 'ingredient_overrides']);
        });
    }
};

