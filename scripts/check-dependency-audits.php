<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root.'/vendor/autoload.php';

$format = optionValue('--format', 'text');
$strict = hasOption('--strict');

$composer = runAuditCommand(['composer', 'audit', '--locked', '--no-dev', '--format=json'], $root);
$npm = runAuditCommand(['npm', 'audit', '--omit=dev', '--json'], $root);

$composerPayload = decodeJsonPayload($composer['output']);
$npmPayload = decodeJsonPayload($npm['output']);

$composerCounts = $composerPayload === null
    ? severityCounts()
    : countComposerSeverities($composerPayload);
$npmCounts = $npmPayload === null
    ? severityCounts()
    : countNpmSeverities($npmPayload);

$highCritical = (int) $composerCounts['high']
    + (int) $composerCounts['critical']
    + (int) $npmCounts['high']
    + (int) $npmCounts['critical'];

$invalidPayloads = [];
if ($composerPayload === null) {
    $invalidPayloads[] = 'composer';
}
if ($npmPayload === null) {
    $invalidPayloads[] = 'npm';
}

$payload = [
    'status' => $highCritical === 0 && $invalidPayloads === [] ? 'ok' : 'blocked',
    'gate' => 'high_critical_dependency_audits',
    'strict' => $strict,
    'high_critical_count' => $highCritical,
    'composer' => [
        'exit_code' => $composer['exit_code'],
        'severity_counts' => $composerCounts,
        'packages' => composerAdvisoryPackages($composerPayload),
    ],
    'npm' => [
        'exit_code' => $npm['exit_code'],
        'severity_counts' => $npmCounts,
        'packages' => npmAdvisoryPackages($npmPayload),
    ],
    'invalid_payloads' => $invalidPayloads,
];

if ($format === 'json') {
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
} else {
    echo 'Dependency audit gate: '.$payload['status'].PHP_EOL;
    echo 'High/Critical advisories: '.$payload['high_critical_count'].PHP_EOL;
    echo 'Composer: '.json_encode($payload['composer']['severity_counts']).PHP_EOL;
    echo 'NPM: '.json_encode($payload['npm']['severity_counts']).PHP_EOL;
}

if ($strict && ($highCritical > 0 || $invalidPayloads !== [])) {
    exit(1);
}

exit(0);

function hasOption(string $option): bool
{
    return in_array($option, $_SERVER['argv'] ?? [], true);
}

function optionValue(string $option, string $default): string
{
    $argv = $_SERVER['argv'] ?? [];
    foreach ($argv as $index => $arg) {
        if ($arg === $option && isset($argv[$index + 1])) {
            return (string) $argv[$index + 1];
        }

        if (str_starts_with((string) $arg, $option.'=')) {
            return substr((string) $arg, strlen($option) + 1);
        }
    }

    return $default;
}

function runAuditCommand(array $command, string $cwd): array
{
    try {
        $process = new Symfony\Component\Process\Process($command, $cwd);
        $process->setTimeout(600);
        $process->run();

        return [
            'exit_code' => $process->getExitCode() ?? 1,
            'output' => trim($process->getOutput()."\n".$process->getErrorOutput()),
        ];
    } catch (Throwable $exception) {
        return [
            'exit_code' => 1,
            'output' => $exception->getMessage(),
        ];
    }
}

function decodeJsonPayload(string $raw): ?array
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

    $candidate = substr($trimmed, $firstBrace, ($lastBrace - $firstBrace) + 1);
    $decoded = json_decode($candidate, true);

    return is_array($decoded) ? $decoded : null;
}

function severityCounts(): array
{
    return [
        'info' => 0,
        'low' => 0,
        'moderate' => 0,
        'high' => 0,
        'critical' => 0,
        'unknown' => 0,
    ];
}

function countComposerSeverities(array $payload): array
{
    $counts = severityCounts();
    foreach ((array) ($payload['advisories'] ?? []) as $items) {
        foreach ((array) $items as $advisory) {
            $severity = strtolower((string) ($advisory['severity'] ?? 'unknown'));
            $severity = array_key_exists($severity, $counts) ? $severity : 'unknown';
            $counts[$severity]++;
        }
    }

    return $counts;
}

function countNpmSeverities(array $payload): array
{
    $counts = severityCounts();
    foreach ($counts as $severity => $_) {
        $counts[$severity] = (int) ($payload['metadata']['vulnerabilities'][$severity] ?? 0);
    }

    return $counts;
}

function composerAdvisoryPackages(?array $payload): array
{
    if ($payload === null) {
        return [];
    }

    return array_values(array_keys((array) ($payload['advisories'] ?? [])));
}

function npmAdvisoryPackages(?array $payload): array
{
    if ($payload === null) {
        return [];
    }

    return array_values(array_keys((array) ($payload['vulnerabilities'] ?? [])));
}
