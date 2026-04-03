<?php

use App\Support\ProformaCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_proforma_line_defaults', function (Blueprint $table) {
            if (! Schema::hasColumn('hotel_proforma_line_defaults', 'report_bucket_key')) {
                $table->string('report_bucket_key', 40)->default('other')->after('line_type');
            }
        });

        $map = ProformaCatalog::defaultBucketForLineType();
        foreach ($map as $lineType => $bucket) {
            DB::table('hotel_proforma_line_defaults')
                ->where('line_type', $lineType)
                ->update(['report_bucket_key' => $bucket]);
        }
    }

    public function down(): void
    {
        Schema::table('hotel_proforma_line_defaults', function (Blueprint $table) {
            if (Schema::hasColumn('hotel_proforma_line_defaults', 'report_bucket_key')) {
                $table->dropColumn('report_bucket_key');
            }
        });
    }
};
