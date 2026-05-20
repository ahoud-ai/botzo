#!/usr/bin/env bash
set -euo pipefail

SKIP_TESTS=0
SKIP_BUILD=0
REQUIRE_CLEAN_GIT=0

for arg in "$@"; do
  case "$arg" in
    --skip-tests)
      SKIP_TESTS=1
      ;;
    --skip-build)
      SKIP_BUILD=1
      ;;
    --require-clean-git)
      REQUIRE_CLEAN_GIT=1
      ;;
    *)
      echo "Unknown argument: $arg"
      echo "Usage: ./scripts/go-no-go.sh [--skip-tests] [--skip-build] [--require-clean-git]"
      exit 1
      ;;
  esac
done

run_checked() {
  local cmd="$1"
  echo ">> $cmd"
  eval "$cmd"
}

capture_command() {
  local cmd="$1"
  local output
  set +e
  output="$(eval "$cmd" 2>&1)"
  local rc=$?
  set -e
  printf '%s\n__RC__=%s' "$output" "$rc"
}

normalize_json_payload() {
  php -r '
    $raw = stream_get_contents(STDIN);
    if ($raw === false) {
      fwrite(STDERR, "Unable to read command output.".PHP_EOL);
      exit(2);
    }

    $raw = trim($raw);
    if ($raw === "") {
      fwrite(STDERR, "Command output is empty.".PHP_EOL);
      exit(2);
    }

    $starts = [];
    $obj = strpos($raw, "{");
    if ($obj !== false) {
      $starts[] = $obj;
    }
    $arr = strpos($raw, "[");
    if ($arr !== false) {
      $starts[] = $arr;
    }

    if ($starts === []) {
      fwrite(STDERR, "No JSON payload detected in command output.".PHP_EOL);
      exit(3);
    }

    $start = min($starts);
    $json = trim(substr($raw, $start));
    json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
      fwrite(STDERR, "Invalid JSON payload: ".json_last_error_msg().PHP_EOL);
      exit(4);
    }

    echo $json;
  '
}

get_config_value() {
  local key="$1"
  local output
  output="$(php artisan --no-ansi config:show "$key")" || {
    echo "Unable to read runtime config key [$key]."
    exit 1
  }

  local line
  line="$(grep -m1 "$key" <<<"$output" || true)"
  if [[ -z "$line" ]]; then
    echo "Could not parse config:show output for key [$key]."
    exit 1
  fi

  sed -E 's/.*\.{2,}[[:space:]]*//' <<<"$line" | xargs
}

assert_no_forbidden_temp_files() {
  local forbidden=(
    "_write_test.txt"
    "_root_write_test.txt"
    "docroot.php"
    "root-ok.txt"
    "public/__probe.txt"
    "public/docroot.php"
    "public/public-ok.txt"
  )

  local existing=()
  for path in "${forbidden[@]}"; do
    if [[ -e "$path" ]]; then
      existing+=("$path")
    fi
  done

  if [[ "${#existing[@]}" -gt 0 ]]; then
    echo "Forbidden temporary/probe files detected: ${existing[*]}"
    exit 1
  fi
}

assert_dependency_audits_high_critical_clear() {
  echo "==> Dependency audit gate (high/critical)"

  local composer_capture composer_json composer_rc
  composer_capture="$(capture_command "composer audit --locked --no-dev --format=json")"
  composer_rc="$(sed -n 's/^__RC__=//p' <<<"$composer_capture" | tail -n1)"
  composer_json="$(sed '/^__RC__=/d' <<<"$composer_capture")"
  if [[ -z "$composer_json" ]]; then
    echo "Composer audit returned no output (exit=$composer_rc). Check network/cache permissions."
    exit 1
  fi
  composer_json="$(normalize_json_payload <<<"$composer_json")" || {
    echo "Composer audit did not return valid JSON output."
    exit 1
  }

  local composer_hc
  composer_hc="$(php -r '
    $json = stream_get_contents(STDIN);
    $data = json_decode($json, true);
    if (!is_array($data)) {
      fwrite(STDERR, "Composer audit did not return valid JSON.".PHP_EOL);
      exit(2);
    }
    $count = 0;
    foreach (($data["advisories"] ?? []) as $items) {
      foreach ($items as $advisory) {
        $severity = strtolower((string)($advisory["severity"] ?? ""));
        if ($severity === "high" || $severity === "critical") {
          $count++;
        }
      }
    }
    echo $count;
  ' <<<"$composer_json")"

  if [[ "$composer_hc" -gt 0 ]]; then
    echo "Composer audit has $composer_hc high/critical advisories."
    exit 1
  fi

  local npm_capture npm_json npm_rc
  npm_capture="$(capture_command "npm audit --omit=dev --json")"
  npm_rc="$(sed -n 's/^__RC__=//p' <<<"$npm_capture" | tail -n1)"
  npm_json="$(sed '/^__RC__=/d' <<<"$npm_capture")"
  if [[ -z "$npm_json" ]]; then
    echo "NPM audit returned no output (exit=$npm_rc). Check npm/node permissions or network."
    exit 1
  fi
  npm_json="$(normalize_json_payload <<<"$npm_json")" || {
    echo "NPM audit did not return valid JSON output."
    exit 1
  }

  local npm_high npm_critical
  npm_high="$(php -r '
    $json = stream_get_contents(STDIN);
    $data = json_decode($json, true);
    if (!is_array($data)) {
      fwrite(STDERR, "NPM audit did not return valid JSON.".PHP_EOL);
      exit(2);
    }
    echo (int)($data["metadata"]["vulnerabilities"]["high"] ?? 0);
  ' <<<"$npm_json")"
  npm_critical="$(php -r '
    $json = stream_get_contents(STDIN);
    $data = json_decode($json, true);
    if (!is_array($data)) {
      fwrite(STDERR, "NPM audit did not return valid JSON.".PHP_EOL);
      exit(2);
    }
    echo (int)($data["metadata"]["vulnerabilities"]["critical"] ?? 0);
  ' <<<"$npm_json")"

  if [[ "$npm_high" -gt 0 || "$npm_critical" -gt 0 ]]; then
    echo "NPM audit has high/critical vulnerabilities. High=$npm_high Critical=$npm_critical."
    exit 1
  fi

  if [[ "$composer_rc" != "0" || "$npm_rc" != "0" ]]; then
    echo "Audit commands reported non-zero exits due low/moderate advisories; high/critical gate still passed."
  fi
}

assert_migrations_up_to_date() {
  echo "==> Migration status check"
  local status
  status="$(php artisan migrate:status --no-ansi)"
  if grep -q "Pending" <<<"$status"; then
    echo "Pending migrations detected. Run \`php artisan migrate --force\` before release."
    exit 1
  fi
}

assert_no_orphan_build_assets() {
  echo "==> Build artifact consistency check"
  set +e
  php scripts/prune-build-assets.php --format=json >/dev/null 2>&1
  local rc=$?
  set -e

  if [[ "$rc" == "2" ]]; then
    echo "Orphan build assets detected in public/build. Run ./scripts/prune-build-assets.sh --apply then rebuild."
    exit 1
  fi

  if [[ "$rc" != "0" ]]; then
    echo "Build artifact consistency check failed."
    exit 1
  fi
}

assert_vite_manifest_present() {
  local manifest_path="public/build/manifest.json"
  if [[ ! -f "$manifest_path" ]]; then
    echo "Vite manifest is missing at $manifest_path. Run npm run build on this release."
    exit 1
  fi
}

assert_prebuilt_parity() {
  echo "==> Prebuilt parity check"
  if ! php scripts/check-prebuilt-parity.php --format=json --strict >/dev/null; then
    echo "Prebuilt/build manifest parity check failed. Run composer build:prebuilt and commit updated public/prebuilt-build."
    exit 1
  fi
}

assert_runtime_profile_matches_queue_profile() {
  echo "==> Runtime profile alignment check"

  local profile_json
  profile_json="$(php artisan --no-ansi system:queue-profile --format=json)" || {
    echo "Unable to read queue profile payload."
    exit 1
  }

  local expected_queue expected_cache expected_session
  expected_queue="$(php -r '$d=json_decode(stream_get_contents(STDIN), true); if(!is_array($d)){fwrite(STDERR,"Invalid queue profile JSON\n"); exit(2);} echo (string)($d["connection"] ?? "");' <<<"$profile_json")"
  expected_cache="$(php -r '$d=json_decode(stream_get_contents(STDIN), true); if(!is_array($d)){fwrite(STDERR,"Invalid queue profile JSON\n"); exit(2);} echo (string)($d["cache_store"] ?? "");' <<<"$profile_json")"
  expected_session="$(php -r '$d=json_decode(stream_get_contents(STDIN), true); if(!is_array($d)){fwrite(STDERR,"Invalid queue profile JSON\n"); exit(2);} echo (string)($d["session_driver"] ?? "");' <<<"$profile_json")"

  local runtime_queue runtime_cache runtime_session
  runtime_queue="$(get_config_value "queue.default")"
  runtime_cache="$(get_config_value "cache.default")"
  runtime_session="$(get_config_value "session.driver")"

  local mismatch=0
  if [[ "$runtime_queue" != "$expected_queue" ]]; then
    echo "Runtime mismatch: queue.default=$runtime_queue (expected: $expected_queue)"
    mismatch=1
  fi
  if [[ "$runtime_cache" != "$expected_cache" ]]; then
    echo "Runtime mismatch: cache.default=$runtime_cache (expected: $expected_cache)"
    mismatch=1
  fi
  if [[ "$runtime_session" != "$expected_session" ]]; then
    echo "Runtime mismatch: session.driver=$runtime_session (expected: $expected_session)"
    mismatch=1
  fi

  if [[ "$mismatch" == "1" ]]; then
    exit 1
  fi
}

echo "==> Release readiness gate started"
assert_no_forbidden_temp_files
assert_dependency_audits_high_critical_clear

if [[ "$REQUIRE_CLEAN_GIT" == "1" ]]; then
  if [[ -n "$(git status --short)" ]]; then
    echo "Git worktree is not clean. Commit or stash before release gate."
    exit 1
  fi
fi

echo "==> Route sanity check"
ROUTE_LIST="$(php artisan route:list)"
for route in "login" "signup" "admin/settings/features/embedded-signup" "admin/settings/features/flow-builder" "automation/flows" "automation/flows/{uuid}/publish" "automation/flows/{uuid}/duplicate" "health/live" "health/ready"; do
  if ! grep -q "$route" <<<"$ROUTE_LIST"; then
    echo "Critical route missing: $route"
    exit 1
  fi
done

echo "==> Health check (strict)"
run_checked "php artisan system:health-check --strict"
assert_migrations_up_to_date
run_checked "php artisan system:queue-profile --format=json"
assert_runtime_profile_matches_queue_profile
run_checked "php artisan system:docs-consistency-check --strict --format=json --out=tmp/docs-code-parity.json"
run_checked "php artisan system:readiness-score --format=json --out=tmp/readiness-scorecard.json --skip-quality --skip-tests --skip-security-audits"
run_checked "php artisan system:risk-report --format=json --out=tmp/risk-register.json --skip-quality --skip-tests --skip-security-audits"

if [[ "$SKIP_TESTS" != "1" ]]; then
  echo "==> Test gate"
  if command -v pwsh >/dev/null 2>&1; then
    run_checked "pwsh -NoProfile -ExecutionPolicy Bypass -File ./scripts/safe-test.ps1"
  else
    run_checked "bash ./scripts/safe-test.sh"
  fi
fi

if [[ "$SKIP_BUILD" != "1" ]]; then
  echo "==> Build gate"
  run_checked "npm run build"
  assert_vite_manifest_present
  assert_no_orphan_build_assets
  assert_prebuilt_parity
else
  assert_vite_manifest_present
  assert_no_orphan_build_assets
  assert_prebuilt_parity
fi

echo "==> Release readiness gate PASSED"
