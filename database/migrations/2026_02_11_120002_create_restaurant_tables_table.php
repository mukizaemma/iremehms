<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_tables', function (Blueprint $table) {
            $table->id();
            $table->string('table_number', 50);
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('table_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_tables');
    }
};
