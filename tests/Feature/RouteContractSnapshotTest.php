<?php

namespace Tests\Feature;

use Illuminate\Routing\Route as IlluminateRoute;
use Tests\TestCase;

class RouteContractSnapshotTest extends TestCase
{
    public function test_route_count_matches_the_current_snapshot(): void
    {
        $expectedCount = (int) config('architecture.route_snapshot.total_count', 0);
        $allowedCounts = collect((array) config('architecture.route_snapshot.allowed_total_counts', []))
            ->map(fn ($value): int => (int) $value)
            ->filter(fn (int $value): bool => $value > 0)
            ->unique()
            ->values();

        if ($expectedCount > 0 && ! $allowedCounts->contains($expectedCount)) {
            $allowedCounts->push($expectedCount);
        }

        $actualCount = count(app('router')->getRoutes());
        $this->assertContains(
            $actualCount,
            $allowedCounts->all(),
            sprintf(
                'Unexpected route count %d. Allowed counts: [%s].',
                $actualCount,
                $allowedCounts->implode(', ')
            )
        );
    }

    public function test_critical_routes_keep_their_contracts(): void
    {
        $routes = collect(app('router')->getRoutes())->map(function (IlluminateRoute $route): array {
            return [
                'methods' => $route->methods(),
                'uri' => $route->uri(),
                'name' => $route->getName(),
            ];
        });

        foreach (config('architecture.route_snapshot.critical_routes', []) as $expectedRoute) {
            $matched = $routes->first(function (array $route) use ($expectedRoute): bool {
                $expectedMethods = explode('|', $expectedRoute['method']);
                $methodMatches = count(array_intersect($expectedMethods, $route['methods'])) > 0;
                $uriMatches = $route['uri'] === $expectedRoute['uri'];
                $nameMatches = ($expectedRoute['name'] ?? null) === null || $route['name'] === $expectedRoute['name'];

                return $methodMatches && $uriMatches && $nameMatches;
            });

            $this->assertNotNull(
                $matched,
                sprintf(
                    'Missing route contract: %s %s [%s]',
                    $expectedRoute['method'],
                    $expectedRoute['uri'],
                    $expectedRoute['name'] ?? 'unnamed'
                )
            );
        }
    }
}
