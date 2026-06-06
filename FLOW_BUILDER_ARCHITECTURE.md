# Flow Builder — Architecture Reference

> Concise architecture diagrams and data structure reference.
> For full explanations, see [FLOW_BUILDER_FULL_DOCUMENTATION.md](./FLOW_BUILDER_FULL_DOCUMENTATION.md)

---

## System Architecture

```
┌──────────────────────────────────────────────────────────────────────┐
│  BROWSER                                                             │
│                                                                      │
│  ┌─────────────────────────────────────────────────────────────┐    │
│  │  Builder.vue (1446 lines — main orchestrator)               │    │
│  │                                                             │    │
│  │  Left Rail          Center Canvas        Right (Inline)    │    │
│  │  ┌──────────┐       ┌────────────────┐   ┌─────────────┐  │    │
│  │  │ Step     │       │   VueFlow      │   │ Inspector   │  │    │
│  │  │ Library  │──drag─▶  FlowCanvas   │   │ (embedded   │  │    │
│  │  │          │       │   Node (×N)    │   │  in node)   │  │    │
│  │  │ Messages │       │   FlowCanvas   │   │             │  │    │
│  │  │ Actions  │       │   Edge (×N)    │   │ Form per    │  │    │
│  │  └──────────┘       └────────────────┘   │ node type   │  │    │
│  │                                          └─────────────┘  │    │
│  │  Top: FlowBuilderHeaderCard (name, status, publish, menu)  │    │
│  │  Info: FlowReadinessPanel (errors, warnings)               │    │
│  └───────────────────────┬─────────────────────────────────────┘    │
│                          │  Axios HTTP + Inertia.js                 │
└──────────────────────────┼───────────────────────────────────────────┘
                           │
┌──────────────────────────▼───────────────────────────────────────────┐
│  LARAVEL WEB SERVER                                                  │
│                                                                      │
│  AutomationFlowController                                            │
│  ├── GET  /automation/flows            → list()                      │
│  ├── POST /automation/flows            → create()                    │
│  ├── GET  /automation/flows/{uuid}     → builderPayload()            │
│  ├── PUT  /automation/flows/{uuid}     → update()                    │
│  ├── POST .../autosave                 → update()                    │
│  ├── POST .../validate                 → validateDraft()             │
│  ├── POST .../preview                  → preview()                   │
│  ├── POST .../publish                  → publish()                   │
│  ├── POST .../pause                    → pause()                     │
│  ├── POST .../duplicate                → duplicate()                 │
│  ├── POST .../assets                   → uploadAsset()               │
│  ├── DELETE .../assets/{assetUuid}     → deleteAsset()               │
│  └── DELETE /automation/flows/{uuid}   → destroy()                   │
│                                                                      │
│  AutomationFlowBuilderService (orchestration)                        │
│  ├── AutomationFlowGraphValidator   (full validation)                │
│  ├── AutomationFlowGraphCompiler    (graph → compiled_json)          │
│  ├── AutomationFlowPreviewService   (simulate timeline)              │
│  ├── AutomationFlowNodeCatalog      (all 16 node types)              │
│  ├── AutomationFlowStarterTemplateService (4 preset graphs)          │
│  ├── AutomationFlowAssetService     (media files)                    │
│  ├── AutomationFlowNodeSecretService (SMTP encryption)               │
│  └── AutomationFlowBuilderPolicyService (policy enforcement)         │
└──────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────┐
│  RUNTIME (triggered by WhatsApp webhook)                             │
│                                                                      │
│  AutomationFlowRuntimeService                                        │
│  ├── withContactLock() ───────────── Redis lock                     │
│  ├── handleInboundUnlocked()                                         │
│  │   ├── Find active run → resumeWaitingRun()                        │
│  │   └── Match trigger → startRun() → continueRun()                 │
│  ├── continueRun() ─────────────── Node execution loop (max 60)     │
│  │   ├── send_text    → WhatsApp API                                 │
│  │   ├── send_buttons → WhatsApp API → waiting_input                │
│  │   ├── send_list    → WhatsApp API → waiting_input                │
│  │   ├── delay        → Queue Job    → waiting_delay                │
│  │   ├── condition    → evaluate     → branch                       │
│  │   ├── human_handoff → handoff service → waiting_handoff          │
│  │   └── end           → completed                                   │
│  └── resumeDelayedRun() ───────── called by ResumeAutomationFlowRunJob│
│                                                                      │
│  AutomationFlowRuntimeSupportService (helpers for runtime)          │
│  AutomationFlowPersonalizationService ({first_name} replacement)    │
│  AutomationFlowContactMutationService (group/field mutations)        │
│  AutomationFlowActionDispatchService  (email sending)               │
│  AutomationFlowConversationHandoffService (agent assignment)         │
└──────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────┐
│  PERSISTENCE                                                         │
│                                                                      │
│  MySQL:                                                              │
│  automation_flows ──────────────── automation_flow_versions          │
│       │                                  (compiled_json)             │
│       └── automation_flow_runs ──── automation_flow_run_steps        │
│       └── automation_flow_assets                                     │
│       └── automation_flow_node_secrets (encrypted)                   │
│                                                                      │
│  Redis:  automation-flow-runtime:{org}:{contact}  (contact lock)    │
│  Queue:  automation-flow-resume  (delay resume jobs)                │
└──────────────────────────────────────────────────────────────────────┘
```

---

## Graph Data Structures

### graph_json (Builder/Storage Format)

Stored in `automation_flows.graph_json` and `automation_flow_versions.graph_json`.

```json
{
  "start_node_id": "trigger-1",
  "nodes": [
    {
      "id": "trigger-1",
      "type": "trigger",
      "position": { "x": 80, "y": 120 },
      "config": {
        "match_mode": "keyword_match",
        "keywords": ["hello", "hi"],
        "starting_step": "send-text-1"
      },
      "ui": { "expanded": true, "title": null }
    },
    {
      "id": "send-text-1",
      "type": "send_text",
      "position": { "x": 420, "y": 120 },
      "config": { "text": "Hello {first_name}! How can I help?" },
      "ui": { "expanded": false, "title": "Welcome message" }
    },
    {
      "id": "buttons-1",
      "type": "send_buttons",
      "position": { "x": 760, "y": 120 },
      "config": {
        "body": "What would you like to do?",
        "header": "Menu",
        "footer": "Choose an option",
        "buttons": [
          { "id": "pricing", "title": "Pricing" },
          { "id": "demo", "title": "Book Demo" },
          { "id": "help", "title": "Get Help" }
        ],
        "invalid_reply_behavior": "repeat_prompt"
      },
      "ui": {}
    },
    { "id": "end-1", "type": "end", "position": { "x": 1100, "y": 120 }, "config": {}, "ui": {} }
  ],
  "edges": [
    { "id": "e1", "source_id": "trigger-1",  "target_id": "send-text-1", "branch": "default" },
    { "id": "e2", "source_id": "send-text-1", "target_id": "buttons-1",  "branch": "default" },
    { "id": "e3", "source_id": "buttons-1",   "target_id": "end-1",      "branch": "pricing" },
    { "id": "e4", "source_id": "buttons-1",   "target_id": "end-1",      "branch": "demo"    },
    { "id": "e5", "source_id": "buttons-1",   "target_id": "end-1",      "branch": "help"    }
  ]
}
```

### compiled_json (Runtime Format)

Stored in `automation_flow_versions.compiled_json`. **Never manually edited.**

```json
{
  "start_node_id": "trigger-1",
  "nodes": {
    "trigger-1":  { "id": "trigger-1",  "type": "trigger",      "config": {...}, "position": {...} },
    "send-text-1":{ "id": "send-text-1","type": "send_text",     "config": {...}, "position": {...} },
    "buttons-1":  { "id": "buttons-1",  "type": "send_buttons",  "config": {...}, "position": {...} },
    "end-1":      { "id": "end-1",      "type": "end",           "config": {},    "position": {...} }
  },
  "edges": [
    { "id": "e1", "source_id": "trigger-1",   "target_id": "send-text-1", "branch": "default"  },
    { "id": "e2", "source_id": "send-text-1", "target_id": "buttons-1",   "branch": "default"  },
    { "id": "e3", "source_id": "buttons-1",   "target_id": "end-1",       "branch": "pricing"  },
    { "id": "e4", "source_id": "buttons-1",   "target_id": "end-1",       "branch": "demo"     },
    { "id": "e5", "source_id": "buttons-1",   "target_id": "end-1",       "branch": "help"     }
  ],
  "adjacency": {
    "trigger-1":   [{ "id": "e1", "source_id": "trigger-1",   "target_id": "send-text-1", "branch": "default"  }],
    "send-text-1": [{ "id": "e2", "source_id": "send-text-1", "target_id": "buttons-1",   "branch": "default"  }],
    "buttons-1":   [
      { "id": "e3", "source_id": "buttons-1", "target_id": "end-1", "branch": "pricing" },
      { "id": "e4", "source_id": "buttons-1", "target_id": "end-1", "branch": "demo"    },
      { "id": "e5", "source_id": "buttons-1", "target_id": "end-1", "branch": "help"    }
    ]
  }
}
```

### state_json (Run Runtime State)

Stored in `automation_flow_runs.state_json`. Updated during execution.

```json
{
  "context": {
    "last_user_message": "hello",
    "selected_button_id": null,
    "selected_list_row_id": null,
    "input_type": "text",
    "chat_id": 12345
  },
  "runtime": {
    "release_to_fallback": false
  },
  "handoff": {
    "target": "human",
    "assignment_mode": "auto_assign",
    "assigned_user_id": 7,
    "ticket_id": 99,
    "started_at": "2026-05-31T10:00:00Z"
  },
  "variables": {
    "preferred_slot": "morning",
    "seller_property_type": "apartment"
  }
}
```

---

## Run Lifecycle State Machine

```
                     ┌─────────────────────────┐
                     │         [START]         │
                     │   message arrives        │
                     └────────────┬────────────┘
                                  │ startRun()
                                  ▼
                            ┌──────────┐
                     ┌──────│  active  │──────┐
                     │      └──────────┘      │
                     │           │            │
              send_buttons  delay node    end node
              send_list     dispatches    reached
              waiting for   job
              input
                     │           │            │
                     ▼           ▼            ▼
              ┌─────────────┐ ┌────────────┐ ┌───────────┐
              │waiting_input│ │waiting_    │ │ completed │ ← terminal
              └──────┬──────┘ │delay       │ └───────────┘
                     │        └─────┬──────┘
              user replies    job fires
              correctly       (after N min)
                     │              │
                     └──────┬───────┘
                            │ continueRun()
                            ▼
                      ┌──────────┐
                      │  active  │ ─── (loop back)
                      └──────────┘

  human_handoff / handoff_to_ai:
                      ┌──────────────────┐
                      │ waiting_handoff  │
                      └────────┬─────────┘
                               │ handoff resolved
                               ▼
                         ┌──────────┐
                         │  active  │  or  completed

  At any point, errors lead to:
              ┌──────────┐    ┌────────────┐    ┌─────────┐
              │  failed  │    │ cancelled  │    │ expired │ ← all terminal
              └──────────┘    └────────────┘    └─────────┘
```

---

## Frontend Component Dependency Graph

```
Builder.vue
├── imports flowBuilderStudio.js
│       └── uses: buildStartingStepOptions, toCanvasEdge, toGraphEdge,
│                 insertNodeOnCanvasEdge, nextBranchForNode, composeFlowBuilderUiJson
├── imports flowBuilderDraft.js
│       └── uses: cloneFlowValue, buildFlowEdge, defaultNodeConfig, makeFlowBuilderUuid
├── imports flowBuilderGraph.js
│       └── uses: normalizeTriggerStart, pruneOutgoingBranches
├── imports flowBuilderValidation.js
│       └── uses: buildNodeErrors, buildValidationSummary, saveStateLabelFor
├── imports flowBuilderMeta.js
│       └── uses: flowNodeLabel, flowNodeIcon, flowNodeCategory, resolveFlowNodeTitle
├── imports flowBuilderDanger.js
│       └── uses: beginNodeDelete, applyNodeDelete, createLeaveGuard
├── imports flowBuilderRouting.js
│       └── uses: resolveFlowBuilderDestination, FLOW_INDEX_PATH
├── imports flowBuilderInsights.js
│       └── uses: collectFlowInsights
├── imports flowCanvasLayout.js
│       └── uses: normalizeNodeRefreshList, refreshFlowCanvasNodeInternals
├── imports flowCanvasRuntime.js
│       └── uses: provideFlowCanvasRuntime, buildCanvasEdgeId, syncGraphNodePosition
├── imports useFlowCanvasSurfaceDrag.js  (composable)
│
├── renders FlowBuilderHeaderCard
├── renders FlowReadinessPanel
├── renders VueFlow
│       ├── renders FlowCanvasNode (custom node type)
│       │       ├── FlowCanvasNodeHeader
│       │       ├── FlowNodeInspectorRenderer (when focused)
│       │       │       ├── FlowNodeInspectorTriggerForm
│       │       │       ├── FlowNodeInspectorTextMediaForm
│       │       │       ├── FlowNodeInspectorInteractiveForm
│       │       │       ├── FlowNodeInspectorContactActionForm
│       │       │       ├── FlowNodeInspectorHandoffForm
│       │       │       ├── FlowNodeInspectorExternalActionForm
│       │       │       └── FlowNodeInspectorConditionForm
│       │       ├── FlowCanvasNodeMenu
│       │       └── FlowCanvasNodeRoutingHealth
│       └── renders FlowCanvasEdge (custom edge type)
│
├── renders FlowMetaEditModal
├── renders FlowPreviewModal
├── renders FlowExitConfirmModal
└── renders FlowBuilderDangerModals
```

---

## Access Control Decision Tree

```
Request arrives at AutomationFlowController
                │
                ▼
    FLOW_BUILDER_V2_ENABLED=true?
         NO ──────────────────────── 404 / redirect /automation/basic
         │
         YES
         ▼
    addons.name='Flow builder' 
    AND status=1 AND is_active=1?
         NO ──────────────────────── 403 (feature not enabled for org)
         │
         YES
         ▼
    org has active subscription?
    (subscriptions table, plan with valid_until > now)
         NO ──────────────────────── limits return 0 (effectively blocked)
         │
         YES
         ▼
    plan.metadata.addons['Flow builder'] = true?
         NO ──────────────────────── addonEnabled = false → 403
         │
         YES
         ▼
    automation_flows table exists?  (base_schema_ready)
         NO ──────────────────────── 503 (run migrations)
         │
         YES
         ▼
    automation_flow_assets table exists? (builder_schema_ready)
         NO ──────────────────────── Asset/secret features disabled
         │                           (flow listing still works)
         YES
         ▼
    org has permission for action?
    (automations.flows.view/add/edit/publish/delete)
         NO ──────────────────────── 403 Permission denied
         │
         YES
         ▼
         ✅ Proceed
```

---

## Node Type Quick Reference

```
TRIGGER LAYER (1 per flow)
─────────────────────────────────────
trigger           any_incoming | first_in_conversation | keyword_match

MESSAGE LAYER (WhatsApp-native, care window required)
─────────────────────────────────────
send_text         Plain text (supports {first_name} {email} etc.)
send_media        Image/video/audio/document via signed URL
send_buttons      Interactive quick reply (max 3 buttons) → branches by button.id
send_list         Interactive list picker (max 10 rows/section) → branches by row.id

ACTION LAYER (no WhatsApp window restriction)
─────────────────────────────────────
save_reply_to_field   Wait for free-text → save to contact_field or session_variable
condition         Evaluate rule → 'matched' or 'unmatched' branch
add_to_group      Add contact to contact group
remove_from_group Remove contact from contact group
update_contact_field  Set contact field to static/last_input/session_variable value
assign_to_agent   Open/assign ticket (flow CONTINUES after this)
human_handoff     Assign + PAUSE flow (waiting_handoff state)
handoff_to_ai_assistant   AI takes over + PAUSE flow
send_email        SMTP email via encrypted node secret
delay             Wait N minutes (ResumeAutomationFlowRunJob)
end               Mark run completed

ADVANCED TYPES (require flow_builder_advanced_enabled=true in plan):
send_media, send_buttons, send_list, save_reply_to_field, condition,
add_to_group, remove_from_group, update_contact_field, send_email,
assign_to_agent, human_handoff, handoff_to_ai_assistant, delay
```

---

## Queue Architecture

```
Queue: automation-flow-resume
    │
    └── ResumeAutomationFlowRunJob
            └── Dispatched by:  delay node in continueRun()
            └── Payload:        run.id (integer)
            └── Delay:          N minutes (from node config)
            └── Handler:        AutomationFlowRuntimeService::resumeDelayedRun()

Start worker:
  php artisan queue:work --queue=automation-flow-resume,default

For production (Supervisor recommended):
  [program:flow-resume-worker]
  command=php /path/to/artisan queue:work
          --queue=automation-flow-resume,default
          --tries=3
          --timeout=60
          --sleep=3
```

---

## Environment Variables Quick Reference

```
FLOW_BUILDER_V2_ENABLED                      bool    true
FLOW_BUILDER_V2_MAX_NODES                    int     80
FLOW_BUILDER_V2_MAX_EDGES                    int     160
FLOW_BUILDER_V2_AUTOSAVE_DEBOUNCE_MS         int     1200
FLOW_BUILDER_V2_MAX_EXECUTION_STEPS          int     60
FLOW_BUILDER_V2_RESUME_QUEUE                 string  automation-flow-resume
FLOW_BUILDER_V2_ASSET_URL_TTL_MINUTES        int     1440
FLOW_BUILDER_V2_ACTIVE_RUN_STALE_MINUTES     int     30
FLOW_BUILDER_V2_WAITING_INPUT_STALE_MINUTES  int     1440
FLOW_BUILDER_V2_WAITING_HANDOFF_STALE_MINUTES int    10080
FLOW_BUILDER_V2_CONTACT_LOCK_TTL_SECONDS     int     10
FLOW_BUILDER_V2_CONTACT_LOCK_WAIT_SECONDS    int     3
FLOW_BUILDER_V2_INVALID_REPLY_DEFAULT_BEHAVIOR string release_to_fallback
FLOW_BUILDER_V2_CUSTOMER_CARE_WINDOW_HOURS   int     24
FLOW_BUILDER_V2_ENFORCE_CUSTOMER_CARE_WINDOW bool    true
FLOW_BUILDER_V2_ON_WINDOW_CLOSED             string  fail_run
FLOW_BUILDER_V2_CHANNEL                      string  whatsapp
FLOW_BUILDER_V2_WHATSAPP_ONLY_MODE           bool    true
FLOW_BUILDER_V2_ALLOW_EXTERNAL_ACTIONS       bool    false
FLOW_BUILDER_V2_ALLOW_CRM_ACTIONS            bool    true
```
