param(
    [switch]$SkipTests,
    [switch]$SkipBuild,
    [switch]$RequireCleanGit
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

function Invoke-CheckedCommand {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Command
    )

    Write-Host ">> $Command"
    Invoke-Expression $Command
    if ($LASTEXITCODE -ne 0) {
        throw "Command failed with exit code ${LASTEXITCODE}: $Command"
    }
}

function Assert-NoForbiddenTempFiles {
    $forbiddenPaths = @(
        "_write_test.txt",
        "_root_write_test.txt",
        "docroot.php",
        "root-ok.txt",
        "public/__probe.txt",
        "public/docroot.php",
        "public/public-ok.txt"
    )

    $existing = @()
    foreach ($path in $forbiddenPaths) {
        if (Test-Path $path) {
            $existing += $path
        }
    }

    if ($existing.Count -gt 0) {
        throw "Forbidden temporary/probe files detected: $($existing -join ', ')"
    }
}

function Convert-JsonStrict {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Raw,
        [Parameter(Mandatory = $true)]
        [string]$Label
    )

    $candidate = ($Raw ?? '').Trim()
    if (-not $candidate) {
        throw "$Label returned empty output."
    }

    try {
        return $candidate | ConvertFrom-Json -ErrorAction Stop
    }
    catch {
        $startCandidates = @()
        $objectStart = $candidate.IndexOf('{')
        if ($objectStart -ge 0) {
            $startCandidates += $objectStart
        }

        $arrayStart = $candidate.IndexOf('[')
        if ($arrayStart -ge 0) {
            $startCandidates += $arrayStart
        }

        $startIndex = $null
        if ($startCandidates.Count -gt 0) {
            $startIndex = ($startCandidates | Measure-Object -Minimum).Minimum
        }

        if ($startIndex -is [int] -and $startIndex -ge 0 -and $startIndex -lt $candidate.Length) {
            $normalized = $candidate.Substring($startIndex).Trim()
            try {
                return $normalized | ConvertFrom-Json -ErrorAction Stop
            }
            catch {
                # Fall through to include preview of the original raw output.
            }
        }

        $preview = (($Raw -split "`r?`n") | Select-Object -First 12) -join "`n"
        throw "$Label did not return valid JSON. First lines:`n$preview"
    }
}

function Invoke-CommandCapture {
    param(
        [Parameter(Mandatory = $true)]
        [string]$CommandLine
    )

    $raw = Invoke-Expression "$CommandLine 2>&1" | Out-String
    $exitCode = $LASTEXITCODE

    return @{
        output = ($raw ?? '').Trim()
        code = $exitCode
    }
}

function Get-ArtisanConfigValue {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Key
    )

    $result = Invoke-CommandCapture -CommandLine "php artisan --no-ansi config:show $Key"
    if ($result.code -ne 0) {
        throw "Unable to read runtime config key [$Key]."
    }

    if (-not $result.output) {
        throw "Empty config:show output for key [$Key]."
    }

    $line = ($result.output -split "`r?`n" | Where-Object { $_ -match [regex]::Escape($Key) } | Select-Object -First 1)
    if (-not $line) {
        throw "Could not parse config:show output for key [$Key]."
    }

    if ($line -match '\.{2,}\s+(?<value>.+?)\s*$') {
        return $matches['value'].Trim()
    }

    throw "Could not extract value for key [$Key] from config:show output."
}

function Assert-DependencyAuditsHighCriticalClear {
    Write-Host "==> Dependency audit gate (high/critical)"

    $composerResult = Invoke-CommandCapture -CommandLine "composer audit --locked --no-dev --format=json"
    if (-not $composerResult.output) {
        throw "Composer audit returned no output (exit=$($composerResult.code)). Check network/cache permissions."
    }

    $composerAudit = Convert-JsonStrict -Raw $composerResult.output -Label 'Composer audit'
    $composerHighCritical = 0

    if ($composerAudit.advisories) {
        foreach ($package in $composerAudit.advisories.PSObject.Properties.Name) {
            foreach ($advisory in $composerAudit.advisories.$package) {
                if (($advisory.severity -eq 'high') -or ($advisory.severity -eq 'critical')) {
                    $composerHighCritical++
                }
            }
        }
    }

    if ($composerHighCritical -gt 0) {
        throw "Composer audit has $composerHighCritical high/critical advisories."
    }

    $npmResult = Invoke-CommandCapture -CommandLine "npm audit --omit=dev --json"
    if (-not $npmResult.output) {
        throw "NPM audit returned no output (exit=$($npmResult.code)). Check npm/node permissions or network."
    }

    $npmAudit = Convert-JsonStrict -Raw $npmResult.output -Label 'NPM audit'
    $npmHigh = [int]($npmAudit.metadata.vulnerabilities.high ?? 0)
    $npmCritical = [int]($npmAudit.metadata.vulnerabilities.critical ?? 0)

    if (($npmHigh + $npmCritical) -gt 0) {
        throw "NPM audit has high/critical vulnerabilities. High=$npmHigh Critical=$npmCritical."
    }

    if ($composerResult.code -ne 0 -or $npmResult.code -ne 0) {
        Write-Host "Audit commands reported non-zero exits due low/moderate advisories; high/critical gate still passed."
    }
}

function Assert-MigrationsUpToDate {
    Write-Host "==> Migration status check"
    $statusOutput = php artisan migrate:status --no-ansi
    if ($statusOutput | Select-String -Pattern '\bPending\b') {
        throw "Pending migrations detected. Run `php artisan migrate --force` before release."
    }
}

function Assert-NoOrphanBuildAssets {
    Write-Host "==> Build artifact consistency check"
    & php scripts/prune-build-assets.php --format=json *> $null
    $exitCode = $LASTEXITCODE
    if ($exitCode -eq 2) {
        throw "Orphan build assets detected in public/build. Run `pwsh ./scripts/prune-build-assets.ps1 -Apply` then rebuild."
    }

    if ($exitCode -ne 0) {
        throw "Build artifact consistency check failed."
    }
}

function Assert-ViteManifestPresent {
    $manifestPath = Join-Path (Get-Location) "public/build/manifest.json"
    if (-not (Test-Path $manifestPath)) {
        throw "Vite manifest is missing at public/build/manifest.json. Run npm run build on this release."
    }
}

function Assert-PrebuiltParity {
    Write-Host "==> Prebuilt parity check"
    & php scripts/check-prebuilt-parity.php --format=json --strict *> $null
    $exitCode = $LASTEXITCODE
    if ($exitCode -ne 0) {
        throw "Prebuilt/build manifest parity check failed. Run `composer build:prebuilt` and commit updated public/prebuilt-build."
    }
}

function Assert-RuntimeProfileMatchesQueueProfile {
    Write-Host "==> Runtime profile alignment check"

    $profileResult = Invoke-CommandCapture -CommandLine "php artisan --no-ansi system:queue-profile --format=json"
    if ($profileResult.code -ne 0 -or -not $profileResult.output) {
        throw "Unable to read queue profile payload."
    }

    $profile = Convert-JsonStrict -Raw $profileResult.output -Label 'Queue profile'
    $expectedQueue = ('' + $profile.connection).Trim()
    $expectedCache = ('' + $profile.cache_store).Trim()
    $expectedSession = ('' + $profile.session_driver).Trim()

    $runtimeQueue = Get-ArtisanConfigValue -Key 'queue.default'
    $runtimeCache = Get-ArtisanConfigValue -Key 'cache.default'
    $runtimeSession = Get-ArtisanConfigValue -Key 'session.driver'

    $mismatches = @()
    if ($runtimeQueue -ne $expectedQueue) {
        $mismatches += "queue.default=$runtimeQueue (expected: $expectedQueue)"
    }
    if ($runtimeCache -ne $expectedCache) {
        $mismatches += "cache.default=$runtimeCache (expected: $expectedCache)"
    }
    if ($runtimeSession -ne $expectedSession) {
        $mismatches += "session.driver=$runtimeSession (expected: $expectedSession)"
    }

    if ($mismatches.Count -gt 0) {
        throw "Runtime profile mismatch: $($mismatches -join '; ')"
    }
}

Write-Host "==> Release readiness gate started"
Assert-NoForbiddenTempFiles
Assert-DependencyAuditsHighCriticalClear

if ($RequireCleanGit) {
    $gitStatus = git status --short
    if ($gitStatus) {
        throw "Git worktree is not clean. Commit/stash changes before release gate."
    }
}

Write-Host "==> Route sanity check"
$criticalRoutes = @(
    "login",
    "signup",
    "admin/settings/features/embedded-signup",
    "admin/settings/features/flow-builder",
    "automation/flows",
    "automation/flows/{uuid}/publish",
    "automation/flows/{uuid}/duplicate",
    "health/live",
    "health/ready"
)

$routeList = php artisan route:list
foreach ($route in $criticalRoutes) {
    if (-not ($routeList | Select-String -Pattern $route)) {
        throw "Critical route missing: $route"
    }
}

Write-Host "==> Health check (strict)"
Invoke-CheckedCommand "php artisan system:health-check --strict"
Assert-MigrationsUpToDate
Invoke-CheckedCommand "php artisan system:queue-profile --format=json"
Assert-RuntimeProfileMatchesQueueProfile
Invoke-CheckedCommand "php artisan system:docs-consistency-check --strict --format=json --out=tmp/docs-code-parity.json"
Invoke-CheckedCommand "php artisan system:readiness-score --format=json --out=tmp/readiness-scorecard.json --skip-quality --skip-tests --skip-security-audits"
Invoke-CheckedCommand "php artisan system:risk-report --format=json --out=tmp/risk-register.json --skip-quality --skip-tests --skip-security-audits"

if (-not $SkipTests) {
    Write-Host "==> Test gate"
    Invoke-CheckedCommand "pwsh -NoProfile -ExecutionPolicy Bypass -File ./scripts/safe-test.ps1"
}

if (-not $SkipBuild) {
    Write-Host "==> Build gate"
    Invoke-CheckedCommand "npm run build"
    Assert-ViteManifestPresent
    Assert-NoOrphanBuildAssets
    Assert-PrebuiltParity
}
else {
    Assert-ViteManifestPresent
    Assert-NoOrphanBuildAssets
    Assert-PrebuiltParity
}

Write-Host "==> Release readiness gate PASSED"
