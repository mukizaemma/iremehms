<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Many-to-many: users can be assigned to multiple departments.
     * Keeps users.department_id as primary for backward compatibility.
     */
    public function up(): void
    {
        Schema::create('department_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'department_id']);
        });

        // Copy existing single department into pivot so "all departments" display works
        $pairs = DB::table('users')->whereNotNull('department_id')->get(['id', 'department_id']);
        $now = now();
        foreach ($pairs as $row) {
            DB::table('department_user')->insertOrIgnore([
                'user_id' => $row->id,
                'department_id' => $row->department_id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('department_user');
    }
};
