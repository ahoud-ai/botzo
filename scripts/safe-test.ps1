param(
    [switch]$SkipMigrate
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

Write-Host "==> Clearing cached bootstrap artifacts"
Invoke-CheckedCommand "php artisan optimize:clear --env=testing"

Write-Host "==> Running test safety guard"
Invoke-CheckedCommand "php artisan system:test-safety-check --env=testing"

if (-not $SkipMigrate) {
    Write-Host "==> Preparing isolated testing database"
    Invoke-CheckedCommand "php artisan system:prepare-testing-database --env=testing --force"
}

Write-Host "==> Running test suite"
Invoke-CheckedCommand "php artisan test --env=testing"

Write-Host "==> Safe test run completed"
