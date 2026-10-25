#!/usr/bin/env bash
# Response time, size, compression and caching of the public pages and static files.
# Read-only: GET requests only, no login, nothing is changed. See PERFORMANCE.md.
#
#   bash scripts/perf-check.sh                  # https://botzo.net
#   BOTZO_URL=https://staging.example bash scripts/perf-check.sh
#   WIDGET_KEY=wgt_... bash scripts/perf-check.sh   # also the widget config endpoint
#
# Each URL is fetched 3 times; the median server time (TTFB minus connect) is shown.

set -u
BASE="${BOTZO_URL:-https://botzo.net}"
RUNS=3
# Git Bash on Windows reports size 0 when curl writes to /dev/null.
case "$(uname -s)" in MINGW*|MSYS*) DEVNULL=NUL ;; *) DEVNULL=/dev/null ;; esac

printf '%-46s %6s %7s %8s %6s  %s\n' "URL" "TTFB" "server" "bytes" "enc" "cache-control"

median() { sort -n | awk '{a[NR]=$1} END {print a[int((NR+1)/2)]}'; }

check() {
    local url="$1" ttfbs="" servers="" size="" enc="" cache=""
    for _ in $(seq "$RUNS"); do
        local out
        out=$(curl -s -o "$DEVNULL" -D - -H 'Accept-Encoding: br, gzip' \
            -w 'TIMING %{time_starttransfer} %{time_appconnect} %{size_download}\n' "$url")
        local line
        line=$(printf '%s\n' "$out" | grep '^TIMING')
        local ttfb appc
        ttfb=$(echo "$line" | awk '{print $2}')
        appc=$(echo "$line" | awk '{print $3}')
        size=$(echo "$line" | awk '{print $4}')
        ttfbs="$ttfbs $(awk -v t="$ttfb" 'BEGIN {printf "%d", t * 1000}')"
        servers="$servers $(awk -v t="$ttfb" -v a="$appc" 'BEGIN {printf "%d", (t - a) * 1000}')"
        enc=$(printf '%s\n' "$out" | tr -d '\r' | grep -i '^content-encoding:' | head -1 | cut -d' ' -f2)
        cache=$(printf '%s\n' "$out" | tr -d '\r' | grep -i '^cache-control:' | head -1 | cut -d' ' -f2-)
    done
    printf '%-46s %5sms %6sms %8s %6s  %s\n' "${url#"$BASE"}" \
        "$(echo $ttfbs | tr ' ' '\n' | median)" "$(echo $servers | tr ' ' '\n' | median)" \
        "$size" "${enc:--}" "${cache:--}"
}

# Static files referenced by the home page: first CSS and the main script.
HOME_HTML=$(curl -s "$BASE/")
asset() { printf '%s' "$HOME_HTML" | grep -o "/build/assets/$1-[0-9a-f]*\.$2" | head -1; }

for path in / /pricing /contact /login /developers /widget.js /robots.txt; do
    check "$BASE$path"
done

for path in "$(asset app css)" "$(asset app js)"; do
    [ -n "$path" ] && check "$BASE$path"
done

# The translations file the page asks for (versioned name in the page props).
I18N=$(printf '%s' "$HOME_HTML" | grep -o '\\/i18n\\/[a-z_]*\.[0-9a-f]*\.json' | head -1 | tr -d '\\')
[ -n "$I18N" ] && check "$BASE$I18N"

if [ -n "${WIDGET_KEY:-}" ]; then
    check "$BASE/api/widget/$WIDGET_KEY/config"
fi

echo
echo "TTFB = time to first byte from here; server = TTFB minus connection setup (PHP time plus one round trip)."
echo "Expected: build assets and /i18n files 'immutable'; text responses br or gzip."
