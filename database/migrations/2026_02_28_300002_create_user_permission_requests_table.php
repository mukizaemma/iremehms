<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When Manager adds a permission to a user, it is recorded here as pending.
     * Only Super Admin can approve; once approved, the permission is added to user_permissions.
     */
    public function up(): void
    {
        Schema::create('user_permission_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->foreignId('requested_by_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('pending'); // pending, approved, rejected
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'permission_id'], 'user_permission_request_unique');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permission_requests');
    }
};
