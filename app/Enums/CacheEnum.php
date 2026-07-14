<?php

namespace App\Enums;

use Closure;
use Illuminate\Support\Facades\Cache;

enum CacheEnum
{
    case SURVEY_ACTIVE;
    case SURVEY_REGIONS;
    case HAS_GEOMETRY;

    public function key(): string
    {
        return match ($this) {
            self::SURVEY_ACTIVE => 'survey_active',
            self::SURVEY_REGIONS => 'survey_regions',
            self::HAS_GEOMETRY => 'has_geometry',
        };
    }

    /**
     * Cache the closure's result forever under this key.
     * Invalidated explicitly via CacheEnum::flush() (e.g. on seed/deploy).
     */
    public function remember(Closure $callback): mixed
    {
        return Cache::rememberForever($this->key(), $callback);
    }

    public function forget(): void
    {
        Cache::forget($this->key());
    }

    /** Drop every fixed-data cache entry (call after (re)seeding or editing survey data). */
    public static function flush(): void
    {
        foreach (self::cases() as $case) {
            $case->forget();
        }
    }
}
