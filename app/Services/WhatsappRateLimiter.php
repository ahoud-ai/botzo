<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WhatsappRateLimiter
{
    /**
     * Maximum messages per second according to WhatsApp docs
     */
    const MAX_MESSAGES_PER_SECOND = 80;

    /**
     * Cache key prefix for rate limiting
     */
    const CACHE_KEY_PREFIX = 'whatsapp_rate_limit:';

    /**
     * Check if we can send a message (rate limit check)
     * Returns true if we can send, false if rate limited
     * 
     * @return bool
     */
    public static function canSend(?int $organizationId = null): bool
    {
        $timestamp = now()->timestamp;
        $key = self::cacheKey($timestamp, $organizationId);
        $currentCount = Cache::get($key, 0);
        
        return $currentCount < self::MAX_MESSAGES_PER_SECOND;
    }

    /**
     * Record that a message was sent (increment counter)
     * Uses atomic operations to ensure thread-safe rate limiting
     * 
     * @return bool True if within rate limit, false if exceeded
     */
    public static function recordSent(?int $organizationId = null): bool
    {
        $timestamp = now()->timestamp;
        $key = self::cacheKey($timestamp, $organizationId);
        
        // Use atomic increment - this is thread-safe in Redis/Cache
        $newCount = Cache::increment($key);
        
        // Set expiration on first increment (2 seconds to handle edge cases)
        if ($newCount === 1) {
            Cache::put($key, 1, 2);
        }
        
        // Check if we've exceeded the limit
        if ($newCount > self::MAX_MESSAGES_PER_SECOND) {
            // Decrement since we exceeded (rollback)
            Cache::decrement($key);
            return false;
        }
        
        return true;
    }

    /**
     * Wait until we can send a message (rate limit throttle)
     * This will block until rate limit allows sending
     * 
     * @param int $maxWaitSeconds Maximum seconds to wait
     * @return bool True if we can send now, false if max wait exceeded
     */
    public static function waitUntilCanSend(int $maxWaitSeconds = 10, ?int $organizationId = null): bool
    {
        $startTime = microtime(true);
        $maxWaitMicroseconds = $maxWaitSeconds * 1000000;
        
        while (!self::canSend($organizationId)) {
            // Check if we've exceeded max wait time
            $elapsed = (microtime(true) - $startTime) * 1000000;
            if ($elapsed >= $maxWaitMicroseconds) {
                return false;
            }
            
            // Wait a small amount before checking again
            usleep(10000); // 10ms
        }
        
        return true;
    }

    /**
     * Get current rate limit status
     * 
     * @return array
     */
    public static function getStatus(?int $organizationId = null): array
    {
        $timestamp = now()->timestamp;
        $key = self::cacheKey($timestamp, $organizationId);
        $currentCount = Cache::get($key, 0);
        $remaining = max(0, self::MAX_MESSAGES_PER_SECOND - $currentCount);
        
        return [
            'current_count' => $currentCount,
            'max_per_second' => self::MAX_MESSAGES_PER_SECOND,
            'remaining' => $remaining,
            'can_send' => $remaining > 0,
            'percentage_used' => $currentCount > 0 ? ($currentCount / self::MAX_MESSAGES_PER_SECOND) * 100 : 0,
        ];
    }

    /**
     * Reset rate limit counter (useful for testing)
     * 
     * @return void
     */
    public static function reset(?int $organizationId = null): void
    {
        // Clear all rate limit keys (current and previous second)
        $timestamp = now()->timestamp;
        for ($i = 0; $i <= 2; $i++) {
            $key = self::cacheKey($timestamp - $i, $organizationId);
            Cache::forget($key);
        }
    }

    private static function cacheKey(int $timestamp, ?int $organizationId = null): string
    {
        $scope = $organizationId ? 'organization:' . $organizationId : 'global';

        return self::CACHE_KEY_PREFIX . $scope . ':' . $timestamp;
    }
}
