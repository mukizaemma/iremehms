<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class PlatformSetting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function get(string $key, $default = null)
    {
        $cacheKey = 'platform_setting_' . $key;
        return Cache::remember($cacheKey, 3600, function () use ($key, $default) {
            $row = static::where('key', $key)->first();
            return $row ? $row->value : $default;
        });
    }

    public static function set(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('platform_setting_' . $key);
    }

    /** Ireme logo storage path; returns full URL for display or null. */
    public static function getIremeLogoUrl(): ?string
    {
        $path = static::get('ireme_logo');
        return $path ? Storage::url($path) : null;
    }

    /** Global login background image for the left side of the login page. */
    public static function getLoginBackgroundUrl(): ?string
    {
        $path = static::get('login_background');
        return $path ? Storage::url($path) : null;
    }

    /** Ireme HMS company/details for subscription invoices (super admin sets these). */
    public static function getIremeCompanyName(): string
    {
        return (string) static::get('ireme_company_name', 'Ireme HMS');
    }

    public static function getIremePhone(): string
    {
        return (string) static::get('ireme_phone', '');
    }

    public static function getIremeEmail(): string
    {
        return (string) static::get('ireme_email', '');
    }

    public static function getIremeTin(): string
    {
        return (string) static::get('ireme_tin', '');
    }

    public static function getIremeBankAccount(): string
    {
        return (string) static::get('ireme_bank_account', '');
    }

    public static function getIremeMomoCode(): string
    {
        return (string) static::get('ireme_momo_code', '');
    }

    public static function getIremeInvoiceDescription(): string
    {
        return (string) static::get('ireme_invoice_description', 'Hotel management system subscription.');
    }

    public static function getIremeInvoiceThankYou(): string
    {
        return (string) static::get('ireme_invoice_thank_you', 'Thank you for your business.');
    }
}
