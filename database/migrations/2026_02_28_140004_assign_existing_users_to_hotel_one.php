<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->whereNull('hotel_id')->update(['hotel_id' => 1]);
    }

    public function down(): void
    {
        // Cannot reliably reverse
    }
};
