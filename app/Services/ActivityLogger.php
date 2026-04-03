<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Hotel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Schema;

class ActivityLogger
{
    /**
     * Persist an audit row (Front Office and other modules). Safe no-op if table missing.
     */
    public static function log(
        string $action,
        string $description,
        ?string $modelType = null,
        ?int $modelId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $module = null
    ): void {
        if (! Schema::hasTable('activity_logs')) {
            return;
        }

        $data = [
            'user_id' => Auth::id(),
            'action' => $action,
            'model_type' => $modelType ?? '',
            'model_id' => $modelId,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => Request::ip(),
            'user_agent' => mb_substr((string) Request::userAgent(), 0, 500),
            'created_at' => now(),
        ];

        if (Schema::hasColumn('activity_logs', 'hotel_id')) {
            $data['hotel_id'] = Hotel::getHotel()?->id;
        }

        if (Schema::hasColumn('activity_logs', 'module')) {
            $data['module'] = $module;
        }

        ActivityLog::query()->create($data);
    }
}
