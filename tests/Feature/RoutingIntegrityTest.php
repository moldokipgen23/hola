<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoutingIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_critical_web_and_api_routes_keep_distinct_names_and_methods(): void
    {
        $this->assertTrue(Route::has('admin.claims'));
        $this->assertTrue(Route::has('login'));
        $this->assertTrue(Route::has('api.login'));

        $this->assertSame('admin/claims', Route::getRoutes()->getByName('admin.claims')->uri());
        $this->assertContains('GET', Route::getRoutes()->getByName('admin.claims')->methods());
        $this->assertSame('login', Route::getRoutes()->getByName('login')->uri());
        $this->assertContains('GET', Route::getRoutes()->getByName('login')->methods());
        $this->assertSame('api/auth/login', Route::getRoutes()->getByName('api.login')->uri());
        $this->assertSame(['POST'], Route::getRoutes()->getByName('api.login')->methods());

        $this->get('/businesses')->assertOk()->assertViewIs('public.businesses');
        $this->getJson('/api/businesses')->assertOk()->assertJsonStructure(['businesses']);
    }

    public function test_health_routes_are_available(): void
    {
        $this->get('/up')->assertOk()->assertJson(['status' => 'ok']);
        $this->assertTrue(Route::has('health'));

        Cache::put('health:scheduler:last_run_at', now()->toIso8601String(), now()->addMinutes(10));

        $this->get('/health')->assertOk()->assertJson([
            'status' => 'ok',
            'checks' => [
                'application' => true,
                'database' => true,
                'storage' => true,
                'scheduler' => true,
                'queue' => 'not_required',
            ],
        ]);
    }

    public function test_route_names_are_unique(): void
    {
        $namedRoutes = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route) => $route->getName())
            ->filter();

        $duplicates = $namedRoutes->duplicates()->unique()->values()->all();

        $this->assertSame([], $duplicates, 'Duplicate route names: '.implode(', ', $duplicates));
    }

    public function test_admin_views_only_reference_defined_named_routes(): void
    {
        $missing = collect(glob(resource_path('views/admin/**/*.blade.php')))
            ->merge(glob(resource_path('views/admin/*.blade.php')))
            ->flatMap(function (string $path) {
                preg_match_all('/route\s*\(\s*[\'\"]([^\'\"]+)/', file_get_contents($path), $matches);

                return $matches[1] ?? [];
            })
            ->unique()
            ->reject(fn (string $name) => Route::has($name))
            ->values()
            ->all();

        $this->assertSame([], $missing, 'Undefined admin view routes: '.implode(', ', $missing));
    }
}
