<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RateLimitApi
{
    protected array $limits = [
        'general' => 60,
        'auth' => 10,
        'search' => 30,
        'upload' => 20,
    ];

    public function handle(Request $request, Closure $next, ?string $group = null): Response
    {
        $group = $group ?? $this->resolveGroup($request);
        $maxAttempts = $this->limits[$group] ?? $this->limits['general'];

        $key = $this->resolveKey($request, $group);

        $limiter = RateLimiter::optional('api-' . $group);

        if ($limiter && method_exists($limiter, 'tooManyAttempts')) {
            $rateLimiter = RateLimiter::limiter('api-' . $group, function () use ($maxAttempts) {
                return \Illuminate\Cache\RateLimiter::none()->allow($maxAttempts, 1);
            });
        }

        $attempts = $this->getAttempts($key);
        $decay = 60;

        if ($attempts >= $maxAttempts) {
            $retryAfter = $decay - (now()->timestamp % $decay);

            $response = response()->json([
                'message' => 'Too many requests. Please try again later.',
                'error' => 'rate_limited',
                'retry_after' => $retryAfter,
            ], 429);

            return $response->withHeaders([
                'Retry-After' => $retryAfter,
                'X-RateLimit-Limit' => $maxAttempts,
                'X-RateLimit-Remaining' => 0,
                'X-RateLimit-Reset' => now()->addSeconds($retryAfter)->timestamp,
            ]);
        }

        $this->increment($key, $decay);

        $response = $next($request);

        if ($response instanceof Response) {
            $remaining = max(0, $maxAttempts - $this->getAttempts($key));
            $response->headers->set('X-RateLimit-Limit', $maxAttempts);
            $response->headers->set('X-RateLimit-Remaining', $remaining);
            $response->headers->set('X-RateLimit-Reset', now()->addSeconds($decay)->timestamp);
        }

        return $response;
    }

    protected function resolveGroup(Request $request): string
    {
        $path = strtolower($request->path());

        if (preg_match('#^api/v\d+/auth/#', $path) || str_starts_with($path, 'api/auth')) {
            return 'auth';
        }

        if (str_contains($path, 'search') || str_contains($path, 'explore')) {
            return 'search';
        }

        if ($request->is('api/*/upload*') || $request->is('api/*/media*')) {
            return 'upload';
        }

        if ($request->hasFile('photo') || $request->hasFile('image') || $request->hasFile('csv_file')) {
            return 'upload';
        }

        return 'general';
    }

    protected function resolveKey(Request $request, string $group): string
    {
        $ip = $request->ip();
        $userId = $request->user()?->id ?? 'guest';

        return "rate_limit:{$group}:{$ip}:{$userId}";
    }

    protected function getAttempts(string $key): int
    {
        $cache = Cache::store($this->getCacheDriver());

        return (int) $cache->get($key, 0);
    }

    protected function increment(string $key, int $decay): void
    {
        $cache = Cache::store($this->getCacheDriver());
        $current = $this->getAttempts($key);

        if ($current === 0) {
            $cache->put($key, 1, $decay);
        } else {
            $cache->increment($key);
        }
    }

    protected function getCacheDriver(): string
    {
        if (extension_loaded('redis') && class_exists(\Redis::class)) {
            return config('cache.default') === 'redis' ? 'redis' : config('cache.default');
        }

        return config('cache.default');
    }
}
