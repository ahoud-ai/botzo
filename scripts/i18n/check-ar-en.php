<?php

declare(strict_types=1);

$rootPath = realpath(__DIR__ . '/../../');
if ($rootPath === false) {
    fwrite(STDERR, "Unable to resolve project root.\n");
    exit(1);
}

$options = parseCliOptions($argv);
$mode = resolveGateMode($options);
$strict = $mode === 'strict';

$outputDir = resolvePath($rootPath, (string) ($options['out-dir'] ?? 'tmp'));
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}

$staticOutput = normalizePath($outputDir . DIRECTORY_SEPARATOR . 'i18n-audit-static-ci.json');
$astOutput = normalizePath($outputDir . DIRECTORY_SEPARATOR . 'i18n-audit-ast-ci.json');
$optionsOutput = normalizePath($outputDir . DIRECTORY_SEPARATOR . 'i18n-audit-options-ci.json');
$mergedOutput = normalizePath($outputDir . DIRECTORY_SEPARATOR . 'i18n-audit-merged-ci.json');
$statusOutput = normalizePath($outputDir . DIRECTORY_SEPARATOR . 'i18n-check-ar-en-status.json');

$phpBinary = PHP_BINARY;
$commands = [
    [
        'label' => 'Static AR/EN i18n audit',
        'command' => buildStaticCommand($phpBinary, $rootPath, $staticOutput, $strict),
    ],
    [
        'label' => 'Template AST i18n audit',
        'command' => buildAstCommand($rootPath, $astOutput, $strict),
    ],
    [
        'label' => 'Options localization audit',
        'command' => buildOptionsCommand($phpBinary, $rootPath, $optionsOutput, $strict),
    ],
    [
        'label' => 'Merged i18n audit',
        'command' => buildMergeCommand($phpBinary, $rootPath, $staticOutput, $astOutput, $optionsOutput, $mergedOutput, $strict),
    ],
];

foreach ($commands as $item) {
    $result = runCommand($item['command'], $rootPath);
    if ($result['exit_code'] !== 0) {
        fwrite(STDERR, "[i18n:check:ar-en] {$item['label']} failed.\n");
        if ($result['stderr'] !== '') {
            fwrite(STDERR, $result['stderr'] . "\n");
        }
        if ($result['stdout'] !== '') {
            fwrite(STDOUT, $result['stdout'] . "\n");
        }
        writeStatus($statusOutput, $mode, false, null, [
            'step' => $item['label'],
            'exit_code' => $result['exit_code'],
            'stderr' => $result['stderr'],
            'stdout' => $result['stdout'],
        ]);
        exit(1);
    }
}

$mergedRaw = file_get_contents($mergedOutput);
$merged = is_string($mergedRaw) ? json_decode($mergedRaw, true) : null;
if (!is_array($merged)) {
    fwrite(STDERR, "[i18n:check:ar-en] Unable to parse merged report: {$mergedOutput}\n");
    writeStatus($statusOutput, $mode, false, null, [
        'step' => 'Merged i18n audit',
        'error' => 'invalid_merged_report',
    ]);
    exit(1);
}

$summary = $merged['summary'] ?? [];
$violations = [
    'missing_in_ar' => (int) ($summary['missing_in_ar'] ?? 0),
    'missing_in_en' => (int) ($summary['missing_in_en'] ?? 0),
    'missing_escaped_unique' => (int) ($summary['missing_escaped_unique'] ?? 0),
    'literal_untranslated' => (int) ($summary['literal_untranslated'] ?? 0),
    'template_text_untranslated' => (int) ($summary['template_text_untranslated'] ?? 0),
    'options_unlocalized_sources' => (int) ($summary['options_unlocalized_sources'] ?? 0),
    'ar_equal_en_nontechnical' => (int) ($summary['ar_equal_en_nontechnical'] ?? 0),
    'en_default_drift' => (int) ($summary['en_default_drift'] ?? 0),
];

$totalViolations = array_sum($violations);
$statusLine = "[i18n:check:ar-en] mode={$mode} violations={$totalViolations}";
fwrite(STDOUT, $statusLine . PHP_EOL);

if ($totalViolations > 0) {
    fwrite(STDOUT, json_encode($violations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
}

$passed = !$strict || $totalViolations === 0;
writeStatus($statusOutput, $mode, $passed, $violations, null);

if ($strict && $totalViolations > 0) {
    fwrite(STDERR, "[i18n:check:ar-en] Strict mode is active and violations were found.\n");
    exit(1);
}

if (!$strict && $totalViolations > 0) {
    fwrite(STDOUT, "[i18n:check:ar-en] Warning mode active: violations recorded without blocking.\n");
}

exit(0);

function parseCliOptions(array $argv): array
{
    $parsed = [];

    foreach ($argv as $index => $arg) {
        if ($index === 0 || !str_starts_with($arg, '--')) {
            continue;
        }

        $arg = substr($arg, 2);
        [$key, $value] = array_pad(explode('=', $arg, 2), 2, true);
        $parsed[$key] = $value;
    }

    return $parsed;
}

function resolveGateMode(array $options): string
{
    $explicit = strtolower(trim((string) ($options['mode'] ?? '')));
    if (in_array($explicit, ['warn', 'strict'], true)) {
        return $explicit;
    }

    $envMode = strtolower(trim((string) getenv('I18N_GATE_MODE')));
    if (in_array($envMode, ['warn', 'strict'], true)) {
        return $envMode;
    }

    $startedAtRaw = trim((string) getenv('I18N_GATE_STARTED_AT'));
    if ($startedAtRaw !== '') {
        try {
            $startedAt = new DateTimeImmutable($startedAtRaw);
            $warnHours = (int) (getenv('I18N_GATE_WARN_HOURS') ?: 48);
            $strictAt = $startedAt->modify('+' . max(1, $warnHours) . ' hours');
            $now = new DateTimeImmutable('now', $strictAt->getTimezone());

            return $now >= $strictAt ? 'strict' : 'warn';
        } catch (Throwable) {
            return 'warn';
        }
    }

    return 'strict';
}

function buildStaticCommand(string $phpBinary, string $rootPath, string $outputPath, bool $strict): array
{
    $command = [
        $phpBinary,
        $rootPath . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'i18n' . DIRECTORY_SEPARATOR . 'audit-ui-translations.php',
        '--locales=ar,en',
        '--scope=all',
        '--format=json',
        '--out=' . $outputPath,
    ];

    if ($strict) {
        $command[] = '--fail-on-missing-ar=1';
        $command[] = '--fail-on-missing-en=1';
        $command[] = '--fail-on-literal-untranslated=1';
        $command[] = '--fail-on-ar-equals-en=1';
        $command[] = '--fail-on-en-default-drift=1';
    }

    return $command;
}

function buildAstCommand(string $rootPath, string $outputPath, bool $strict): array
{
    $command = [
        'node',
        $rootPath . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'i18n' . DIRECTORY_SEPARATOR . 'audit-ui-template-ast.mjs',
        '--scope=all',
        '--format=json',
        '--out=' . $outputPath,
    ];

    if ($strict) {
        $command[] = '--fail-on-template-untranslated=1';
    }

    return $command;
}

function buildOptionsCommand(string $phpBinary, string $rootPath, string $outputPath, bool $strict): array
{
    $command = [
        $phpBinary,
        $rootPath . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'i18n' . DIRECTORY_SEPARATOR . 'audit-ui-options.php',
        '--format=json',
        '--out=' . $outputPath,
    ];

    if ($strict) {
        $command[] = '--fail-on-options-unlocalized=1';
    }

    return $command;
}

function buildMergeCommand(
    string $phpBinary,
    string $rootPath,
    string $staticOutput,
    string $astOutput,
    string $optionsOutput,
    string $mergedOutput,
    bool $strict
): array {
    $command = [
        $phpBinary,
        $rootPath . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'i18n' . DIRECTORY_SEPARATOR . 'audit-ui-merge.php',
        '--static=' . $staticOutput,
        '--ast=' . $astOutput,
        '--options=' . $optionsOutput,
        '--format=json',
        '--out=' . $mergedOutput,
    ];

    if ($strict) {
        $command[] = '--fail-on-missing-ar=1';
        $command[] = '--fail-on-missing-en=1';
        $command[] = '--fail-on-literal-untranslated=1';
        $command[] = '--fail-on-template-untranslated=1';
        $command[] = '--fail-on-options-unlocalized=1';
        $command[] = '--fail-on-ar-equals-en=1';
        $command[] = '--fail-on-en-default-drift=1';
    }

    return $command;
}

function runCommand(array $command, string $cwd): array
{
    $descriptorSpec = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptorSpec, $pipes, $cwd);
    if (!is_resource($process)) {
        return [
            'exit_code' => 1,
            'stdout' => '',
            'stderr' => 'Unable to start process.',
        ];
    }

    $stdout = stream_get_contents($pipes[1]) ?: '';
    $stderr = stream_get_contents($pipes[2]) ?: '';
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);

    return [
        'exit_code' => $exitCode,
        'stdout' => trim($stdout),
        'stderr' => trim($stderr),
    ];
}

function resolvePath(string $rootPath, string $path): string
{
    if ($path === '') {
        return $rootPath;
    }

    if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1 || str_starts_with($path, '/')) {
        return $path;
    }

    return $rootPath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
}

function normalizePath(string $path): string
{
    return str_replace('\\', '/', $path);
}

function writeStatus(string $statusPath, string $mode, bool $passed, ?array $violations, ?array $error): void
{
    $payload = [
        'generated_at' => date(DATE_ATOM),
        'mode' => $mode,
        'passed' => $passed,
        'violations' => $violations,
        'error' => $error,
    ];

    file_put_contents(
        $statusPath,
        json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL
    );
}
