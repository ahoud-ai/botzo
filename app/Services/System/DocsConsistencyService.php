<?php

namespace App\Services\System;

use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\File;

class DocsConsistencyService
{
    public function evaluate(): array
    {
        $routes = collect(app('router')->getRoutes());
        $actualRouteCount = $routes->count();
        $actualFlowRoutesCount = $routes
            ->filter(fn (IlluminateRoute $route): bool => str_starts_with($route->uri(), 'automation/flows'))
            ->count();
        $snapshotRouteCount = (int) config('architecture.route_snapshot.total_count', 0);
        $allowedRouteCounts = $this->resolveAllowedRouteCounts($snapshotRouteCount);
        $actualRouteCountWithinSnapshot = in_array($actualRouteCount, $allowedRouteCounts, true);

        $readmePath = base_path('README.md');
        $readmeContent = File::exists($readmePath)
            ? (string) File::get($readmePath)
            : '';

        $readmeRouteCount = $this->extractReadmeRouteCount($readmeContent);
        $readmeFlowRoutesCount = $this->extractReadmeFlowRoutesCount($readmeContent);
        $criticalRoutes = $this->evaluateCriticalRouteContracts();

        $mismatches = [];
        $warnings = [];

        if (! $actualRouteCountWithinSnapshot) {
            $mismatches[] = sprintf(
                'Route snapshot mismatch: config/architecture.php expects one of [%s], actual=%d.',
                implode(', ', $allowedRouteCounts),
                $actualRouteCount
            );
        }

        if (
            $readmeRouteCount !== null
            && $readmeRouteCount !== $actualRouteCount
            && ! (in_array($readmeRouteCount, $allowedRouteCounts, true) && $actualRouteCountWithinSnapshot)
        ) {
            $mismatches[] = sprintf(
                'README route mismatch: README.md=%d, actual=%d.',
                $readmeRouteCount,
                $actualRouteCount
            );
        }

        if ($readmeFlowRoutesCount !== null && $readmeFlowRoutesCount !== $actualFlowRoutesCount) {
            $mismatches[] = sprintf(
                'README flow routes mismatch: README.md=%d, actual=%d.',
                $readmeFlowRoutesCount,
                $actualFlowRoutesCount
            );
        }

        if (($criticalRoutes['matched'] ?? 0) !== ($criticalRoutes['expected'] ?? 0)) {
            $mismatches[] = sprintf(
                'Critical route contracts mismatch: matched=%d expected=%d.',
                $criticalRoutes['matched'] ?? 0,
                $criticalRoutes['expected'] ?? 0
            );
        }

        if ($readmeRouteCount === null) {
            $warnings[] = 'Could not extract route count from README.md.';
        }

        if ($readmeFlowRoutesCount === null) {
            $warnings[] = 'Could not extract Flow Builder route count from README.md.';
        }

        $score = max(0.0, 100.0 - (count($mismatches) * 25.0) - (count($warnings) * 5.0));

        return [
            'status' => $mismatches === [] ? 'consistent' : 'drifted',
            'score' => round($score, 2),
            'actual' => [
                'route_count' => $actualRouteCount,
                'flow_routes_count' => $actualFlowRoutesCount,
                'critical_routes' => $criticalRoutes,
            ],
            'documents' => [
                'readme' => [
                    'path' => $readmePath,
                    'route_count' => $readmeRouteCount,
                    'flow_routes_count' => $readmeFlowRoutesCount,
                ],
                'config' => [
                    'path' => base_path('config/architecture.php'),
                    'route_snapshot_total_count' => $snapshotRouteCount,
                    'route_snapshot_allowed_total_counts' => $allowedRouteCounts,
                ],
            ],
            'mismatches' => $mismatches,
            'warnings' => $warnings,
            'timestamp' => now()->toISOString(),
        ];
    }

    private function evaluateCriticalRouteContracts(): array
    {
        $expectedRoutes = (array) config('architecture.route_snapshot.critical_routes', []);

        $routes = collect(app('router')->getRoutes())->map(function (IlluminateRoute $route): array {
            return [
                'methods' => $route->methods(),
                'uri' => $route->uri(),
                'name' => $route->getName(),
            ];
        });

        $matched = 0;
        $missing = [];

        foreach ($expectedRoutes as $expectedRoute) {
            $expectedMethods = explode('|', (string) ($expectedRoute['method'] ?? ''));

            $found = $routes->first(function (array $route) use ($expectedRoute, $expectedMethods): bool {
                $methodMatches = count(array_intersect($expectedMethods, (array) $route['methods'])) > 0;
                $uriMatches = ($expectedRoute['uri'] ?? null) === $route['uri'];
                $expectedName = $expectedRoute['name'] ?? null;
                $nameMatches = $expectedName === null || $expectedName === $route['name'];

                return $methodMatches && $uriMatches && $nameMatches;
            });

            if ($found === null) {
                $missing[] = [
                    'method' => $expectedRoute['method'] ?? null,
                    'uri' => $expectedRoute['uri'] ?? null,
                    'name' => $expectedRoute['name'] ?? null,
                ];
                continue;
            }

            $matched++;
        }

        return [
            'expected' => count($expectedRoutes),
            'matched' => $matched,
            'missing' => $missing,
        ];
    }

    private function extractReadmeRouteCount(string $content): ?int
    {
        if ($content === '') {
            return null;
        }

        if (preg_match('/route snapshot[^\n]*`(\d+)`\s*route/iu', $content, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    private function extractReadmeFlowRoutesCount(string $content): ?int
    {
        if ($content === '') {
            return null;
        }

        if (preg_match('/`(\d+)`\s*routes\s*مستقلة\s*تحت\s*`\/automation\/flows`/iu', $content, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * @return array<int>
     */
    private function resolveAllowedRouteCounts(int $snapshotRouteCount): array
    {
        $counts = collect((array) config('architecture.route_snapshot.allowed_total_counts', []))
            ->map(fn ($value): int => (int) $value)
            ->filter(fn (int $value): bool => $value > 0)
            ->unique()
            ->values()
            ->all();

        if ($snapshotRouteCount > 0 && ! in_array($snapshotRouteCount, $counts, true)) {
            $counts[] = $snapshotRouteCount;
        }

        sort($counts);

        return $counts;
    }
}
