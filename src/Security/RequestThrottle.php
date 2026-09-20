<?php

namespace ShopExtensions\Security;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Cache\RateLimiter;
use SilverStripe\Core\Config\Configurable;

/**
 * Rate limiting for public shop endpoints outside /Security (account creation in the checkout,
 * summary endpoint, registration, cart APIs, subscription actions …). Built on the framework
 * RateLimiter (cache `Psr\SimpleCache\CacheInterface.RateLimiter`).
 *
 * Buckets are configured per bucket name, preferably as a map (maps merge by key, lists are
 * APPENDED by SilverStripe's config merge):
 *
 *   ShopExtensions\Security\RequestThrottle:
 *     buckets:
 *       checkout-account: { max: 10, decay: 10 }   # decay in minutes
 *
 * A bucket without configuration allows 60 requests per minute; max 0 switches a bucket off.
 * Without a key requests are counted per host + client IP, with a key (e.g. "member-12") per that
 * identity. Call attempt() only for mutating requests; it counts the request.
 *
 * Replaces Ghee\Security\RequestThrottle (projects may keep a thin subclass for their own callers;
 * config set on the subclass applies to calls through the subclass only).
 */
class RequestThrottle
{
    use Configurable;

    /**
     * bucket => [max attempts, decay in minutes]
     *
     * @config
     */
    private static array $buckets = [
        'registration' => [5, 10],
        'checkout-account' => [10, 10],
        'checkout-summary' => [60, 1],
        'cart-api' => [120, 1],
    ];

    /**
     * Prefix of the cache keys.
     *
     * @config
     */
    private static string $key_prefix = 'shopext-';

    /**
     * Counts this request against $bucket. Returns false when the limit is already reached (the
     * request must be refused, e.g. with HTTP 429).
     */
    public static function attempt(HTTPRequest $request, string $bucket, ?string $key = null): bool
    {
        [$max] = static::limits($bucket);
        if ($max <= 0) {
            return true;
        }

        $limiter = static::limiter($request, $bucket, $key);
        if (!$limiter->canAccess()) {
            return false;
        }
        $limiter->hit();

        return true;
    }

    /**
     * Seconds until the bucket accepts requests again (for messages / Retry-After), at least 1.
     */
    public static function retryAfterSeconds(HTTPRequest $request, string $bucket, ?string $key = null): int
    {
        return max(1, (int) static::limiter($request, $bucket, $key)->getTimeToReset());
    }

    /**
     * Limits of a bucket.
     *
     * A bucket may be written as a map `{max: 10, decay: 10}` (RECOMMENDED: maps merge by key, so a
     * project value replaces the default) or as the short list `[10, 10]`. SilverStripe's config
     * merge APPENDS lists instead of replacing them, so `[10, 10]` from this class plus `[200, 10]`
     * from a project YAML becomes `[10, 10, 200, 10]` – the LAST two entries are therefore the ones
     * that were configured last and they win.
     *
     * @return array{0:int,1:int} [max attempts, decay minutes]
     */
    public static function limits(string $bucket): array
    {
        $raw = static::config()->get('buckets')[$bucket] ?? null;

        if (is_array($raw) && (isset($raw['max']) || isset($raw['decay']))) {
            return [(int) ($raw['max'] ?? 60), max(1, (int) ($raw['decay'] ?? 1))];
        }

        $values = array_values((array) ($raw ?? [60, 1]));
        if (count($values) > 2) {
            $values = array_slice($values, -2);
        }
        [$max, $decay] = array_pad($values, 2, 1);

        return [(int) $max, max(1, (int) $decay)];
    }

    protected static function limiter(HTTPRequest $request, string $bucket, ?string $key): RateLimiter
    {
        [$max, $decay] = static::limits($bucket);
        $identity = ($key === null || $key === '')
            ? $request->getHost() . '|' . $request->getIP()
            : $request->getHost() . '|key|' . $key;

        return RateLimiter::create(
            static::config()->get('key_prefix') . $bucket . '-' . md5($identity),
            max(1, $max),
            $decay
        );
    }
}
