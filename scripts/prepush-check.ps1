param(
    [switch]$SkipBuild
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

function Assert-NoTrackedBuildArtifacts {
    $trackedBuildFiles = @(
        git ls-files -- 'public/build' |
            Where-Object { $_ -and $_.Trim() -ne '' }
    )

    if ($trackedBuildFiles.Count -gt 0) {
        throw "Tracked build artifacts detected under public/build. Remove them from the git index and keep build outputs generated-only."
    }
}

function Assert-NoOrphanBuildAssets {
    Write-Host "==> Validating build artifact consistency"
    & php scripts/prune-build-assets.php --format=json *> $null
    $exitCode = $LASTEXITCODE
    if ($exitCode -eq 2) {
        throw "Orphan build assets detected in public/build. Run `pwsh ./scripts/prune-build-assets.ps1 -Apply` then rebuild."
    }

    if ($exitCode -ne 0) {
        throw "Build artifact consistency check failed."
    }
}

Write-Host "==> Enforcing repository hygiene"
Assert-NoForbiddenTempFiles
Assert-NoTrackedBuildArtifacts
Assert-NoOrphanBuildAssets

Write-Host "==> Running PHP tests"
Invoke-CheckedCommand "pwsh -NoProfile -ExecutionPolicy Bypass -File ./scripts/safe-test.ps1"

Write-Host "==> Running architecture guards"
Invoke-CheckedCommand "composer check:architecture"

if (-not $SkipBuild) {
    Write-Host "==> Running frontend build"
    Invoke-CheckedCommand "npm run build"
}

Write-Host "==> Pre-push checks passed"
