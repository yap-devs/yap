<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class ThrottleByDistinctIp
{
    /**
     * Throttle requests when too many distinct IPs access the same resource
     * within a time window. Existing IPs are never blocked.
     *
     * @param  \Closure(Request): (Response)  $next
     * @param  int  $max_ips  Maximum distinct IPs allowed per window
     * @param  int  $window_seconds  Time window in seconds
     */
    public function handle(Request $request, \Closure $next, int $max_ips = 5, int $window_seconds = 60): Response
    {
        $ip = $request->ip();
        $cache_key = 'distinct_ip_throttle:' . $request->path();

        /** @var array $ips */
        $ips = Cache::get($cache_key, []);

        // Remove expired entries
        $now = time();
        $ips = array_filter($ips, fn(int $timestamp) => ($now - $timestamp) < $window_seconds);

        // If this IP is already recorded, always allow and refresh its timestamp
        if (isset($ips[$ip])) {
            $ips[$ip] = $now;
            Cache::put($cache_key, $ips, $window_seconds);

            return $next($request);
        }

        // New IP: check if we've exceeded the distinct IP limit
        if (count($ips) >= $max_ips) {
            abort(429, 'Too many distinct IPs accessing this subscription.');
        }

        // Record this new IP
        $ips[$ip] = $now;
        Cache::put($cache_key, $ips, $window_seconds);

        return $next($request);
    }
}
