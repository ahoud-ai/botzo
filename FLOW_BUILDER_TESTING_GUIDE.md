# Flow Builder — Testing Guide

> Complete manual testing checklist, edge cases, failure scenarios, and automated test suggestions.

---

## Table of Contents

1. [Pre-Test Setup Checklist](#1-pre-test-setup-checklist)
2. [Manual Testing Checklist](#2-manual-testing-checklist)
   - [2.1 Access & Feature Flags](#21-access--feature-flags)
   - [2.2 Flow List (Index Page)](#22-flow-list-index-page)
   - [2.3 Flow Creation](#23-flow-creation)
   - [2.4 Canvas & Node System](#24-canvas--node-system)
   - [2.5 Each Node Type](#25-each-node-type)
   - [2.6 Validation System](#26-validation-system)
   - [2.7 Save & Autosave](#27-save--autosave)
   - [2.8 Preview System](#28-preview-system)
   - [2.9 Publish & Lifecycle](#29-publish--lifecycle)
   - [2.10 Asset Upload](#210-asset-upload)
   - [2.11 SMTP (send_email) Secrets](#211-smtp-send_email-secrets)
   - [2.12 Flow Runtime Execution](#212-flow-runtime-execution)
   - [2.13 Delay Node Resume (Queue)](#213-delay-node-resume-queue)
   - [2.14 Duplicate & Delete](#214-duplicate--delete)
3. [Edge Cases](#3-edge-cases)
4. [Failure Scenarios](#4-failure-scenarios)
5. [Runtime Test Matrix](#5-runtime-test-matrix)
6. [Automated Test Suggestions](#6-automated-test-suggestions)
7. [Production Readiness Checklist](#7-production-readiness-checklist)

---

## 1. Pre-Test Setup Checklist

Before running any tests, verify these conditions:

```
[ ] php artisan migrate  → all 6 automation tables exist
[ ] Flow builder addon enabled in DB:
    SELECT status, is_active FROM addons WHERE name = 'Flow builder';
    → must be: status=1, is_active=1

[ ] Organization has active subscription:
    SELECT status, valid_until FROM subscriptions WHERE organization_id = 1;
    → must be: status='active', valid_until > NOW()

[ ] Plan metadata includes Flow builder:
    SELECT metadata FROM subscription_plans WHERE id = 1;
    → metadata.addons["Flow builder"] = true

[ ] Redis running (for contact locking):
    redis-cli ping  → PONG

[ ] Queue worker running (for delay nodes):
    php artisan queue:work --queue=automation-flow-resume,default

[ ] Navigate to /automation/flows — must NOT redirect to /automation/basic

[ ] Verify readiness:
    php artisan tinker --execute="
    echo json_encode(
        app(App\Services\AutomationFlows\AutomationFlowAccessService::class)
            ->readinessReport(1), JSON_PRETTY_PRINT);
    "
    → builder_ready: true
```

---

## 2. Manual Testing Checklist

### 2.1 Access & Feature Flags

```
[ ] Visit /automation/flows as authenticated user
    → Shows flows list page (not redirected to /automation/basic)

[ ] Disable addon: UPDATE addons SET status=0 WHERE name='Flow builder';
    → Visit /automation/flows → redirected to /automation/basic
    → Re-enable: UPDATE addons SET status=1 WHERE name='Flow builder';

[ ] Set FLOW_BUILDER_V2_ENABLED=false in .env, php artisan config:clear
    → Visit /automation/flows → redirected to /automation/basic
    → Re-enable

[ ] Test without active subscription (soft test — set valid_until to past date)
    → Feature should be blocked or limits return 0
```

### 2.2 Flow List (Index Page)

```
[ ] Empty state: visit /automation/flows with no flows
    → Shows empty state message/UI (not an error)

[ ] Create a flow → appears in list immediately

[ ] Search by name:
    → Type flow name in search box → list filters correctly
    → Type non-existent name → empty list shown

[ ] Filter by status:
    → Filter 'draft' → shows only drafts
    → Filter 'published' → shows only published
    → Filter 'paused' → shows only paused

[ ] Pagination:
    → Create 11+ flows → second page appears

[ ] Row menu actions:
    [ ] Edit → opens builder for that flow
    [ ] Duplicate → creates copy, redirects to copy's builder
    [ ] Delete → shows confirm dialog, deletes flow, removed from list
```

### 2.3 Flow Creation

```
[ ] Click "Create" → FlowCreateModal opens

[ ] Create with only name (no description, no goal preset)
    → Flow created with default goal (sales_qualification)
    → Redirects to builder with correct starter template

[ ] Create with each goal preset:
    [ ] sales_qualification  → trigger + send_buttons (3 options) + 3 texts + end
    [ ] support_routing      → trigger + send_list (3 rows) + 3 texts + end
    [ ] appointment_booking  → trigger + send_text + save_reply + send_text + end
    [ ] seller_intake        → trigger + send_text + save_reply + condition + 2 texts + end

[ ] Create with long name (120 chars) → succeeds
[ ] Create with name 121 chars → validation error

[ ] Create with description (max 1000 chars) → succeeds
```

### 2.4 Canvas & Node System

```
[ ] Canvas renders correctly with at least the trigger node visible

[ ] Left library panel:
    [ ] "Messages" tab shows: Simple text, Media files, Interactive buttons, Interactive list
    [ ] "Actions" tab shows: Save reply, Condition, Add to Group, Remove from Group,
                             Update Contact, Assign to Agent, Human handoff,
                             AI assistant handoff, Send Email, Delay, End
    [ ] Search: type "text" → filters to matching types
    [ ] Search: type "xyz" → empty results shown

[ ] Drag node from library onto canvas
    → Node appears at drop position
    → Run is marked dirty (autosave triggers after 1.2s)

[ ] Click "+" button on library item → node added near currently selected node

[ ] Click a node → node becomes selected (highlighted border)

[ ] Double-click a node → inline inspector opens inside node card

[ ] Click canvas background → node deselected, inspector closes

[ ] Drag node card to new position:
    → Node moves
    → Position is saved on next autosave

[ ] Canvas zoom:
    [ ] Scroll to zoom in/out
    [ ] Zoom label in top-right updates (e.g., "85%")
    [ ] Zoom controls (bottom-left) work: +, -, fit view

[ ] "Focus" button (top-right):
    → Triggers browser fullscreen for workspace area
    [ ] Exit fullscreen works

[ ] "Open in new window" (board=1 param):
    → Opens canvas-only view in new tab (no sidebar, no header)

[ ] Hide/show library rail button works

[ ] Connect two nodes by dragging from output handle to input handle
    → Edge appears with branch label

[ ] Edge "insert node" button:
    → Click "+" on an edge → shows node type picker
    → Select type → node inserted between the two connected nodes

[ ] Edge click → shows delete option
    → Confirm → edge removed

[ ] Node context menu (right-click or ⋮ button):
    [ ] Rename → opens prompt (window.prompt) → name updates on canvas
    [ ] Duplicate → creates copy with "Copy" suffix at +80/+80 offset
    [ ] Delete → shows confirm dialog → node removed with all its edges
```

### 2.5 Each Node Type

#### trigger
```
[ ] Match mode dropdown: any_incoming / first_in_conversation / keyword_match
[ ] Select keyword_match → keywords input appears
    → Type "hello, hi, هيا" → stores as array
[ ] Select any_incoming → keywords input hidden
[ ] Starting step dropdown: lists all non-trigger nodes
    → Select a node → starting_step saved in config
```

#### send_text
```
[ ] Type message text
    → Placeholder text: enter something to send
[ ] Type {first_name} → validates as valid personalization
[ ] Empty text → shows validation error on node
```

#### send_media
```
[ ] Upload image (JPG/PNG < 5MB) → uploads, thumbnail appears
[ ] Upload video (MP4 < 16MB) → uploads
[ ] Upload audio (MP3 < 16MB) → uploads
[ ] Upload document (PDF < 100MB) → uploads
[ ] Upload oversized file → error message shown
[ ] Caption field: optional text
[ ] Remove media → asset deleted, node shows "upload" state again
```

#### send_buttons (Interactive Buttons)
```
[ ] Body field: required, max 1024 chars
[ ] Header field: optional, max 60 chars
[ ] Footer field: optional, max 60 chars
[ ] Add up to 3 buttons (max 3)
    → 4th button → validation error "max 3 buttons"
[ ] Each button: ID (auto-generated) + Title text
[ ] Button IDs are unique (no duplicates)
[ ] Each button needs a connected edge → validation error if missing
[ ] invalid_reply_behavior: release_to_fallback / repeat_prompt / end_run
```

#### send_list (Interactive List)
```
[ ] Body field: required
[ ] Button label: required (text for the "See options" button)
[ ] Add section with title
[ ] Add up to 10 rows per section
    → 11th row → validation error
[ ] Each row: ID + Title + optional Description
[ ] Row IDs are unique
[ ] Each row needs connected edge → validation error if missing
[ ] invalid_reply_behavior: release_to_fallback / repeat_prompt / end_run
```

#### save_reply_to_field
```
[ ] Save target: contact_field → shows field dropdown (org's contact fields)
[ ] Save target: session_variable → shows variable key input
[ ] Empty field_uuid → validation error
[ ] Empty variable_key → validation error
```

#### condition
```
[ ] Source: last_user_message / selected_button_id / selected_list_row_id / contact_field / flow_variable
[ ] Operator: equals / not_equals / contains / filled / not_filled
[ ] Source = contact_field → field dropdown appears
[ ] Source = flow_variable → variable key input appears
[ ] Must have both "matched" AND "unmatched" edges → validation error otherwise
```

#### add_to_group / remove_from_group
```
[ ] Group dropdown shows org's contact groups
[ ] Select a group → validates
[ ] Empty group_uuid → validation error
```

#### update_contact_field
```
[ ] Save target: contact_field or session_variable
[ ] Mode: save_last_user_message / static / last_input / session_variable
[ ] Mode = static → value text field appears
[ ] Mode = session_variable → source_variable_key input appears
```

#### assign_to_agent
```
[ ] If ticketing not active: shows error "Activate ticketing first"
[ ] Assignment mode: auto_assign / specific_agent / unassigned
[ ] specific_agent → agent dropdown appears with org's users
[ ] Note: flow CONTINUES after this node (does not pause)
```

#### human_handoff
```
[ ] Similar to assign_to_agent config
[ ] Note: flow PAUSES at this node (waiting_handoff state)
[ ] No outgoing edges allowed → validation error if any exist
```

#### handoff_to_ai_assistant
```
[ ] If AI assistant not active: shows error "Activate AI assistant first"
[ ] No outgoing edges allowed
[ ] Flow pauses at this node
```

#### send_email
```
[ ] Subject: required
[ ] Body: required
[ ] SMTP section:
    [ ] Host, Port, Username, Password, From Name, From Email
    [ ] All fields required for valid secret
    [ ] Password masked
    [ ] "Test" button (if exists) to verify SMTP connection
[ ] Empty subject or body → validation error
[ ] Missing SMTP config → validation error
```

#### delay
```
[ ] Minutes field: integer, min 1
[ ] Enter 0 → validation error (min 1 minute)
[ ] Enter 5 → valid
```

#### end
```
[ ] No config fields
[ ] No outgoing edges allowed
```

### 2.6 Validation System

```
[ ] Publish with NO nodes (just trigger) → error: "Choose the first step after trigger"

[ ] Publish with trigger having no outgoing edge → error shown

[ ] Publish with disconnected node (no path from trigger) →
    error: "Remove disconnected steps or reconnect them"

[ ] Publish with circular path (A → B → A) →
    error: "Remove circular paths before publishing"

[ ] Publish with condition missing unmatched branch →
    error: "Condition steps need both matched and unmatched branches"

[ ] Publish with send_buttons where one button has no edge →
    error: "Connect every button reply to its own next step"

[ ] Publish with end node having outgoing edge →
    error: "The end step should not connect to any other step"

[ ] Publish with human_handoff having outgoing edge →
    error: "Handoff steps should finish the journey"

[ ] Errors shown in FlowReadinessPanel (top of page)

[ ] Errors shown as badges on the specific node (FlowCanvasNodeRoutingHealth)

[ ] Clicking error node in readiness panel → canvas pans to that node

[ ] Plan limit test:
    → Set flow_builder_nodes_per_flow_limit = 3 in plan metadata
    → Add 4+ nodes → validation error: "Your current plan allows up to 3 nodes"
```

### 2.7 Save & Autosave

```
[ ] Edit a node's text → after 1.2 seconds → network request to /autosave
    → Header shows "Saving..." then "Saved"

[ ] Edit rapidly (multiple changes) → only ONE save request fires after last change

[ ] Save fails (simulate 500 response) → header shows "Error saving"
    → No data loss — changes still visible in UI

[ ] Navigate away with unsaved changes →
    [ ] FlowExitConfirmModal appears
    [ ] "Discard" → leaves without saving
    [ ] "Save & Exit" → saves then navigates
    [ ] Close modal → stays on builder

[ ] Browser tab close with unsaved changes →
    → Browser shows "Leave site?" dialog

[ ] Manual "Save" button (header) → fires PUT request immediately
```

### 2.8 Preview System

```
[ ] Click "Preview" in header menu → FlowPreviewModal opens

[ ] Preview shows WhatsApp-style conversation timeline

[ ] trigger node → shows "Flow starts when a matching WhatsApp message arrives."

[ ] send_text node → shows message text content

[ ] send_media node → shows caption (and media thumbnail if asset loaded)

[ ] send_buttons node → shows body + all button labels as clickable options
    → First button appears as simulated "user" selection

[ ] send_list node → shows body + list → simulates selecting first row

[ ] save_reply_to_field → shows "Wait for free text answer and save to ..."
    → Simulated user reply appears

[ ] condition → shows "Check rule and route to matching branch"
    → Always takes "matched" branch in preview

[ ] delay → shows "Wait N minute(s) before next step"

[ ] end → shows "End the journey"

[ ] Focus behavior: select a node → preview scrolls to that node's context

[ ] Update graph → preview auto-refreshes (220ms debounce)
```

### 2.9 Publish & Lifecycle

```
[ ] Publish a valid flow:
    → Status changes from 'draft' to 'published'
    → Header badge shows "Published"
    → "has_unpublished_changes" flag cleared

[ ] Try to publish with validation errors:
    → Publish button disabled (or click shows errors)
    → No version created

[ ] After publish, edit a node:
    → Status shows "Published (unsaved changes)" or similar
    → "has_unpublished_changes" = true

[ ] Re-publish after edits:
    → New version created (version_number increments)
    → Changes go live

[ ] Pause a published flow:
    → Status changes to 'paused'
    → Runtime will NOT trigger for new messages

[ ] Unpause a paused flow:
    → Status changes back to 'published'
    → Runtime resumes

[ ] Flow list shows correct status badges

[ ] Active flows limit:
    → Set flow_builder_active_flows_limit = 1 in plan
    → Publish first flow → OK
    → Try to publish second flow → error: "Active Flow Builder limit reached"
```

### 2.10 Asset Upload

```
[ ] Upload image to send_media node:
    → Thumbnail appears in node card
    → Asset UUID appears in node config

[ ] Upload video:
    → Video icon shown
    → File metadata visible

[ ] Upload too large file:
    → Error returned from server
    → Node still in "upload" state

[ ] Upload unsupported MIME type (if validation present)

[ ] Delete asset from node:
    → File deleted from storage
    → Node reverts to "no media" state

[ ] After page reload:
    → Asset still linked
    → Signed URL still works (valid for 24h)

[ ] Asset served via signed URL:
    → GET /automation/flows/{uuid}/assets/{assetUuid}?signature=...&expires=...
    → Returns correct content-type
    → Returns 403 if signature is invalid
    → Returns 403 if expired
```

### 2.11 SMTP (send_email) Secrets

```
[ ] Add send_email node
[ ] Fill SMTP settings → saved as encrypted secret in DB
    → Verify: SELECT node_type, node_id FROM automation_flow_node_secrets;

[ ] Save draft → secret synced in DB (graph_json has secret_ref, not raw credentials)

[ ] Reload builder → SMTP form shows "Connected" (display only, not raw password)

[ ] Duplicate flow → secrets duplicated in DB with new UUIDs

[ ] Delete email node → secret should be cleaned from DB
    (NOTE: verify this actually happens in AutomationFlowNodeSecretService::sanitizeGraphAndSyncSecrets)
```

### 2.12 Flow Runtime Execution

*Requires a connected WhatsApp account and the ability to send messages.*

```
[ ] TEST: any_incoming trigger
    → Publish flow with trigger: any_incoming
    → Send any message from WhatsApp to the bot number
    → Bot replies with first node's message

[ ] TEST: keyword_match trigger
    → Publish flow with keyword: "price"
    → Send message "price" → flow starts
    → Send message "hello" → flow does NOT start (different trigger)
    → Send message "I want to know the price" → flow starts (substring match)

[ ] TEST: first_in_conversation trigger
    → New contact sends first message → flow starts
    → Same contact sends second message → flow does NOT start again

[ ] TEST: send_text
    → Bot sends correct text message
    → Personalization: {first_name} replaced with contact's name

[ ] TEST: send_buttons
    → Bot sends interactive buttons message
    → Tap button "Option A" → flow continues on Option A branch

[ ] TEST: invalid reply to buttons
    → Bot sends buttons
    → Send plain text instead of tapping button
    → Behavior per invalid_reply_behavior config:
        - release_to_fallback: message shows in normal chat
        - repeat_prompt: buttons sent again
        - end_run: flow cancelled

[ ] TEST: send_list
    → Bot sends list message
    → Select list row → flow continues on that row's branch

[ ] TEST: save_reply_to_field
    → Bot asks question
    → Contact replies with text
    → Field updated in database:
       SELECT * FROM contact_meta WHERE contact_id=X AND field_id=Y;

[ ] TEST: condition (contact_field)
    → Contact has field value "VIP"
    → Condition: contact_field equals "VIP" → takes matched branch

[ ] TEST: add_to_group
    → Run flow with add_to_group node
    → Contact appears in target group

[ ] TEST: assign_to_agent
    → Ticket created/assigned
    → Flow CONTINUES after assignment

[ ] TEST: human_handoff
    → Ticket created/assigned
    → Flow PAUSES (waiting_handoff state)
    → Verify: SELECT status FROM automation_flow_runs WHERE contact_id=X;

[ ] TEST: end node
    → Run marked 'completed'
    → No more messages sent

[ ] TEST: Multiple concurrent users
    → Send messages from 2 different contacts simultaneously
    → Each gets independent run (no cross-contamination)

[ ] TEST: Run isolation
    → Contact A triggers flow
    → Contact B sends message while A's flow runs
    → Contact B gets their own run
```

### 2.13 Delay Node Resume (Queue)

*Requires queue worker running.*

```
[ ] Create flow: trigger → send_text → delay(1 min) → send_text → end
[ ] Publish flow
[ ] Send trigger message from WhatsApp
    → First send_text received ✓
[ ] Check run status:
    SELECT status, waiting_for, next_resume_at FROM automation_flow_runs WHERE contact_id=X;
    → status='waiting_delay', waiting_for='delay'

[ ] Wait 1+ minutes
    → Second send_text received ✓
    → Run status = 'completed'

[ ] Verify NO infinite loop:
    SELECT COUNT(*) FROM automation_flow_run_steps WHERE automation_flow_run_id=X;
    → Should be exactly 4 steps (trigger, send_text, delay, send_text/end)
    → NOT repeating delay steps

[ ] TEST: Multiple delays in sequence
    → trigger → delay(1min) → delay(2min) → end
    → Each delay fires once with correct timing

[ ] TEST: Cancel flow during delay
    → Run in waiting_delay state
    → Unpublish/pause the flow
    → Wait for delay to expire
    → resumeDelayedRun() should cancel (flow not published)
    → Run status = 'cancelled'

[ ] TEST: Queue worker down during delay
    → Kill queue worker
    → Send trigger message → run enters waiting_delay
    → Restart worker
    → Job processes (if not expired)
```

### 2.14 Duplicate & Delete

```
[ ] Duplicate flow from list row menu:
    → New flow created with "Copy" suffix
    → All nodes/edges preserved
    → Assets duplicated (new UUIDs, new physical files)
    → Secrets duplicated (new encrypted records)
    → Redirects to new flow's builder
    → Original flow unchanged

[ ] Duplicate from builder "More" menu:
    → Same behavior
    → If unsaved changes: prompts to save first

[ ] Delete flow from list:
    → Confirm dialog shown
    → Flow soft-deleted (deleted_at set)
    → Removed from list immediately
    → Run flows may still complete (cascade doesn't affect active runs)

[ ] Delete flow from builder:
    → Confirm dialog
    → Delete → redirects to /automation/flows

[ ] Deleted flow not accessible:
    → Direct URL /automation/flows/{uuid} → 404
```

---

## 3. Edge Cases

### Graph Structure Edge Cases

```
[ ] Flow with only trigger and end (minimal valid flow)
    → Should validate and publish

[ ] Flow with trigger → condition → matched branch → end
    → Unmatched branch has NO connection → should fail validation

[ ] Flow with 80 nodes (maximum)
    → Should work
[ ] Flow with 81 nodes
    → Validation error: "exceeds maximum node limit"

[ ] Extremely long message text (1024 chars in body)
    → WhatsApp compliance check should pass

[ ] Message text > 1024 chars
    → WhatsApp compliance error

[ ] 3 buttons (maximum)
    → Valid
[ ] Button title > 20 chars
    → WhatsApp compliance error

[ ] Trigger with 0 keywords in keyword_match mode
    → Validation error: "Add at least one keyword"

[ ] Two flows both set to trigger on any_incoming
    → Both published → runtime processes FIRST matching flow (by updated_at DESC)
    → Second flow never triggers if contact is already in a run

[ ] Flow with condition branching to both matched and end
    → The unmatched branch also needs connection → validation error
```

### Runtime Edge Cases

```
[ ] Contact has active run → new trigger message arrives
    → resumeWaitingRun() handles if waiting_input
    → OR if status=active: startRun() returns false (existing run blocks new one)

[ ] Contact message arrives 31 minutes after last activity (stale threshold)
    → Run expired automatically
    → New run starts from beginning

[ ] Contact taps button from OLD message (e.g., 25 hours later)
    → waiting_input run may be stale (1440 min = 24h)
    → Stale run expired → new run starts (or just falls through)

[ ] Delay node set to 0 minutes
    → max(1, 0) = 1 → uses 1 minute minimum

[ ] Delay node set to 9999 minutes (6.9 days)
    → Still dispatches job with that delay
    → But if waiting_input stale threshold (1440 min) elapses first, run may expire

[ ] send_media node with deleted asset
    → resolveMediaUrl returns null → run fails with reason

[ ] Condition operator 'contains' with empty value
    → Evaluates to... (check AutomationFlowRuntimeSupportService::evaluateCondition)

[ ] Flow unpublished WHILE a run is in waiting_delay
    → Job fires → resumeDelayedRun checks flow.status !== 'published' → cancels run

[ ] WhatsApp 24h customer care window CLOSED
    → send_text node reached
    → on_window_closed = 'fail_run': run fails
    → on_window_closed = 'release_to_fallback': run cancelled, message falls through
```

---

## 4. Failure Scenarios

### Test These Failure Conditions

| Scenario | Expected Behavior |
|---|---|
| Save request fails (500) | `saveState = 'error'`, no data loss in UI |
| Publish with invalid graph | Validation errors returned, no version created |
| Asset upload with wrong MIME | Server returns error, node remains empty |
| Asset too large | Server returns 413, UI shows error |
| SMTP credentials wrong | Email fails at runtime, run marked failed |
| WhatsApp API returns error | Run step recorded with error, run marked failed |
| Redis down | Contact lock bypassed (unsafe fallback), run continues |
| Queue worker down | Delayed runs queue up, eventually process when worker restarts |
| Contact deleted mid-run | `Contact::find()` returns null → run failed gracefully |
| Flow hard-deleted during run | Cascade deletes run (no orphan records) |
| Session variable key empty | Runtime checks `hasUsableKey('')` → false → validation blocks publish |
| Group deleted after flow published | Runtime `addToGroup` will fail silently or return empty result |

---

## 5. Runtime Test Matrix

For each node type that sends a WhatsApp message or modifies data:

| Node | Action | Expected DB Change | Expected WhatsApp Event |
|---|---|---|---|
| trigger | Message arrives | `automation_flow_run` created | — |
| send_text | Execute | `run_step` created (status=executed) | Message received by contact |
| send_media | Execute | `run_step` created | Media message received |
| send_buttons | First visit | `run_step` (status=waiting), run=waiting_input | Buttons received by contact |
| send_buttons | User taps | `run_step` (status=executed), run=active | — |
| send_list | First visit | `run_step` (status=waiting), run=waiting_input | List received by contact |
| send_list | User selects | `run_step` (status=executed), run=active | — |
| save_reply_to_field | Execute | contact field updated OR session var set | — |
| condition | matched | `run_step` (branch=matched) | — |
| condition | unmatched | `run_step` (branch=unmatched) | — |
| add_to_group | Execute | contact_group_contact record created | — |
| remove_from_group | Execute | contact_group_contact record removed | — |
| update_contact_field | Execute | contact_meta updated | — |
| assign_to_agent | Execute | Ticket created/assigned | — |
| human_handoff | Execute | Ticket created, run=waiting_handoff | — |
| delay | First visit | run=waiting_delay, job dispatched | — |
| delay | Resume | `run_step` (reason=delay_completed) | — |
| end | Execute | run=completed, completed_at set | — |

---

## 6. Automated Test Suggestions

### PHP Unit Tests

```php
// AutomationFlowGraphValidatorTest.php
test('validation fails without trigger node')
test('validation fails with two trigger nodes')
test('validation fails with unreachable node')
test('validation fails with circular path')
test('validation fails with condition missing unmatched branch')
test('validation passes with minimal valid flow: trigger → send_text → end')
test('validation blocks advanced nodes when plan disabled')
test('validation enforces node count limit')

// AutomationFlowGraphCompilerTest.php
test('compiled adjacency matches expected structure')
test('compiler filters invalid edges')
test('compiler keys nodes by id')
test('compiler handles empty graph gracefully')

// AutomationFlowRuntimeServiceTest.php
test('trigger any_incoming matches non-empty message')
test('trigger any_incoming does not match empty message')
test('trigger keyword_match matches containing keyword')
test('trigger keyword_match is case insensitive')
test('trigger first_in_conversation matches only first chat')
test('contact lock prevents concurrent execution')
test('stale active run is expired before new run starts')
test('delay node suspends run and dispatches job')
test('delay resume does NOT create infinite loop')  // ← THE CRITICAL FIX
test('delay resume advances to next node correctly')
test('send_buttons suspends run waiting for button')
test('valid button reply resumes run on correct branch')
test('invalid button reply with repeat_prompt resends buttons')
test('invalid button reply with end_run cancels run')
test('invalid button reply with release_to_fallback returns false')
test('condition evaluates equals operator correctly')
test('condition evaluates contains operator correctly')
test('max execution steps terminates run with failed status')
test('run fails gracefully when contact deleted')

// AutomationFlowBuilderServiceTest.php
test('create uses starter template for goal preset')
test('publish creates new version with correct version number')
test('publish increments runs on flow correctly')
test('duplicate copies assets with new UUIDs')
test('duplicate copies secrets with new references')
```

### Feature/Integration Tests

```php
// AutomationFlowControllerTest.php
test('GET /automation/flows returns 200 with flows list')
test('POST /automation/flows creates flow and redirects')
test('GET /automation/flows/{uuid} returns full builder payload')
test('PUT /automation/flows/{uuid} saves draft')
test('POST .../validate returns validation report')
test('POST .../publish creates version and activates flow')
test('POST .../pause toggles flow status')
test('POST .../duplicate creates copy')
test('DELETE /automation/flows/{uuid} soft deletes flow')
test('POST .../assets stores uploaded file')
test('DELETE .../assets/{assetUuid} removes file')
test('featureGuard redirects when addon disabled')
test('featureGuard blocks when schema not ready')
test('unauthorized user gets 403')

// ResumeAutomationFlowRunJobTest.php
test('job calls resumeDelayedRun with correct run')
test('job is noop when run no longer waiting_delay')
```

### JavaScript Tests (Vitest/Jest)

```javascript
// flowBuilderValidation.test.js
test('buildNodeErrors returns error for empty send_text')
test('buildNodeErrors returns error for buttons with no branches')
test('buildValidationSummary detects circular path')
test('buildValidationSummary detects disconnected node')

// flowBuilderDraft.test.js
test('cloneFlowValue deep clones without reference sharing')
test('makeFlowBuilderUuid generates unique prefixed UUIDs')
test('defaultNodeConfig returns correct defaults for each type')
test('buildFlowEdge creates correct edge structure')

// flowBuilderGraph.test.js
test('normalizeTriggerStart ensures trigger is start_node_id')
test('pruneOutgoingBranches removes edges for removed button IDs')
```

### Playwright E2E Tests

The project already has `tests/Playwright/flow-builder-canvas.spec.js`. Extend with:

```javascript
test('create flow, add node, save, publish end-to-end')
test('drag node from library onto canvas')
test('connect two nodes by dragging handle')
test('insert node on edge using + button')
test('delete node clears its edges')
test('preview shows correct timeline for simple flow')
test('validation errors appear in readiness panel')
test('autosave fires after editing node text')
```

---

## 7. Production Readiness Checklist

```
Infrastructure:
[ ] Redis configured and reachable (CACHE_DRIVER=redis)
[ ] Queue worker configured with Supervisor for auto-restart
    Worker command: php artisan queue:work --queue=automation-flow-resume,default --tries=3 --timeout=60
[ ] Storage disk writable (storage/app/)
[ ] In production: consider S3 for assets instead of local disk
[ ] APP_KEY set (for encrypting node secrets)

Database:
[ ] All 6 automation tables migrated
[ ] Indexes verified (check EXPLAIN on runtime queries)
[ ] Addon record enabled: status=1, is_active=1
[ ] Each organization has active subscription with Flow builder addon

Monitoring:
[ ] Queue backlog monitoring (Horizon or custom dashboard)
[ ] Alert on high failed_jobs count
[ ] Alert on runs stuck in waiting_delay past next_resume_at
[ ] Log slow WhatsApp API calls

Security:
[ ] APP_KEY is strong and not exposed
[ ] Signed asset URLs work correctly (APP_KEY consistency)
[ ] node_secrets.payload_json is encrypted (Crypt::encrypt)
[ ] No SMTP credentials logged in application logs

Performance:
[ ] Consider adding MySQL index on automation_flow_runs(contact_id, organization_id, status)
[ ] If high volume: consider partitioning automation_flow_run_steps by date
[ ] Test with 1000+ concurrent active runs

Feature flags:
[ ] FLOW_BUILDER_V2_ENFORCE_CUSTOMER_CARE_WINDOW=true (production default)
[ ] FLOW_BUILDER_V2_ON_WINDOW_CLOSED=release_to_fallback (recommended for production)
[ ] FLOW_BUILDER_V2_ALLOW_EXTERNAL_ACTIONS=false (unless send_email needed)

Smoke Test After Deployment:
[ ] Create a test flow (trigger + send_text + end)
[ ] Publish it
[ ] Send a WhatsApp message to the bot
[ ] Verify reply received
[ ] Check automation_flow_runs has status=completed
[ ] Check automation_flow_run_steps has 3 rows (trigger, send_text, end)
[ ] Delete the test flow
```

---

*Generated from source code analysis — 2026-05-31*
