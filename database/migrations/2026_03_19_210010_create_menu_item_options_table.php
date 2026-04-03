<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('menu_item_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('menu_item_option_groups')->cascadeOnDelete();
            $table->string('label');
            $table->string('value')->nullable(); // internal key; defaults from label if null
            $table->decimal('price_delta', 12, 2)->default(0);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_options');
    }
};

