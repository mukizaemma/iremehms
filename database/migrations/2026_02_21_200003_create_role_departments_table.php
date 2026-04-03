<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Controller scope: GLOBAL (entire hotel) or DEPARTMENT (per department).
     * When scope = department, department_id restricts the role to that department only.
     */
    public function up(): void
    {
        Schema::create('role_departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->cascadeOnDelete();
            $table->string('scope', 20)->default('global')->comment('global = entire hotel, department = scoped to department_id');
            $table->timestamps();

            $table->unique(['role_id', 'department_id'], 'role_department_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_departments');
    }
};
