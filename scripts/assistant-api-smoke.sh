#!/usr/bin/env bash
# Live check of the Mochi API on a real server.
#
#   bash scripts/assistant-api-smoke.sh
#
# Before running: in Developer tools create a "Mochi only (server)" key, and optionally a
# "Mochi public key (inside apps)" key. The keys are typed hidden and never printed or saved.
# The organization's AI assistant must be on. Each run uses a new test user, shown in the inbox.

set -u
BASE="${BOTZO_URL:-https://botzo.net}"
read -r -s -p "Mochi secret key (Mochi only): " KEY
echo
read -r -s -p "Mochi public key pk_... (Enter to skip): " PUBLIC_KEY
echo

PASS=0
FAIL=0
ok() { PASS=$((PASS + 1)); echo "  ✅ $1"; }
bad() { FAIL=$((FAIL + 1)); echo "  ❌ $1" | cut -c1-400; }
field() { sed -n "s/.*\"$1\":\"\{0,1\}\([^\",}]*\).*/\1/p" | head -1; }

call() { # key method path [json] [extra header]
    local body_file headers_file
    body_file=$(mktemp)
    headers_file=$(mktemp)
    STATUS=$(curl -s -o "$body_file" -D "$headers_file" -w '%{http_code}' -X "$2" "$BASE$3" \
        -H "Authorization: Bearer $1" -H 'Accept: application/json' -H 'Content-Type: application/json' \
        ${5:+-H "$5"} ${4:+--data "$4"})
    BODY=$(cat "$body_file")
    TIMING=$(grep -i '^server-timing:' "$headers_file" | cut -d' ' -f2- | tr -d '\r')
    rm -f "$body_file" "$headers_file"
}

USER_ID="smoke-$(date +%s)"

echo "1) Open a conversation for a test user"
call "$KEY" POST /api/v1/assistant/conversations "{\"user\":{\"id\":\"$USER_ID\",\"name\":\"اختبار API موتشي\"}}"
CONVERSATION=$(echo "$BODY" | field conversation_id)
[ "$STATUS" = 201 ] && [ -n "$CONVERSATION" ] && ok "conversation opened ($TIMING)" || bad "open → $STATUS $BODY"

echo "2) Ask Mochi and wait for the answer"
START=$(date +%s%N)
call "$KEY" POST "/api/v1/assistant/conversations/$CONVERSATION/messages" '{"text":"مرحبا، وش هي بوتزو باختصار؟"}'
MS=$(( ($(date +%s%N) - START) / 1000000 ))
REPLY=$(echo "$BODY" | sed -n 's/.*"from":"assistant"[^}]*"text":"\([^"]*\)".*/\1/p' | head -1)
[ "$STATUS" = 200 ] && [ -n "$REPLY" ] && ok "Mochi answered in $MS ms ($TIMING)" || bad "send → $STATUS $BODY"
[ -n "$REPLY" ] && echo "     💬 $(printf '%b' "$REPLY" | cut -c1-200)"

echo "3) Send without waiting, then fetch the answer"
sleep 4
call "$KEY" POST "/api/v1/assistant/conversations/$CONVERSATION/messages" '{"text":"شكراً، كم الأسعار؟","wait":false}'
MESSAGE_ID=$(echo "$BODY" | field message_id)
[ "$STATUS" = 202 ] && ok "accepted at once ($TIMING)" || bad "send without wait → $STATUS $BODY"
FOUND=""
for _ in 1 2 3 4 5 6 7 8 9 10; do
    sleep 2
    call "$KEY" GET "/api/v1/assistant/conversations/$CONVERSATION/messages?after=$MESSAGE_ID"
    echo "$BODY" | grep -q '"from":"assistant"' && FOUND=1 && break
done
[ -n "$FOUND" ] && ok "the answer was there when fetched" || bad "no answer after 20 s: $BODY"

echo "4) The same user continues the same conversation"
call "$KEY" POST /api/v1/assistant/conversations "{\"user\":{\"id\":\"$USER_ID\"}}"
[ "$STATUS" = 200 ] && [ "$(echo "$BODY" | field conversation_id)" = "$CONVERSATION" ] && ok "same conversation, with its history" || bad "reopen → $STATUS $BODY"

echo "5) A Mochi key cannot touch WhatsApp or contacts"
call "$KEY" GET /api/contacts
[ "$STATUS" = 403 ] && ok "contacts → 403" || bad "contacts → $STATUS $BODY"

if [ -n "$PUBLIC_KEY" ]; then
    echo "6) Public key: a device conversation, private to that device"
    call "$PUBLIC_KEY" POST /api/v1/assistant/conversations '{}'
    DEVICE_CONVERSATION=$(echo "$BODY" | field conversation_id)
    DEVICE_TOKEN=$(echo "$BODY" | field conversation_token)
    [ "$STATUS" = 201 ] && [ -n "$DEVICE_TOKEN" ] && ok "device conversation opened" || bad "public open → $STATUS $BODY"
    call "$PUBLIC_KEY" GET "/api/v1/assistant/conversations/$DEVICE_CONVERSATION/messages"
    [ "$STATUS" = 404 ] && ok "without the device token → 404" || bad "no token → $STATUS"
    call "$PUBLIC_KEY" POST "/api/v1/assistant/conversations/$DEVICE_CONVERSATION/messages" '{"text":"hello"}' "X-Conversation-Token: $DEVICE_TOKEN"
    [ "$STATUS" = 200 ] && ok "with the device token Mochi answered ($TIMING)" || bad "public send → $STATUS $BODY"
    call "$PUBLIC_KEY" GET /api/contacts
    [ "$STATUS" = 403 ] && ok "public key cannot read contacts" || bad "public contacts → $STATUS"
fi

echo
echo "Passed: $PASS   Failed: $FAIL"
echo "Check the inbox: a conversation named \"اختبار API موتشي\" with the app's name on its label."
unset KEY PUBLIC_KEY
