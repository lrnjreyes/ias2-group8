<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class AuthenticationThrottle
{
    public const MAX_ATTEMPTS = 5;

    public const INITIAL_SECONDS = 60;

    public const REPEAT_SECONDS = 900;

    public const HISTORY_SECONDS = 3600;

    public static function status(string $key): ?array
    {
        if (! RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return null;
        }

        return [
            'retryAfter' => RateLimiter::availableIn($key),
            'showCountdown' => ! Cache::has(self::marker($key, 'extended')),
        ];
    }

    public static function recordFailure(string $key): ?array
    {
        if ($status = self::status($key)) {
            return $status;
        }

        if (Cache::has(self::marker($key, 'previous'))) {
            RateLimiter::clear($key);
            RateLimiter::increment($key, self::REPEAT_SECONDS, self::MAX_ATTEMPTS);
            Cache::put(self::marker($key, 'extended'), true, self::REPEAT_SECONDS);
            Cache::put(self::marker($key, 'previous'), true, self::HISTORY_SECONDS);

            return self::status($key);
        }

        RateLimiter::hit($key, self::INITIAL_SECONDS);

        if ($status = self::status($key)) {
            // Keep the previous lockout after its timer expires so another failure gets a longer wait.
            Cache::put(self::marker($key, 'previous'), true, self::HISTORY_SECONDS);
        }

        return $status;
    }

    public static function message(array $status, string $prefix): string
    {
        return $status['showCountdown']
            ? $prefix.' Please try again in '.$status['retryAfter'].' seconds.'
            : 'Too many attempts. Please try again later.';
    }

    public static function clear(string $key): void
    {
        RateLimiter::clear($key);
        Cache::forget(self::marker($key, 'previous'));
        Cache::forget(self::marker($key, 'extended'));
    }

    private static function marker(string $key, string $stage): string
    {
        return 'auth-lockout:'.hash('sha256', $key).':'.$stage;
    }
}
