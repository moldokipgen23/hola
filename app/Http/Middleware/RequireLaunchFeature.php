<?php

namespace App\Http\Middleware;

use App\Services\LaunchControlService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireLaunchFeature
{
    public function __construct(private readonly LaunchControlService $launchControl) {}

    public function handle(Request $request, Closure $next, string ...$features): Response
    {
        foreach ($features as $feature) {
            if (! $this->launchControl->enabled($feature)) {
                return response()->json([
                    'message' => 'This feature is not currently available.',
                    'code' => 'feature_unavailable',
                    'feature' => $feature,
                ], 404);
            }
        }

        return $next($request);
    }
}
