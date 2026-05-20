param(
    [string]$ProjectRoot = "."
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

function Write-Section {
    param([string]$Title)
    Write-Host ""
    Write-Host "=== $Title ==="
}

function Read-EnvValue {
    param(
        [string]$EnvPath,
        [string]$Key
    )
    if (-not (Test-Path $EnvPath)) { return $null }
    $line = Get-Content $EnvPath | Where-Object { $_ -match "^$Key=" } | Select-Object -First 1
    if (-not $line) { return $null }
    return $line.Substring($Key.Length + 1)
}

$root = Resolve-Path $ProjectRoot
Set-Location $root

$failures = @()

Write-Section "Project"
Write-Host "Path: $root"

Write-Section "PHP"
try {
    $phpVersion = (& php -r "echo PHP_VERSION;")
    Write-Host "PHP version: $phpVersion"
} catch {
    $failures += "php_not_found"
    Write-Host "PHP not found in PATH."
}

$requiredExtensions = @(
    "pdo_mysql",
    "mbstring",
    "openssl",
    "fileinfo",
    "curl",
    "zip",
    "tokenizer",
    "json",
    "imagick"
)

try {
    $loaded = (& php -m) | ForEach-Object { $_.Trim() } | Where-Object { $_ -ne "" }
    foreach ($ext in $requiredExtensions) {
        if ($loaded -contains $ext) {
            Write-Host "ext:$ext = OK"
        } else {
            Write-Host "ext:$ext = MISSING"
            $failures += "missing_ext_$ext"
        }
    }
} catch {
    $failures += "php_extensions_check_failed"
}

Write-Section "Core Files"
$checks = @(
    "artisan",
    "bootstrap/app.php",
    "public/index.php",
    "routes/web.php",
    "routes/api.php",
    "vendor/autoload.php",
    "public/build/manifest.json"
)

foreach ($item in $checks) {
    if (Test-Path $item) {
        Write-Host "$item = OK"
    } else {
        Write-Host "$item = MISSING"
        $failures += "missing_$item"
    }
}

Write-Section "Source Completeness"
$sourceDirs = @(
    "app/Http/Controllers/Admin",
    "app/Http/Controllers/User",
    "database",
    "resources/js",
    "modules"
)

foreach ($dir in $sourceDirs) {
    if (Test-Path $dir) {
        Write-Host "$dir = PRESENT"
    } else {
        Write-Host "$dir = ABSENT"
        $failures += "absent_$dir"
    }
}

Write-Section "Autoload Classmap Sanity"
$classmapFile = "vendor/composer/autoload_classmap.php"
if (Test-Path $classmapFile) {
    $lines = Get-Content $classmapFile
    $paths = @()

    foreach ($line in $lines) {
        if ($line -like "*`$baseDir . '/app/*") {
            $start = $line.IndexOf("'/app/")
            if ($start -ge 0) {
                $rest = $line.Substring($start + 1)
                $end = $rest.IndexOf("'")
                if ($end -gt 0) {
                    $paths += $rest.Substring(0, $end).TrimStart("/")
                }
            }
        }
    }

    $paths = $paths | Sort-Object -Unique
    $missing = @($paths | Where-Object { -not (Test-Path $_) })

    Write-Host "expected app classmap paths: $($paths.Count)"
    Write-Host "missing app classmap paths: $($missing.Count)"

    if ($missing.Count -gt 0) {
        Write-Host "sample missing paths:"
        $missing | Select-Object -First 15 | ForEach-Object { Write-Host " - $_" }
        $failures += "missing_classmap_paths"
    }
} else {
    Write-Host "autoload classmap not found."
    $failures += "missing_autoload_classmap"
}

Write-Section "Install State"
$installedPath = "storage/installed"
if (Test-Path $installedPath) {
    Write-Host "storage/installed = PRESENT"
} else {
    Write-Host "storage/installed = ABSENT"
}

$envPath = ".env"
if (Test-Path $envPath) {
    $appUrl = Read-EnvValue -EnvPath $envPath -Key "APP_URL"
    $dbHost = Read-EnvValue -EnvPath $envPath -Key "DB_HOST"
    $dbName = Read-EnvValue -EnvPath $envPath -Key "DB_DATABASE"
    Write-Host ".env APP_URL: $appUrl"
    Write-Host ".env DB_HOST: $dbHost"
    Write-Host ".env DB_DATABASE: $dbName"
} else {
    Write-Host ".env not found."
    $failures += "missing_env"
}

Write-Section "Summary"
if ($failures.Count -eq 0) {
    Write-Host "Preflight passed."
    exit 0
}

Write-Host "Preflight found issues: $($failures.Count)"
$failures | ForEach-Object { Write-Host " - $_" }
exit 1
