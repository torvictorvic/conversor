<?php
declare(strict_types=1);

namespace App\Providers;

use App\Cache;

final class CachedProvider implements RateProvider
{
    private RateProvider $inner;
    private int $ttl;

    public function __construct(RateProvider $inner, int $ttlSeconds)
    {
        $this->inner = $inner;
        $this->ttl   = $ttlSeconds;
    }

    public function getConversion(string $currency): array
    {
        $key = get_class($this->inner) . ':' . strtoupper($currency);
        if ($cached = Cache::get($key, $this->ttl)) {
            // etiqueta para cache
            $cached['source'] .= ' (cache)';
            return $cached;
        }
        $fresh = $this->inner->getConversion($currency);
        Cache::set($key, $fresh);
        return $fresh;
    }
}
