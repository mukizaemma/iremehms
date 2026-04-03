<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('hotels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('logo')->nullable();
            $table->string('primary_color')->default('#667eea');
            $table->string('secondary_color')->default('#764ba2');
            $table->string('font_family')->default('Heebo');
            $table->json('enabled_modules')->nullable(); // Array of module IDs
            $table->json('enabled_departments')->nullable(); // Array of department IDs
            $table->timestamps();
        });

        // Insert the single hotel record immediately after creating the table
        DB::table('hotels')->insert([
            'id' => 1,
            'name' => config('app.name', 'Hotel Management System'),
            'primary_color' => '#667eea',
            'secondary_color' => '#764ba2',
            'font_family' => 'Heebo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hotels');
    }
};
