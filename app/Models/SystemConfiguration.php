<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
    ];

    /**
     * Get configuration value by key
     */
    public static function get($key, $default = null)
    {
        $config = self::where('key', $key)->first();
        
        if (!$config) {
            return $default;
        }

        return match($config->type) {
            'boolean' => filter_var($config->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $config->value,
            'json' => json_decode($config->value, true),
            default => $config->value,
        };
    }

    /**
     * Set configuration value by key
     */
    public static function set($key, $value, $type = 'string', $description = null)
    {
        $config = self::updateOrCreate(
            ['key' => $key],
            [
                'value' => is_array($value) ? json_encode($value) : $value,
                'type' => $type,
                'description' => $description,
            ]
        );

        return $config;
    }
}
