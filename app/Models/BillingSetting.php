<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingSetting extends Model
{
    public const DEFAULT_TRIAL_DAYS = 30;

    protected $fillable = ['key', 'value'];

    public static function get(string $key, ?string $default = null): ?string
    {
        return static::query()->where('key', $key)->value('value') ?? $default;
    }

    public static function set(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public static function defaultTrialDays(): int
    {
        return (int) static::get('default_trial_days', (string) self::DEFAULT_TRIAL_DAYS);
    }
}
