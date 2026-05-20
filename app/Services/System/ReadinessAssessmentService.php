<?php

namespace App\Services\System;

use App\Contracts\QueueProfileContract;
use Symfony\Component\Process\Process;

class ReadinessAssessmentService
{
    private const AXIS_WEIGHTS = [
        'system_health' => 0.15,
        'migrations' => 0.10,
        'route_contracts' => 0.10,
        'code_quality' => 0.15,
        'test_reliability' => 0.15,
        'operational_security' => 0.15,
        'scalability' => 0.08,
        'cleanliness' => 0.07,
        'docs_code_parity' => 0.05,
    ];

    public function __construct(
        private readonly RuntimeReadinessService $runtimeReadiness,
        private readonly QueueProfileContract $queueProfile,
        private readonly DocsConsistencyService $docsConsistency,
    ) {
    }

    public function assess(array $options = []): array
    {
        $skipQuality = (bool) ($options['skip_quality'] ?? false);
        $skipTests = (bool) ($options['skip_tests'] ?? false);
        $skipSecurityAudits = (bool) ($options['skip_security_audits'] ?? false);

        $systemHealthAxis = $this->evaluateSystemHealth();
        $migrationsAxis = $this->evaluateMigrations();
        $routeContractsAxis = $this->evaluateRouteContracts();
        $qualityAxis = $this->evaluateCodeQuality($skipQuality);
        $testsAxis = $this->evaluateTestReliability($skipTests);
        $securityAxis = $this->evaluateOperationalSecurity($skipSecurityAudits);
        $scalabilityAxis = $this->evaluateScalability();
        $cleanlinessAxis = $this->evaluateCleanliness($qualityAxis);
        $docsParityReport = $this->docsConsistency->evaluate();
        $docsParityAxis = [
            'score' => (float) ($docsParityReport['score'] ?? 0.0),
            'status' => ($docsParityReport['status'] ?? 'drifted') === 'consistent' ? 'pass' : 'fail',
            'summary' => ($docsParityReport['status'] ?? '') === 'consistent'
                ? 'Documentation is aligned with code and route contracts.'
                : 'Documentation drift detected against runtime/code baseline.',
            'details' => $docsParityReport,
        ];

        $axes = [
            'system_health' => $systemHealthAxis,
            'migrations' => $migrationsAxis,
            'route_contracts' => $routeContractsAxis,
            'code_quality' => $qualityAxis,
            'test_reliability' => $testsAxis,
            'operational_security' => $securityAxis,
            'scalability' => $scalabilityAxis,
            'cleanliness' => $cleanlinessAxis,
            'docs_code_parity' => $docsParityAxis,
        ];

        $overallScore = $this->calculateWeightedScore($axes);

        return [
            'status' => $this->resolveOverallStatus($overallScore),
            'overall_score' => $overallScore,
            'axes' => $axes,
            'options' => [
                'skip_quality' => $skipQuality,
                'skip_tests' => $skipTests,
                'skip_security_audits' => $skipSecurityAudits,
            ],
            'weights' => self::AXIS_WEIGHTS,
            'timestamp' => now()->toISOString(),
        ];
    }

    private function evaluateSystemHealth(): array
    {
        $result = $this->runCommand(
            [PHP_BINARY, 'artisan', 'system:health-check', '--strict', '--no-ansi'],
            180
        );
        $passed = $result['exit_code'] === 0;

        return [
            'score' => $passed ? 100.0 : 0.0,
            'status' => $passed ? 'pass' : 'fail',
            'summary' => $passed
                ? 'system:health-check --strict passed.'
                : 'system:health-check --strict failed.',
            'details' => [
                'command' => $result['command'],
                'exit_code' => $result['exit_code'],
                'output_excerpt' => $this->excerpt($result['output']),
            ],
        ];
    }

    private function evaluateMigrations(): array
    {
        $result = $this->runCommand(
            [PHP_BINARY, 'artisan', 'migrate:status', '--no-ansi'],
            180
        );

        preg_match_all('/\[\d+\]\s+Ran/i', $result['output'], $ranMatches);
        preg_match_all('/\bPending\b/i', $result['output'], $pendingMatches);

        $ran = count($ranMatches[0] ?? []);
        $pending = count($pendingMatches[0] ?? []);
        $total = $ran + $pending;
        $score = $total > 0 ? round(($ran / $total) * 100, 2) : ($result['exit_code'] === 0 ? 100.0 : 0.0);
        $passed = $result['exit_code'] === 0 && $pending === 0;

        return [
            'score' => $score,
            'status' => $passed ? 'pass' : 'fail',
            'summary' => $passed
                ? 'All migrations are applied.'
                : 'Pending migrations detected or migrate:status failed.',
            'details' => [
                'command' => $result['command'],
                'exit_code' => $result['exit_code'],
                'ran' => $ran,
                'pending' => $pending,
                'output_excerpt' => $this->excerpt($result['output']),
            ],
        ];
    }

    private function evaluateRouteContracts(): array
    {
        $routes = collect(app('router')->getRoutes())->map(function ($route): array {
            return [
                'methods' => $route->methods(),
                'uri' => $route->uri(),
                'name' => $route->getName(),
            ];
        });

        $actualCount = $routes->count();
        $expectedCount = (int) config('architecture.route_snapshot.total_count', 0);
        $allowedCounts = $this->resolveAllowedRouteCounts($expectedCount);
        $countMatches = in_array($actualCount, $allowedCounts, true);
        $countScore = $countMatches ? 100.0 : 0.0;

        $criticalRoutes = (array) config('architecture.route_snapshot.critical_routes', []);
        $matchedCriticalRoutes = 0;
        $missingCriticalRoutes = [];

        foreach ($criticalRoutes as $expectedRoute) {
            $expectedMethods = explode('|', (string) ($expectedRoute['method'] ?? ''));

            $found = $routes->first(function (array $route) use ($expectedRoute, $expectedMethods): bool {
                $methodMatches = count(array_intersect($expectedMethods, (array) $route['methods'])) > 0;
                $uriMatches = ($expectedRoute['uri'] ?? null) === $route['uri'];
                $expectedName = $expectedRoute['name'] ?? null;
                $nameMatches = $expectedName === null || $expectedName === $route['name'];

                return $methodMatches && $uriMatches && $nameMatches;
            });

            if ($found === null) {
                $missingCriticalRoutes[] = [
                    'method' => $expectedRoute['method'] ?? null,
                    'uri' => $expectedRoute['uri'] ?? null,
                    'name' => $expectedRoute['name'] ?? null,
                ];
                continue;
            }

            $matchedCriticalRoutes++;
        }

        $criticalTotal = count($criticalRoutes);
        $criticalScore = $criticalTotal > 0
            ? round(($matchedCriticalRoutes / $criticalTotal) * 100, 2)
            : 100.0;
        $score = round(($countScore + $criticalScore) / 2, 2);
        $passed = $countMatches && $matchedCriticalRoutes === $criticalTotal;

        return [
            'score' => $score,
            'status' => $passed ? 'pass' : 'fail',
            'summary' => $passed
                ? 'Route snapshot and critical route contracts are aligned.'
                : 'Route snapshot or critical route contracts are not aligned.',
            'details' => [
                'actual_route_count' => $actualCount,
                'expected_route_count' => $expectedCount,
                'allowed_route_counts' => $allowedCounts,
                'critical_total' => $criticalTotal,
                'critical_matched' => $matchedCriticalRoutes,
                'critical_missing' => $missingCriticalRoutes,
            ],
        ];
    }

    /**
     * @return array<int>
     */
    private function resolveAllowedRouteCounts(int $expectedCount): array
    {
        $counts = collect((array) config('architecture.route_snapshot.allowed_total_counts', []))
            ->map(fn ($value): int => (int) $value)
            ->filter(fn (int $value): bool => $value > 0)
            ->unique()
            ->values()
            ->all();

        if ($expectedCount > 0 && ! in_array($expectedCount, $counts, true)) {
            $counts[] = $expectedCount;
        }

        sort($counts);

        return $counts;
    }

    private function evaluateCodeQuality(bool $skip): array
    {
        if ($skip) {
            return $this->skippedAxis('Code quality gates were skipped by option.');
        }

        $testingEnv = $this->readinessTestingEnvironment();

        $checks = [
            'repo_hygiene' => [
                'label' => __('composer check:hygiene'),
                'command' => ['composer', 'check:hygiene'],
                'timeout' => 300,
            ],
            'architecture' => [
                'label' => __('composer check:architecture'),
                'command' => [
                    PHP_BINARY,
                    'artisan',
                    'test',
                    '--env=testing',
                    'tests/Feature/ArchitectureEnvBoundaryTest.php',
                    'tests/Feature/ArchitectureBudgetGuardTest.php',
                    'tests/Feature/RouteContractSnapshotTest.php',
                    'tests/Unit/WhatsappAccountInspectionServiceTest.php',
                    '--no-ansi',
                ],
                'timeout' => 600,
                'env' => $testingEnv,
            ],
            'php_static_analysis' => [
                'label' => __('composer analyse:php'),
                'command' => ['composer', 'analyse:php'],
                'timeout' => 1800,
            ],
            'frontend_lint' => [
                'label' => __('npm run lint'),
                'command' => ['npm', 'run', 'lint'],
                'timeout' => 900,
            ],
        ];

        $results = [];
        $passed = 0;

        foreach ($checks as $key => $check) {
            $result = $this->runCommand($check['command'], (int) $check['timeout'], $check['env'] ?? null);
            $ok = $result['exit_code'] === 0;
            if ($ok) {
                $passed++;
            }

            $results[$key] = [
                'label' => $check['label'],
                'passed' => $ok,
                'exit_code' => $result['exit_code'],
                'output_excerpt' => $this->excerpt($result['output']),
            ];
        }

        $totalChecks = count($checks);
        $score = $totalChecks > 0 ? round(($passed / $totalChecks) * 100, 2) : 100.0;
        $allPassed = $passed === $totalChecks;

        return [
            'score' => $score,
            'status' => $allPassed ? 'pass' : 'fail',
            'summary' => $allPassed
                ? 'All code quality gates passed.'
                : 'One or more code quality gates failed.',
            'details' => [
                'passed' => $passed,
                'total' => $totalChecks,
                'checks' => $results,
            ],
        ];
    }

    private function evaluateTestReliability(bool $skip): array
    {
        if ($skip) {
            return $this->skippedAxis('Test reliability gate was skipped by option.');
        }

        $testingEnv = $this->readinessTestingEnvironment();

        $safety = $this->runCommand(
            [PHP_BINARY, 'artisan', 'system:test-safety-check', '--env=testing', '--no-ansi'],
            180,
            $testingEnv
        );

        if ($safety['exit_code'] !== 0) {
            return [
                'score' => 0.0,
                'status' => 'fail',
                'summary' => 'Test reliability gate blocked by test safety check.',
                'details' => [
                    'test_safety' => [
                        'command' => $safety['command'],
                        'exit_code' => $safety['exit_code'],
                        'output_excerpt' => $this->excerpt($safety['output']),
                    ],
                ],
            ];
        }

        $result = $this->runCommand(
            [PHP_BINARY, 'artisan', 'test', '--env=testing', '--no-ansi'],
            3600,
            $testingEnv
        );
        $summary = $this->parseTestSummary($result['output']);
        $total = (int) $summary['passed'] + (int) $summary['failed'];
        $rawScore = $total > 0 ? round(((int) $summary['passed'] / $total) * 100, 2) : 0.0;
        $penalty = ((int) $summary['failed']) > 0 ? 10.0 : 0.0;
        $score = max(0.0, round($rawScore - $penalty, 2));
        $passed = $result['exit_code'] === 0 && ((int) $summary['failed']) === 0;

        return [
            'score' => $score,
            'status' => $passed ? 'pass' : 'fail',
            'summary' => $passed
                ? 'Full test suite passed with no failures.'
                : 'Test failures detected or test suite execution failed.',
            'details' => [
                'command' => $result['command'],
                'exit_code' => $result['exit_code'],
                'passed' => (int) $summary['passed'],
                'failed' => (int) $summary['failed'],
                'assertions' => (int) $summary['assertions'],
                'raw_score' => $rawScore,
                'stability_penalty' => $penalty,
                'output_excerpt' => $this->excerpt($result['output']),
            ],
        ];
    }

    private function evaluateOperationalSecurity(bool $skip): array
    {
        if ($skip) {
            return $this->skippedAxis('Operational security audit checks were skipped by option.');
        }

        $testingEnv = $this->readinessTestingEnvironment();

        $testSafety = $this->runCommand(
            [PHP_BINARY, 'artisan', 'system:test-safety-check', '--env=testing', '--no-ansi'],
            180,
            $testingEnv
        );
        $composerAudit = $this->runCommand(
            ['composer', 'audit', '--locked', '--no-dev', '--format=json'],
            600
        );
        $npmAudit = $this->runCommand(
            ['npm', 'audit', '--omit=dev', '--json'],
            600
        );

        $composerAuditJson = $this->decodeLooseJson($composerAudit['output']);
        $composerHighCritical = $this->countComposerHighCritical($composerAuditJson);
        $npmAuditJson = $this->decodeLooseJson($npmAudit['output']);
        $npmHighCritical = $this->countNpmHighCritical($npmAuditJson);

        $checks = [
            'test_safety' => [
                'label' => 'system:test-safety-check',
                'passed' => $testSafety['exit_code'] === 0,
                'exit_code' => $testSafety['exit_code'],
                'output_excerpt' => $this->excerpt($testSafety['output']),
            ],
            'composer_audit_high_critical' => [
                'label' => __('composer audit --locked --no-dev --format=json'),
                'passed' => $composerHighCritical === 0,
                'exit_code' => $composerAudit['exit_code'],
                'high_critical' => $composerHighCritical,
                'output_excerpt' => $this->excerpt($composerAudit['output']),
            ],
            'npm_audit_high_critical' => [
                'label' => __('npm audit --omit=dev --json'),
                'passed' => $npmHighCritical === 0,
                'exit_code' => $npmAudit['exit_code'],
                'high_critical' => $npmHighCritical,
                'output_excerpt' => $this->excerpt($npmAudit['output']),
            ],
        ];

        $passedChecks = collect($checks)->filter(fn (array $check): bool => (bool) $check['passed'])->count();
        $score = round(($passedChecks / count($checks)) * 100, 2);
        $allPassed = $passedChecks === count($checks);

        return [
            'score' => $score,
            'status' => $allPassed ? 'pass' : 'fail',
            'summary' => $allPassed
                ? 'Operational security checks passed.'
                : 'Operational security checks reported blockers.',
            'details' => [
                'passed' => $passedChecks,
                'total' => count($checks),
                'checks' => $checks,
            ],
        ];
    }

    private function readinessTestingEnvironment(): array
    {
        $testingConnection = $this->readinessEnvValue('READINESS_TEST_DB_CONNECTION')
            ?: (string) config('database.default', 'mysql');
        if ($testingConnection !== 'mysql') {
            $testingConnection = 'mysql';
        }

        $testingDatabase = $this->readinessEnvValue('READINESS_TEST_DB_DATABASE')
            ?: (string) config("database.connections.{$testingConnection}.database", 'app_testing');
        if (
            $testingDatabase === ''
            || strtolower($testingDatabase) === 'app'
            || ! preg_match('/(^|_)(test|testing)(_|$)/i', $testingDatabase)
        ) {
            $testingDatabase = 'app_testing';
        }
        $testingHost = $this->readinessEnvValue('READINESS_TEST_DB_HOST')
            ?: (string) config("database.connections.{$testingConnection}.host", '127.0.0.1');
        $testingPort = $this->readinessEnvValue('READINESS_TEST_DB_PORT')
            ?: (string) config("database.connections.{$testingConnection}.port", '3306');
        $testingUsername = $this->readinessEnvValue('READINESS_TEST_DB_USERNAME')
            ?: (string) config("database.connections.{$testingConnection}.username", '');
        $testingPassword = $this->readinessEnvValue('READINESS_TEST_DB_PASSWORD')
            ?: (string) config("database.connections.{$testingConnection}.password", '');

        return [
            'APP_ENV' => 'testing',
            'APP_CONFIG_CACHE' => 'bootstrap/cache/config-readiness-testing.php',
            'APP_ROUTES_CACHE' => 'bootstrap/cache/routes-readiness-testing.php',
            'APP_SERVICES_CACHE' => 'bootstrap/cache/services-readiness-testing.php',
            'APP_PACKAGES_CACHE' => 'bootstrap/cache/packages-readiness-testing.php',
            'APP_EVENTS_CACHE' => 'bootstrap/cache/events-readiness-testing.php',
            'DB_CONNECTION' => $testingConnection,
            'DB_DATABASE' => $testingDatabase,
            'DB_HOST' => $testingHost,
            'DB_PORT' => $testingPort,
            'DB_USERNAME' => $testingUsername,
            'DB_PASSWORD' => $testingPassword,
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'QUEUE_PROFILE_NAME' => 'production',
            'QUEUE_PROFILE_READINESS' => 'production',
            'QUEUE_PROFILE_CONNECTION' => 'redis',
            'QUEUE_PROFILE_CACHE_STORE' => 'redis',
            'QUEUE_PROFILE_SESSION_DRIVER' => 'redis',
            'QUEUE_PROFILE_SHARED_CONNECTION' => 'database',
            'QUEUE_PROFILE_SHARED_CACHE_STORE' => 'file',
            'QUEUE_PROFILE_SHARED_SESSION_DRIVER' => 'file',
        ];
    }

    private function readinessEnvValue(string $key): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value === false || $value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function evaluateScalability(): array
    {
        $runtime = $this->runtimeReadiness->evaluate();
        $activeProfileName = (string) $this->queueProfile->getName();
        $profileContext = $this->resolveReadinessScalabilityProfile();
        $profileName = (string) ($profileContext['name'] ?? $activeProfileName);
        $connection = (string) ($profileContext['connection'] ?? $this->queueProfile->getConnection());
        $cacheStore = (string) ($profileContext['cache_store'] ?? $this->queueProfile->getCacheStore());
        $sessionDriver = (string) ($profileContext['session_driver'] ?? $this->queueProfile->getSessionDriver());
        $workers = (array) ($profileContext['workers'] ?? $this->queueProfile->getWorkers());

        $runtimePoints = ($runtime['status'] ?? null) === 'ready' ? 40 : 0;
        $profilePoints = in_array($profileName, ['production', 'scale-ready'], true)
            ? 30
            : ($profileName === 'shared' ? 15 : 0);
        $connectionPoints = $connection === 'redis' ? 10 : ($connection === 'database' ? 5 : 0);
        $cachePoints = $cacheStore === 'redis' ? 10 : ($cacheStore === 'file' ? 5 : 0);
        $sessionPoints = $sessionDriver === 'redis' ? 10 : ($sessionDriver === 'file' ? 5 : 0);

        $score = round(
            $runtimePoints + $profilePoints + $connectionPoints + $cachePoints + $sessionPoints,
            2
        );

        $status = $score >= 85
            ? 'pass'
            : ($score >= 60 ? 'warning' : 'fail');

        return [
            'score' => $score,
            'status' => $status,
            'summary' => $status === 'pass'
                ? 'Runtime profile is scale-ready.'
                : 'Runtime profile is operational but not fully scale-ready.',
            'details' => [
                'profile' => $profileName,
                'active_profile' => $activeProfileName,
                'profile_source' => (string) ($profileContext['source'] ?? 'active'),
                'connection' => $connection,
                'cache_store' => $cacheStore,
                'session_driver' => $sessionDriver,
                'workers' => $workers,
                'runtime_readiness' => $runtime,
                'scoring' => [
                    'runtime_points' => $runtimePoints,
                    'profile_points' => $profilePoints,
                    'connection_points' => $connectionPoints,
                    'cache_points' => $cachePoints,
                    'session_points' => $sessionPoints,
                ],
            ],
        ];
    }

    private function evaluateCleanliness(array $qualityAxis): array
    {
        $caps = (array) config('architecture.budgets.previous_caps', []);
        $hotspots = [];
        $withinBudget = 0;

        foreach ($caps as $relativePath => $cap) {
            $absolutePath = base_path((string) $relativePath);
            $lineCount = $this->countFileLines($absolutePath);
            $isWithinBudget = $lineCount !== null && $lineCount <= (int) $cap;

            if ($isWithinBudget) {
                $withinBudget++;
            }

            $hotspots[] = [
                'path' => (string) $relativePath,
                'cap' => (int) $cap,
                'lines' => $lineCount,
                'within_budget' => $isWithinBudget,
            ];
        }

        $totalHotspots = count($hotspots);
        $budgetCompliance = $totalHotspots > 0
            ? round(($withinBudget / $totalHotspots) * 100, 2)
            : 100.0;
        $budgetPoints = round($budgetCompliance * 0.70, 2);

        $qualityStatus = $qualityAxis['status'] ?? 'skipped';
        $hygienePoints = match ($qualityStatus) {
            'pass' => 30.0,
            'fail' => 0.0,
            default => 15.0,
        };

        $score = min(100.0, round($budgetPoints + $hygienePoints, 2));
        $status = $score >= 90
            ? 'pass'
            : ($score >= 70 ? 'warning' : 'fail');

        return [
            'score' => $score,
            'status' => $status,
            'summary' => $status === 'pass'
                ? 'Hotspots are within architecture budgets and hygiene is healthy.'
                : 'Project cleanliness has budget/hygiene debt that should be addressed.',
            'details' => [
                'budget_compliance' => $budgetCompliance,
                'hotspots_within_budget' => $withinBudget,
                'hotspots_total' => $totalHotspots,
                'hotspots' => $hotspots,
                'hygiene_points' => $hygienePoints,
            ],
        ];
    }

    private function calculateWeightedScore(array $axes): float
    {
        $weightedTotal = 0.0;
        $weightTotal = 0.0;

        foreach (self::AXIS_WEIGHTS as $axis => $weight) {
            $axisScore = $axes[$axis]['score'] ?? null;
            if ($axisScore === null) {
                continue;
            }

            $weightedTotal += ((float) $axisScore) * $weight;
            $weightTotal += $weight;
        }

        if ($weightTotal <= 0.0) {
            return 0.0;
        }

        return round($weightedTotal / $weightTotal, 2);
    }

    private function resolveOverallStatus(float $overallScore): string
    {
        if ($overallScore >= 95.0) {
            return 'strong';
        }

        if ($overallScore >= 80.0) {
            return 'acceptable';
        }

        if ($overallScore >= 60.0) {
            return 'needs_attention';
        }

        return 'at_risk';
    }

    private function parseTestSummary(string $output): array
    {
        $summary = [
            'passed' => 0,
            'failed' => 0,
            'assertions' => 0,
        ];

        $lines = preg_split('/\r\n|\r|\n/', $output) ?: [];
        for ($index = count($lines) - 1; $index >= 0; $index--) {
            $line = trim((string) ($lines[$index] ?? ''));
            if ($line === '') {
                continue;
            }

            if (preg_match('/^Tests:\s+(\d+)\s+failed,\s+(\d+)\s+passed\s+\((\d+)\s+assertions\)$/i', $line, $matches) === 1) {
                return [
                    'failed' => (int) $matches[1],
                    'passed' => (int) $matches[2],
                    'assertions' => (int) $matches[3],
                ];
            }

            if (preg_match('/^Tests:\s+(\d+)\s+passed\s+\((\d+)\s+assertions\)$/i', $line, $matches) === 1) {
                return [
                    'failed' => 0,
                    'passed' => (int) $matches[1],
                    'assertions' => (int) $matches[2],
                ];
            }
        }

        return $summary;
    }

    private function resolveReadinessScalabilityProfile(): array
    {
        $configuredProfile = trim((string) config('queue_profile.readiness_profile', 'production'));
        if ($configuredProfile !== '') {
            $profile = config("queue_profile.{$configuredProfile}");
            if (is_array($profile)) {
                return [
                    'name' => $configuredProfile,
                    'source' => 'readiness_profile',
                    'connection' => (string) ($profile['connection'] ?? $this->queueProfile->getConnection()),
                    'cache_store' => (string) ($profile['cache_store'] ?? $this->queueProfile->getCacheStore()),
                    'session_driver' => (string) ($profile['session_driver'] ?? $this->queueProfile->getSessionDriver()),
                    'workers' => (array) ($profile['workers'] ?? $this->queueProfile->getWorkers()),
                ];
            }
        }

        return [
            'name' => (string) $this->queueProfile->getName(),
            'source' => 'active',
            'connection' => (string) $this->queueProfile->getConnection(),
            'cache_store' => (string) $this->queueProfile->getCacheStore(),
            'session_driver' => (string) $this->queueProfile->getSessionDriver(),
            'workers' => (array) $this->queueProfile->getWorkers(),
        ];
    }

    private function countComposerHighCritical(?array $payload): int
    {
        if (!is_array($payload)) {
            return 1;
        }

        $count = 0;
        foreach ((array) ($payload['advisories'] ?? []) as $items) {
            foreach ((array) $items as $advisory) {
                $severity = strtolower((string) ($advisory['severity'] ?? ''));
                if (in_array($severity, ['high', 'critical'], true)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    private function countNpmHighCritical(?array $payload): int
    {
        if (!is_array($payload)) {
            return 1;
        }

        $high = (int) data_get($payload, 'metadata.vulnerabilities.high', 0);
        $critical = (int) data_get($payload, 'metadata.vulnerabilities.critical', 0);

        return $high + $critical;
    }

    private function decodeLooseJson(string $raw): ?array
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }

        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $firstBrace = strpos($trimmed, '{');
        $lastBrace = strrpos($trimmed, '}');
        if ($firstBrace === false || $lastBrace === false || $lastBrace <= $firstBrace) {
            return null;
        }

        $jsonSlice = substr($trimmed, $firstBrace, ($lastBrace - $firstBrace) + 1);
        $decoded = json_decode($jsonSlice, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function countFileLines(string $path): ?int
    {
        if (!is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        return substr_count($contents, "\n") + 1;
    }

    private function skippedAxis(string $reason): array
    {
        return [
            'score' => null,
            'status' => 'skipped',
            'summary' => $reason,
            'details' => [],
        ];
    }

    private function runCommand(array $command, int $timeoutSeconds, ?array $environment = null): array
    {
        $startedAt = microtime(true);

        try {
            $process = new Process($command, base_path(), $environment);
            $process->setTimeout($timeoutSeconds);
            $process->run();

            return [
                'command' => implode(' ', $command),
                'exit_code' => $process->getExitCode() ?? 1,
                'output' => trim($process->getOutput().$process->getErrorOutput()),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ];
        } catch (\Throwable $exception) {
            return [
                'command' => implode(' ', $command),
                'exit_code' => 1,
                'output' => $exception->getMessage(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ];
        }
    }

    private function excerpt(string $output, int $maxLines = 14): string
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($output)) ?: [];
        $lines = array_values(array_filter($lines, static fn (string $line): bool => trim($line) !== ''));

        if (count($lines) <= $maxLines) {
            return implode(PHP_EOL, $lines);
        }

        $slice = array_slice($lines, 0, $maxLines);
        $slice[] = '...';

        return implode(PHP_EOL, $slice);
    }
}
