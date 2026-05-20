param(
    [Parameter(Mandatory = $true)]
    [ValidateSet('core-safety', 'flowbuilder-prod', 'i18n', 'ops-docs')]
    [string]$Batch,
    [switch]$PreviewOnly
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

$batchSpecs = @{
    'core-safety' = @{
        include = @(
            '.editorconfig',
            '.env.example',
            '.env.production.example',
            '.env.testing.example',
            '.gitignore',
            'app',
            'bootstrap',
            'config',
            'database',
            'resources',
            'routes',
            'tests',
            'composer.json',
            'composer.lock',
            'package.json',
            'package-lock.json',
            'phpunit.xml'
        )
        exclude = @(
            'app/Http/Controllers/User/AutomationFlowController.php',
            'app/Http/Requests/AutomationFlows',
            'app/Jobs/ResumeAutomationFlowRunJob.php',
            'app/Models/AutomationFlow*',
            'app/Services/AutomationFlows',
            'config/automation_flows.php',
            'database/migrations/*automation_flow*',
            'database/migrations/*flow_builder*',
            'resources/js/Components/AutomationFlows',
            'resources/js/Pages/User/Automation/Flows',
            'resources/js/Pages/User/Automation/Layout.vue',
            'lang/ar.json',
            'lang/en.json',
            'scripts/i18n',
            'docs',
            'scripts/*.ps1',
            'scripts/*.sh',
            'scripts/*.php'
        )
    }
    'flowbuilder-prod' = @{
        include = @(
            'app/Http/Controllers/User/AutomationFlowController.php',
            'app/Http/Requests/AutomationFlows',
            'app/Jobs/ResumeAutomationFlowRunJob.php',
            'app/Models/AutomationFlow*',
            'app/Services/AutomationFlows',
            'app/Http/Middleware/HandleInertiaRequests.php',
            'app/Services/AutoReplyService.php',
            'app/Services/System/RuntimeReadinessService.php',
            'app/Support/OrganizationPermissions.php',
            'config/automation_flows.php',
            'database/migrations/*automation_flow*',
            'database/migrations/*flow_builder*',
            'routes/web/automation.php',
            'resources/js/Components/AutomationFlows',
            'resources/js/Pages/User/Automation/Flows',
            'resources/js/Pages/User/Automation/Layout.vue',
            'tests/Feature/AutomationFlow*',
            'tests/Feature/AdminFlowBuilderAddonSetupRouteTest.php'
        )
        exclude = @()
    }
    'i18n' = @{
        include = @(
            'lang/ar.json',
            'lang/en.json',
            'scripts/i18n'
        )
        exclude = @()
    }
    'ops-docs' = @{
        include = @(
            '.github/workflows',
            'docs',
            'scripts/*.ps1',
            'scripts/*.sh',
            'scripts/*.php'
        )
        exclude = @(
            'scripts/i18n'
        )
    }
}

function Get-ChangedFiles {
    $rows = git -c core.excludesFile= status --porcelain --untracked-files=all
    $files = @()
    foreach ($row in $rows) {
        if ($row.Length -lt 4) {
            continue
        }

        $path = $row.Substring(3).Trim().Trim('"')
        if ($path -match ' -> ') {
            $path = ($path -split ' -> ')[-1].Trim()
        }

        if ($path -ne '') {
            $files += $path
        }
    }

    return $files | Sort-Object -Unique
}

function Test-PathSpecMatch {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Path,
        [Parameter(Mandatory = $true)]
        [string]$Spec
    )

    $normalizedPath = $Path.Replace('\', '/')
    $normalizedSpec = $Spec.Replace('\', '/')

    if ($normalizedSpec.Contains('*') -or $normalizedSpec.Contains('?')) {
        return $normalizedPath -like $normalizedSpec
    }

    return $normalizedPath -eq $normalizedSpec -or $normalizedPath.StartsWith($normalizedSpec.TrimEnd('/') + '/')
}

function Get-CandidateFiles {
    param(
        [string[]]$IncludeSpecs,
        [string[]]$ExcludeSpecs
    )

    $changedFiles = Get-ChangedFiles
    $candidates = @()

    foreach ($file in $changedFiles) {
        $included = $false
        foreach ($spec in $IncludeSpecs) {
            if (Test-PathSpecMatch -Path $file -Spec $spec) {
                $included = $true
                break
            }
        }

        if (-not $included) {
            continue
        }

        $excluded = $false
        foreach ($spec in $ExcludeSpecs) {
            if (Test-PathSpecMatch -Path $file -Spec $spec) {
                $excluded = $true
                break
            }
        }

        if (-not $excluded) {
            $candidates += $file
        }
    }

    return $candidates | Sort-Object -Unique
}

$specs = $batchSpecs[$Batch]
$includeSpecs = @($specs.include)
$excludeSpecs = @($specs.exclude)
$candidates = Get-CandidateFiles -IncludeSpecs $includeSpecs -ExcludeSpecs $excludeSpecs

Write-Host "==> Batch: $Batch"
Write-Host "==> Include pathspecs:"
$includeSpecs | ForEach-Object { Write-Host " - $_" }
Write-Host "==> Exclude pathspecs:"
if ($excludeSpecs.Count -eq 0) {
    Write-Host " - (none)"
} else {
    $excludeSpecs | ForEach-Object { Write-Host " - $_" }
}
Write-Host "==> Candidate files: $($candidates.Count)"

if ($PreviewOnly) {
    if ($candidates.Count -eq 0) {
        Write-Host "No matching pending files for this batch."
        exit 0
    }

    Write-Host "==> Preview (first 200 candidates):"
    $candidates | Select-Object -First 200 | ForEach-Object { Write-Host " - $_" }
    if ($candidates.Count -gt 200) {
        Write-Host " ... and $($candidates.Count - 200) more."
    }
    exit 0
}

foreach ($spec in $includeSpecs) {
    git add -- $spec
}

foreach ($spec in $excludeSpecs) {
    git restore --staged -- $spec 2>$null
    if ($LASTEXITCODE -ne 0) {
        Write-Host "==> Exclude skipped (no staged match): $spec"
    }
}

Write-Host "==> Staged files for batch '$Batch'."
git diff --cached --name-status
