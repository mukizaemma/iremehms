<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('menu_item_option_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained('menu_items', 'menu_item_id')->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable(); // internal key, optional
            $table->string('type')->default('single'); // single|multi
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_option_groups');
    }
};

