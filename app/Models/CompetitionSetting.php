<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompetitionSetting extends Model
{
    protected $fillable = ['key', 'value'];

    protected $primaryKey = 'id';

    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->where('key', $key)->first();

        return $setting ? $setting->value : $default;
    }

    /**
     * Read a setting as a boolean. Accepts '1'/'true'/'on'/'yes' as true and
     * treats everything else (including '0', 'false', 'off', '') as false.
     */
    public static function bool(string $key, bool $default = false): bool
    {
        $value = static::get($key);

        if ($value === null) {
            return $default;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'on', 'yes'], true);
    }

    public static function put(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => (string) $value]);
    }
}
