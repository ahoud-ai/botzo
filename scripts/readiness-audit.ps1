param(
    [string]$OutputPath = "tmp/readiness-audit.txt"
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

$projectRoot = Resolve-Path "."
$lines = New-Object System.Collections.Generic.List[string]

function Add-Line {
    param([string]$Text)
    $lines.Add($Text) | Out-Null
}

function Add-Section {
    param([string]$Title)
    Add-Line ""
    Add-Line "=== $Title ==="
}

Add-Line "Botzo Readiness Audit"
Add-Line "Generated: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
Add-Line "Project: $projectRoot"

Add-Section "Git"
$gitStatus = git status --short
if ([string]::IsNullOrWhiteSpace(($gitStatus -join ""))) {
    Add-Line "Worktree: clean"
} else {
    Add-Line "Worktree: dirty"
    $gitStatus | ForEach-Object { Add-Line "  $_" }
}

Add-Section "Core Health"
try {
    $health = php artisan system:health-check 2>&1
    $health | ForEach-Object { Add-Line $_ }
} catch {
    Add-Line "system:health-check failed: $($_.Exception.Message)"
}

Add-Section "Testing Safety"
try {
    $safety = php artisan config:clear 2>&1
    $safety | ForEach-Object { Add-Line $_ }
    $safety2 = php artisan system:test-safety-check --env=testing 2>&1
    $safety2 | ForEach-Object { Add-Line $_ }
} catch {
    Add-Line "system:test-safety-check failed: $($_.Exception.Message)"
}

Add-Section "Routes"
$patterns = @("admin/settings/features/embedded-signup", "admin/settings/features/flow-builder", "developer-tools/access-tokens")
foreach ($pattern in $patterns) {
    $match = php artisan route:list | Select-String -Pattern $pattern
    if ($match) {
        Add-Line "${pattern}: OK"
    } else {
        Add-Line "${pattern}: MISSING"
    }
}

Add-Section "Composer Wildcard Dependencies"
try {
    $composer = Get-Content .\composer.json -Raw | ConvertFrom-Json
    foreach ($property in $composer.require.PSObject.Properties) {
        if ($property.Value -eq "*") {
            Add-Line "$($property.Name) => *"
        }
    }
} catch {
    Add-Line "Unable to parse composer.json: $($_.Exception.Message)"
}

Add-Section "Build Artifacts"
if (Test-Path .\public\build\manifest.json) {
    Add-Line "public/build/manifest.json: present"
} else {
    Add-Line "public/build/manifest.json: missing"
}

$outputDir = Split-Path -Parent $OutputPath
if ($outputDir -and -not (Test-Path $outputDir)) {
    New-Item -Path $outputDir -ItemType Directory -Force | Out-Null
}

$lines | Set-Content -Path $OutputPath -Encoding UTF8
Write-Host "Readiness audit written to $OutputPath"
