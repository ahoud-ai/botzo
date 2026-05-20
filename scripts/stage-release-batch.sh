#!/usr/bin/env bash
set -euo pipefail

if [[ $# -lt 1 ]]; then
  echo "Usage: ./scripts/stage-release-batch.sh <batch> [--preview]"
  echo "Batches: core-safety | flowbuilder-prod | i18n | ops-docs"
  exit 1
fi

BATCH="$1"
PREVIEW=0
if [[ "${2:-}" == "--preview" ]]; then
  PREVIEW=1
fi

declare -a INCLUDE_SPECS
declare -a EXCLUDE_SPECS
case "$BATCH" in
  core-safety)
    INCLUDE_SPECS=(
      ".editorconfig"
      ".env.example"
      ".env.production.example"
      ".env.testing.example"
      ".gitignore"
      "app"
      "bootstrap"
      "config"
      "database"
      "resources"
      "routes"
      "tests"
      "composer.json"
      "composer.lock"
      "package.json"
      "package-lock.json"
      "phpunit.xml"
    )
    EXCLUDE_SPECS=(
      "app/Http/Controllers/User/AutomationFlowController.php"
      "app/Http/Requests/AutomationFlows"
      "app/Jobs/ResumeAutomationFlowRunJob.php"
      "app/Models/AutomationFlow*"
      "app/Services/AutomationFlows"
      "config/automation_flows.php"
      "database/migrations/*automation_flow*"
      "database/migrations/*flow_builder*"
      "resources/js/Components/AutomationFlows"
      "resources/js/Pages/User/Automation/Flows"
      "resources/js/Pages/User/Automation/Layout.vue"
      "lang/ar.json"
      "lang/en.json"
      "scripts/i18n"
      "docs"
      "scripts/*.ps1"
      "scripts/*.sh"
      "scripts/*.php"
    )
    ;;
  flowbuilder-prod)
    INCLUDE_SPECS=(
      "app/Http/Controllers/User/AutomationFlowController.php"
      "app/Http/Requests/AutomationFlows"
      "app/Jobs/ResumeAutomationFlowRunJob.php"
      "app/Models/AutomationFlow*"
      "app/Services/AutomationFlows"
      "app/Http/Middleware/HandleInertiaRequests.php"
      "app/Services/AutoReplyService.php"
      "app/Services/System/RuntimeReadinessService.php"
      "app/Support/OrganizationPermissions.php"
      "config/automation_flows.php"
      "database/migrations/*automation_flow*"
      "database/migrations/*flow_builder*"
      "routes/web/automation.php"
      "resources/js/Components/AutomationFlows"
      "resources/js/Pages/User/Automation/Flows"
      "resources/js/Pages/User/Automation/Layout.vue"
      "tests/Feature/AutomationFlow*"
      "tests/Feature/AdminFlowBuilderAddonSetupRouteTest.php"
    )
    EXCLUDE_SPECS=()
    ;;
  i18n)
    INCLUDE_SPECS=(
      "lang/ar.json"
      "lang/en.json"
      "scripts/i18n"
    )
    EXCLUDE_SPECS=()
    ;;
  ops-docs)
    INCLUDE_SPECS=(
      ".github/workflows"
      "docs"
      "scripts/*.ps1"
      "scripts/*.sh"
      "scripts/*.php"
    )
    EXCLUDE_SPECS=(
      "scripts/i18n"
    )
    ;;
  *)
    echo "Unknown batch: $BATCH"
    exit 1
    ;;
esac

echo "==> Batch: $BATCH"
echo "==> Include pathspecs:"
for spec in "${INCLUDE_SPECS[@]}"; do
  echo " - $spec"
done
echo "==> Exclude pathspecs:"
if [[ "${#EXCLUDE_SPECS[@]}" -eq 0 ]]; then
  echo " - (none)"
else
  for spec in "${EXCLUDE_SPECS[@]}"; do
    echo " - $spec"
  done
fi

if [[ "$PREVIEW" == "1" ]]; then
  echo "==> Preview (matching changed files)"
  for spec in "${INCLUDE_SPECS[@]}"; do
    git status --short --untracked-files=all -- "$spec" || true
  done
  if [[ "${#EXCLUDE_SPECS[@]}" -gt 0 ]]; then
    echo "==> Excluded path matches:"
    for spec in "${EXCLUDE_SPECS[@]}"; do
      git status --short --untracked-files=all -- "$spec" || true
    done
  fi
  exit 0
fi

for spec in "${INCLUDE_SPECS[@]}"; do
  # shellcheck disable=SC2086
  git add -- "$spec"
done

for spec in "${EXCLUDE_SPECS[@]}"; do
  # shellcheck disable=SC2086
  git restore --staged -- "$spec" || true
done

echo "==> Staged files for batch '$BATCH'"
git diff --cached --name-status
