<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    public $incrementing = false;
    protected $primaryKey = 'key';
    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Get a setting value by key.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::find($key);
        return $setting ? $setting->value : $default;
    }

    /**
     * Set a setting value by key.
     */
    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    /**
     * Get a boolean setting value.
     */
    public static function getBool(string $key, bool $default = false): bool
    {
        $value = static::get($key);
        if ($value === null) {
            return $default;
        }
        return in_array(strtolower($value), ['true', '1', 'yes', 'on'], true);
    }

    /**
     * Set a boolean setting value.
     */
    public static function setBool(string $key, bool $value): void
    {
        static::set($key, $value ? 'true' : 'false');
    }

    /**
     * Check if setup is completed.
     */
    public static function isSetupCompleted(): bool
    {
        return static::getBool('setup_completed', false);
    }

    /**
     * Mark setup as completed.
     */
    public static function markSetupCompleted(): void
    {
        static::setBool('setup_completed', true);
    }
}
