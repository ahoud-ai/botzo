#!/usr/bin/env bash
# Live check of the developer API on a real server, with a real WhatsApp number.
#
#   bash scripts/developer-api-smoke.sh +9665XXXXXXXX [+9665OTHERNUMBER]
#
# Before running: create a "Send messages only" key in Developer tools, and send any WhatsApp
# message from the first number to the organization's number (Meta only allows free text to
# numbers that wrote in the last 24 hours). The optional second number must NOT have written
# recently: it checks that Meta's refusal comes back as a clear error.
# The key is typed hidden and is never printed or saved.

set -u
BASE="${BOTZO_URL:-https://botzo.net}"
PHONE="${1:?usage: bash scripts/developer-api-smoke.sh +9665XXXXXXXX [+9665OTHERNUMBER]}"
OTHER_PHONE="${2:-}"
read -r -s -p "API key (Send messages only): " KEY
echo

PASS=0
FAIL=0
ok() { PASS=$((PASS + 1)); echo "  ✅ $1"; }
bad() {
    FAIL=$((FAIL + 1))
    echo "  ❌ $1" | cut -c1-400
    case "${BODY:-}" in
        *display_name_not_approved*|*meta_payment_issue*|*whatsapp_account_restricted*|*whatsapp_account_locked*|*whatsapp_token_expired*)
            echo "     ⚠️  Meta refused this for the WhatsApp account itself (see the code above), not Botzo." ;;
    esac
}
field() { sed -n "s/.*\"$1\":\"\{0,1\}\([^\",}]*\).*/\1/p" | head -1; }

call() { # method path [json] [extra header]
    local body_file headers_file
    body_file=$(mktemp)
    headers_file=$(mktemp)
    STATUS=$(curl -s -o "$body_file" -D "$headers_file" -w '%{http_code}' -X "$1" "$BASE$2" \
        -H "Authorization: Bearer $KEY" -H 'Accept: application/json' -H 'Content-Type: application/json' \
        ${4:+-H "$4"} ${3:+--data "$3"})
    BODY=$(cat "$body_file")
    TIMING=$(grep -i '^server-timing:' "$headers_file" | cut -d' ' -f2- | tr -d '\r')
    REPLAYED=$(grep -ci '^idempotent-replayed: true' "$headers_file")
    rm -f "$body_file" "$headers_file"
}

echo "1) The key works"
call GET /api/verify
[ "$STATUS" = 200 ] && ok "verify → 200 ($TIMING)" || bad "verify → $STATUS $BODY"

echo "2) A send-only key cannot read contacts"
call GET /api/contacts
[ "$STATUS" = 403 ] && echo "$BODY" | grep -q api_key_scope_denied && ok "contacts → 403 api_key_scope_denied" || bad "contacts → $STATUS $BODY"

echo "3) Send a text message"
IDEM="smoke-$(date +%s)-$RANDOM"
MESSAGE="{\"phone\":\"$PHONE\",\"message\":\"اختبار بوتزو API ✅ $(date +%H:%M:%S)\"}"
call POST /api/send "$MESSAGE" "Idempotency-Key: $IDEM"
MESSAGE_ID=$(echo "$BODY" | field message_id)
[ "$STATUS" = 200 ] && [ -n "$MESSAGE_ID" ] && ok "send → 200, message_id received ($TIMING)" || bad "send → $STATUS $BODY"

echo "4) Retrying with the same Idempotency-Key does not send twice"
call POST /api/send "$MESSAGE" "Idempotency-Key: $IDEM"
SECOND_ID=$(echo "$BODY" | field message_id)
[ -n "$MESSAGE_ID" ] && [ "$REPLAYED" = 1 ] && [ "$SECOND_ID" = "$MESSAGE_ID" ] && ok "same result returned, only one message on the phone" || bad "retry → $STATUS replayed=$REPLAYED $BODY"

echo "5) Message status"
if [ -n "$MESSAGE_ID" ]; then
    sleep 6
    call GET "/api/messages/$MESSAGE_ID"
    STATE=$(echo "$BODY" | field status)
    [ "$STATUS" = 200 ] && ok "status → $STATE" || bad "status → $STATUS $BODY"
else
    bad "skipped: no message_id from step 3"
fi

echo "6) Media links must be public https"
call POST /api/send/media "{\"phone\":\"$PHONE\",\"media_type\":\"image\",\"media_url\":\"/etc/passwd\"}"
[ "$STATUS" = 400 ] && ok "local file path refused" || bad "local path → $STATUS $BODY"
call POST /api/send/media "{\"phone\":\"$PHONE\",\"media_type\":\"image\",\"media_url\":\"https://127.0.0.1/a.jpg\"}"
[ "$STATUS" = 400 ] && ok "internal address refused" || bad "internal address → $STATUS $BODY"

echo "7) Send an image with a caption"
call POST /api/send/media "{\"phone\":\"$PHONE\",\"media_type\":\"image\",\"media_url\":\"$BASE/images/ai.png\",\"caption\":\"صورة اختبار مع تعليق\"}"
[ "$STATUS" = 200 ] && ok "image sent ($TIMING) — check the caption on the phone" || bad "image → $STATUS $BODY"

if [ -n "$OTHER_PHONE" ]; then
    echo "8) A number outside the 24-hour window gets a clear error"
    call POST /api/send "{\"phone\":\"$OTHER_PHONE\",\"message\":\"اختبار\"}"
    CODE=$(echo "$BODY" | field code)
    [ "$STATUS" != 200 ] && [ -n "$CODE" ] && ok "refused with $STATUS $CODE" || bad "outside window → $STATUS $BODY"
fi

echo "9) Speed: 30 requests, 10 at a time"
TIMES=$(seq 30 | xargs -P 10 -I{} curl -s -o /dev/null -w '%{time_total}\n' "$BASE/api/verify" -H "Authorization: Bearer $KEY" -H 'Accept: application/json' | sort -n)
echo "$TIMES" | awk '{ s += $1; a[NR] = $1 } END { printf "  ⏱  average %.0f ms, slowest %.0f ms (network included)\n", s / NR * 1000, a[NR] * 1000 }'

echo
echo "Passed: $PASS   Failed: $FAIL"
unset KEY
