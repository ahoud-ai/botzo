param(
    [string]$BaseUrl = "http://127.0.0.1:8000",
    [string]$FlowUuid = "",
    [string]$SessionCookie = ""
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

function Assert-RouteExists {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Pattern
    )

    $routeLine = php artisan route:list | Select-String -Pattern $Pattern
    if (-not $routeLine) {
        throw "Missing critical route pattern: $Pattern"
    }
}

function Assert-MigrationsUpToDate {
    $statusOutput = php artisan migrate:status --no-ansi
    if ($statusOutput | Select-String -Pattern '\bPending\b') {
        throw "Pending migrations detected. Run php artisan migrate --force before smoke."
    }
}

function Assert-HttpOk {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Url
    )

    Write-Host ">> GET $Url"
    $response = Invoke-WebRequest -Uri $Url -Method GET -MaximumRedirection 0 -SkipHttpErrorCheck
    if ($response.StatusCode -lt 200 -or $response.StatusCode -ge 400) {
        throw "Unexpected status code $($response.StatusCode) for $Url"
    }
}

function Assert-BuilderVersion {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Url,
        [Parameter(Mandatory = $true)]
        [string]$CookieValue
    )

    Write-Host ">> GET $Url (authenticated builder-version check)"
    $headers = @{
        Cookie = $CookieValue
        Accept = "application/json"
    }

    $response = Invoke-WebRequest -Uri $Url -Method GET -Headers $headers -SkipHttpErrorCheck
    if ($response.StatusCode -lt 200 -or $response.StatusCode -ge 400) {
        throw "Unexpected status code $($response.StatusCode) for $Url"
    }

    $payload = $response.Content | ConvertFrom-Json
    if (-not $payload.data) {
        throw "builder-version payload is missing data object."
    }

    if (-not $payload.data.builder_component) {
        throw "builder-version payload missing builder_component."
    }

    if (-not $payload.data.frontend_manifest_asset) {
        throw "builder-version payload missing frontend_manifest_asset."
    }

    if (-not $payload.data.asset_exists) {
        throw "builder-version payload reports asset_exists=false."
    }
}

function Assert-BuilderMetrics {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Url,
        [Parameter(Mandatory = $true)]
        [string]$CookieValue
    )

    Write-Host ">> GET $Url (authenticated builder-metrics check)"
    $headers = @{
        Cookie = $CookieValue
        Accept = "application/json"
    }

    $response = Invoke-WebRequest -Uri $Url -Method GET -Headers $headers -SkipHttpErrorCheck
    if ($response.StatusCode -lt 200 -or $response.StatusCode -ge 400) {
        throw "Unexpected status code $($response.StatusCode) for $Url"
    }

    $payload = $response.Content | ConvertFrom-Json
    if (-not $payload.data) {
        throw "builder-metrics payload is missing data object."
    }

    $requiredFields = @(
        'time_to_first_publish_seconds',
        'validation_fail_rate',
        'template_usage_rate',
        'autosave_failure_rate'
    )

    foreach ($field in $requiredFields) {
        if (-not ($payload.data.PSObject.Properties.Name -contains $field)) {
            throw "builder-metrics payload missing field: $field"
        }
    }
}

Write-Host "==> Running strict health check"
Invoke-CheckedCommand "php artisan system:health-check --strict"
Assert-MigrationsUpToDate
Invoke-CheckedCommand "php artisan system:queue-profile --format=json"
Invoke-CheckedCommand "php artisan tinker --execute=""dump(config('flow_builder.beta_enabled'));"""

Write-Host "==> Validating critical route registration"
Assert-RouteExists "GET\|HEAD\s+login"
Assert-RouteExists "GET\|HEAD\s+signup"
Assert-RouteExists "GET\|HEAD\s+admin/settings/features/embedded-signup"
Assert-RouteExists "POST\s+admin/settings/features/flow-builder"
Assert-RouteExists "GET\|HEAD\s+settings"
Assert-RouteExists "GET\|HEAD\s+campaigns/\{uuid\?\}"
Assert-RouteExists "GET\|HEAD\s+automation/flows/\{uuid\}/builder-beta"
Assert-RouteExists "GET\|HEAD\s+automation/flows/\{uuid\}/builder-version"
Assert-RouteExists "GET\|HEAD\s+automation/flows/\{uuid\}/builder-metrics"
Assert-RouteExists "POST\s+automation/flows/\{uuid\}/ux-events"

Write-Host "==> HTTP smoke checks"
Assert-HttpOk "$BaseUrl/health/live"
Assert-HttpOk "$BaseUrl/health/ready"
Assert-HttpOk "$BaseUrl/login"
Assert-HttpOk "$BaseUrl/signup"

if (-not [string]::IsNullOrWhiteSpace($FlowUuid) -and -not [string]::IsNullOrWhiteSpace($SessionCookie)) {
    Assert-BuilderVersion "$BaseUrl/automation/flows/$FlowUuid/builder-version" $SessionCookie
    Assert-BuilderMetrics "$BaseUrl/automation/flows/$FlowUuid/builder-metrics" $SessionCookie
} else {
    Write-Host ">> Skipping authenticated builder-version/builder-metrics smoke checks (provide -FlowUuid and -SessionCookie)."
}

Write-Host "==> Critical smoke checks passed"
