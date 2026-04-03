<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 100)->unique();
            $table->string('module_slug', 50)->nullable()->comment('dashboard, front-office, restaurant, store, recovery, settings');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('module_slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
