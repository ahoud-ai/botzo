# Flow Builder — Full Technical Documentation

> **Version**: 2.0 (v2 Canvas Engine)
> **Stack**: Laravel 11 + Vue 3 + Inertia.js + VueFlow
> **Channel**: WhatsApp (exclusive)
> **Last Reviewed**: 2026-05-31

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Architecture Overview](#2-architecture-overview)
3. [Folder & File Mapping](#3-folder--file-mapping)
   - [Backend Files](#31-backend-files)
   - [Frontend Files](#32-frontend-files)
4. [Frontend Deep Analysis](#4-frontend-deep-analysis)
   - [UI Structure & Component Hierarchy](#41-ui-structure--component-hierarchy)
   - [State Management](#42-state-management)
   - [Canvas & Node System (VueFlow)](#43-canvas--node-system-vueflow)
   - [Autosave & Persistence](#44-autosave--persistence)
   - [Validation System (Client-side)](#45-validation-system-client-side)
   - [Preview System](#46-preview-system)
   - [Asset Upload](#47-asset-upload)
5. [Backend Deep Analysis](#5-backend-deep-analysis)
   - [API Endpoints](#51-api-endpoints)
   - [Service Layer Architecture](#52-service-layer-architecture)
   - [Graph Validation Engine](#53-graph-validation-engine)
   - [Graph Compiler](#54-graph-compiler)
   - [Runtime Execution Engine](#55-runtime-execution-engine)
   - [Preview Engine](#56-preview-engine)
   - [Asset & Secret Management](#57-asset--secret-management)
6. [Database Analysis](#6-database-analysis)
   - [Table Reference](#61-table-reference)
   - [Schema Details](#62-schema-details)
   - [Data Flow & Relationships](#63-data-flow--relationships)
7. [Node Types Reference](#7-node-types-reference)
8. [Flow Execution Lifecycle](#8-flow-execution-lifecycle)
   - [Phase 1: Build](#81-phase-1-build)
   - [Phase 2: Publish](#82-phase-2-publish)
   - [Phase 3: Runtime Trigger](#83-phase-3-runtime-trigger)
   - [Phase 4: Node Execution Loop](#84-phase-4-node-execution-loop)
   - [Phase 5: Suspension & Resume](#85-phase-5-suspension--resume)
9. [Environment & Configuration](#9-environment--configuration)
10. [Access Control & Feature Flags](#10-access-control--feature-flags)
11. [Missing Parts, Bugs & Technical Debt](#11-missing-parts-bugs--technical-debt)
12. [Local Setup Guide](#12-local-setup-guide)
13. [Production Readiness Review](#13-production-readiness-review)
14. [Developer Notes](#14-developer-notes)

---

## 1. Executive Summary

### What Is It?

The **Flow Builder** (internally called "Automation Flows v2") is a **visual, no-code workflow automation engine** embedded inside the Botzo platform. It allows business operators to design multi-step automated conversation journeys that execute over **WhatsApp** without writing any code.

### What Problem Does It Solve?

Before this feature, automated replies were limited to simple one-shot canned replies (`/automation/basic`). The Flow Builder replaces this with a graph-based system where:

- Multiple messages can be sent in sequence.
- The conversation branches based on customer choices (buttons, list selections).
- Contact data can be captured and stored during the conversation.
- Delays, conditions, and escalations to human agents are all handled automatically.
- Flows run asynchronously and resume after waiting periods.

### Business Value

| Use Case | How Flow Builder Handles It |
|---|---|
| Lead qualification | Multi-step button flow collects intent |
| Support routing | List message routes to correct department |
| Appointment booking | Save-reply captures preferred slot |
| Contact enrichment | Update contact fields during conversation |
| Human handoff | Smooth transition to agent with context |

### High-Level Flow

```
Operator builds graph in UI → publishes flow → flow listens for WhatsApp triggers →
runtime processes each node → contacts receive automated journey
```

---

## 2. Architecture Overview

### System Layers

```
┌─────────────────────────────────────────────────────────┐
│                    BROWSER (Vue 3)                       │
│  Builder.vue ─ VueFlow Canvas ─ Inspector Panel         │
│  flowBuilderStudio.js ─ flowBuilderDraft.js              │
│  flowBuilderValidation.js ─ flowBuilderGraph.js          │
└────────────────────┬────────────────────────────────────┘
                     │  Inertia.js / Axios (HTTP)
┌────────────────────▼────────────────────────────────────┐
│               LARAVEL WEB SERVER                         │
│  AutomationFlowController                                │
│  ├─ AutomationFlowBuilderService (CRUD + publish)        │
│  ├─ AutomationFlowGraphValidator (validation)            │
│  ├─ AutomationFlowGraphCompiler (compile to runtime)     │
│  ├─ AutomationFlowPreviewService (simulate timeline)     │
│  └─ AutomationFlowAssetService (media files)             │
└────────────────────┬────────────────────────────────────┘
                     │  Database / Cache / Queue
┌────────────────────▼────────────────────────────────────┐
│               PERSISTENCE LAYER                          │
│  MySQL: automation_flows, automation_flow_versions       │
│         automation_flow_runs, automation_flow_run_steps  │
│         automation_flow_assets, automation_flow_node_secrets│
│  Redis: contact_lock (distributed locking)              │
│  Queue: automation-flow-resume (delay jobs)              │
└────────────────────┬────────────────────────────────────┘
                     │  Triggered by incoming WhatsApp chat
┌────────────────────▼────────────────────────────────────┐
│               RUNTIME ENGINE                             │
│  AutomationFlowRuntimeService::handleInbound(Chat)       │
│  ├─ Contact lock (Redis, 10s TTL)                        │
│  ├─ Active run detection / stale run expiry              │
│  ├─ Trigger matching (any / first / keyword)             │
│  ├─ AutomationFlowRun creation                           │
│  └─ Node execution loop (max 60 steps)                   │
│      ├─ send_text → WhatsApp API                         │
│      ├─ send_buttons → WhatsApp API → wait for reply     │
│      ├─ delay → dispatch ResumeAutomationFlowRunJob      │
│      ├─ condition → evaluate → branch                    │
│      └─ handoff → pause run                             │
└─────────────────────────────────────────────────────────┘
```

### Data Flow for a Typical Flow Session

```
1. POST /automation/flows                ← Create (starter template)
2. GET  /automation/flows/{uuid}         ← Load builder payload
3. POST /automation/flows/{uuid}/autosave← Autosave every 1.2s of inactivity
4. POST /automation/flows/{uuid}/validate← Real-time validation
5. POST /automation/flows/{uuid}/preview ← Simulate timeline
6. POST /automation/flows/{uuid}/publish ← Compile + version + activate
   ↓
7. Incoming WhatsApp message arrives
   → AutomationFlowRuntimeService::handleInbound()
   → Flow runs node-by-node
   → AutomationFlowRun tracks state
   → AutomationFlowRunStep records each step
```

### Event Flow (Runtime)

```
WhatsApp Webhook
    │
    ▼
WebhookController::handle()
    │  (fires event or calls service directly)
    ▼
AutomationFlowRuntimeService::handleInbound(Chat $chat)
    │
    ├── withContactLock()  ──────────────── Redis lock (10s TTL, 3s wait)
    │       │
    │       ▼
    │   handleInboundUnlocked()
    │       ├── Find active run for contact?
    │       │       ├── YES → resumeWaitingRun()
    │       │       └── NO  → find matching published flow
    │       │                      └── startRun()
    │       │                              └── continueRun()
    │       │
    │       └── continueRun() ──────────── node execution loop
    │               ├── send_text    → WhatsApp API
    │               ├── send_buttons → WhatsApp API → return (wait)
    │               ├── delay        → dispatch Job → return (wait)
    │               ├── condition    → evaluate branch → loop
    │               └── end          → mark completed → return
    │
    └── release lock
```

---

## 3. Folder & File Mapping

### 3.1 Backend Files

#### Controller

| File | Role |
|---|---|
| `app/Http/Controllers/User/AutomationFlowController.php` | 13 HTTP endpoints. Delegates all business logic to services. Handles feature guard (schema + addon checks). |

#### Models

| File | Role | Key Relations |
|---|---|---|
| `app/Models/AutomationFlow.php` | Core entity. Stores graph, UI state, status. | `currentVersion()`, `versions()`, `runs()`, `assets()`, `nodeSecrets()` |
| `app/Models/AutomationFlowVersion.php` | Immutable snapshot of a published graph. | `belongsTo(AutomationFlow)` |
| `app/Models/AutomationFlowRun.php` | One execution per contact per triggered flow. | `belongsTo(AutomationFlow)`, `hasMany(AutomationFlowRunStep)` |
| `app/Models/AutomationFlowRunStep.php` | One row per node executed. | `belongsTo(AutomationFlowRun)` |
| `app/Models/AutomationFlowAsset.php` | Uploaded media files for nodes. | `belongsTo(AutomationFlow)` |
| `app/Models/AutomationFlowNodeSecret.php` | Encrypted SMTP credentials per node. | `belongsTo(AutomationFlow)` |

#### HTTP Requests (Validation)

| File | Validates |
|---|---|
| `app/Http/Requests/AutomationFlows/StoreAutomationFlowRequest.php` | `name` (req, max 120), `description` (opt, max 1000), `goal_preset` |
| `app/Http/Requests/AutomationFlows/SaveAutomationFlowRequest.php` | `name`, `description`, `graph_json` (nodes must have `id`+`type`, edges `source_id`+`target_id`), `ui_json`, `node_secrets` |
| `app/Http/Requests/AutomationFlows/ValidateAutomationFlowRequest.php` | `graph_json`, `node_secrets` |
| `app/Http/Requests/AutomationFlows/PreviewAutomationFlowRequest.php` | `graph_json`, `focus_node_id` (opt) |
| `app/Http/Requests/AutomationFlows/UploadAutomationFlowAssetRequest.php` | `file` (required), `media_kind` (opt: image/video/audio/document) |

#### Services (20 classes)

| File | Role |
|---|---|
| `AutomationFlowAccessService.php` | Checks runtime enabled, addon enabled, DB schema ready. Single source of truth for "can this org use Flow Builder?" |
| `AutomationFlowBuilderService.php` | Orchestrates all builder operations: list, create, update, validate, publish, preview, duplicate, builderPayload |
| `AutomationFlowGraphValidator.php` | Full graph validation: structure, node configs, plan limits, WhatsApp compliance, circular paths |
| `AutomationFlowGraphCompiler.php` | Converts raw graph to optimized runtime format with adjacency list |
| `AutomationFlowRuntimeService.php` | **Core execution engine.** Handles inbound messages, runs nodes, manages flow state |
| `AutomationFlowRuntimeSupportService.php` | Helper methods for runtime: buildInboundContext, resolveNextNodeId, recordStep, failRunAtNode, evaluateCondition |
| `AutomationFlowPreviewService.php` | Simulates flow execution to generate preview timeline (no side effects) |
| `AutomationFlowNodeCatalog.php` | Defines all 16 node types with metadata (label, icon, category, advanced flag) |
| `AutomationFlowStarterTemplateService.php` | Generates pre-built starter graphs for 4 goal presets |
| `AutomationFlowBuilderPolicyService.php` | Enforces builder policy: which node types are allowed based on config |
| `AutomationFlowAssetService.php` | Store/delete/duplicate media assets, generate signed URLs |
| `AutomationFlowNodeSecretService.php` | Encrypt/decrypt SMTP credentials, sync secret refs with DB |
| `AutomationFlowPersonalizationService.php` | Replaces `{first_name}`, `{email}`, etc. in message text at runtime |
| `AutomationFlowContactMutationService.php` | add_to_group, remove_from_group, update_contact_field operations |
| `AutomationFlowActionDispatchService.php` | Sends emails via SMTP using node secrets |
| `AutomationFlowConversationHandoffService.php` | assign_to_agent, human_handoff, handoff_to_ai_assistant logic |
| `AutomationFlowRunQuotaService.php` | Checks monthly run quotas against plan limits |
| `AutomationFlowSessionVariableService.php` | Manages in-memory flow variables during execution |
| `AutomationFlowWhatsappComplianceService.php` | Validates buttons (max 3), lists (max 10 rows), text length limits |
| `AutomationFlowConversationHandoffService.php` | Manages handoff capabilities per organization |

#### Jobs

| File | Role |
|---|---|
| `app/Jobs/ResumeAutomationFlowRunJob.php` | Queued job dispatched by `delay` node. Calls `AutomationFlowRuntimeService::resumeDelayedRun()` after the delay expires. Queue: `automation-flow-resume` |

#### Migrations

| File | Creates |
|---|---|
| `database/migrations/2026_03_13_010000_create_automation_flow_tables.php` | `automation_flows`, `automation_flow_versions`, `automation_flow_runs`, `automation_flow_run_steps` |
| `database/migrations/2026_03_13_030000_create_automation_flow_assets_and_node_secrets_tables.php` | `automation_flow_assets`, `automation_flow_node_secrets` |

#### Routes

| File | Contains |
|---|---|
| `routes/web/automation.php` | 13 authenticated web routes for builder CRUD operations |
| `routes/web/public.php` (line 94) | `GET /automation/flows/{uuid}/assets/{assetUuid}` — signed URL route for serving media files (`flowbuilder.assets.show`) |

#### Config

| File | Key |
|---|---|
| `config/automation_flows.php` | All feature configuration: enabled, max_nodes, max_edges, autosave_debounce_ms, runtime settings, WhatsApp settings, builder policy |

---

### 3.2 Frontend Files

#### Pages

| File | Role |
|---|---|
| `resources/js/Pages/User/Automation/Flows/Index.vue` | List of flows with search/filter, create modal trigger, readiness status panel |
| `resources/js/Pages/User/Automation/Flows/Builder.vue` | **Main editor** (1446 lines). Orchestrates entire builder: canvas, inspector, preview, autosave, publish, drag/drop |

#### Components (27 files in `resources/js/Components/AutomationFlows/`)

**Header & Controls:**

| Component | Role |
|---|---|
| `FlowBuilderHeaderCard.vue` | Top bar: flow name, status badge, Publish button, More menu |
| `FlowReadinessPanel.vue` | Displays validation errors and plan limit warnings above canvas |

**Canvas:**

| Component | Role |
|---|---|
| `FlowCanvasNode.vue` | Renders a single node card on canvas. Handles click/double-click, drag handle, inline editor open |
| `FlowCanvasNodeHeader.vue` | Node card header: icon, type badge, title, menu trigger |
| `FlowCanvasNodeMenu.vue` | Context menu on node: Rename, Duplicate, Delete |
| `FlowCanvasEdge.vue` | Renders edge (connection) between nodes with branch label and "insert node" button |
| `FlowCanvasCompactNode.vue` | Compact representation of node when zoomed out |
| `FlowCanvasNodeRoutingHealth.vue` | Visual indicator of validation errors on a node |

**Inspector (right panel — node config editor):**

| Component | Role |
|---|---|
| `FlowInspectorPanel.vue` | Container for inspector. Shows instructions when no node selected |
| `FlowNodeInspectorRenderer.vue` | Routes to correct form based on node type. Shows node-level errors |
| `FlowNodeInspectorTriggerForm.vue` | Edits trigger config: match_mode, keywords, starting_step |
| `FlowNodeInspectorTextMediaForm.vue` | Edits send_text (text field) and send_media (media_type, upload) |
| `FlowNodeInspectorInteractiveForm.vue` | Edits send_buttons (buttons array) and send_list (sections/rows array) |
| `FlowNodeInspectorContactActionForm.vue` | Edits add_to_group, remove_from_group, update_contact_field, save_reply_to_field |
| `FlowNodeInspectorHandoffForm.vue` | Edits assign_to_agent, human_handoff, handoff_to_ai_assistant |
| `FlowNodeInspectorExternalActionForm.vue` | Edits send_email (subject, body, SMTP secrets) and delay (minutes) |
| `FlowNodeInspectorConditionForm.vue` | Edits condition node: source, operator, value |

**Modals & Drawers:**

| Component | Role |
|---|---|
| `FlowCreateModal.vue` | Create new flow dialog: name, description, goal_preset |
| `FlowMetaEditModal.vue` | Edit flow name/description inline |
| `FlowPreviewModal.vue` | Full preview timeline modal with simulated WhatsApp UI |
| `FlowPreviewDrawer.vue` | Alternative preview in a side drawer |
| `FlowExitConfirmModal.vue` | Warns of unsaved changes when navigating away |
| `FlowDangerConfirmModal.vue` | Confirm destructive actions |
| `FlowBuilderDangerModals.vue` | Container for node delete and flow delete confirmation modals |
| `FlowListRowMenu.vue` | Row-level action menu in the flows list |

**Utilities:**

| Component | Role |
|---|---|
| `FlowAutosizeTextarea.vue` | Auto-growing textarea for text node editor |
| `FlowStepGuidePanel.vue` | Contextual help/guide panel |

#### JavaScript Utilities (`resources/js/Components/AutomationFlows/flow*.js`)

| File | Role |
|---|---|
| `flowBuilderStudio.js` | Canvas orchestration: `buildStartingStepOptions`, `toCanvasEdge`, `toGraphEdge`, `insertNodeOnCanvasEdge`, `nextBranchForNode`, `resolveNodePayload`, `composeFlowBuilderUiJson` |
| `flowBuilderDraft.js` | Draft utilities: `cloneFlowValue`, `buildFlowEdge`, `defaultNodeConfig`, `makeFlowBuilderUuid` |
| `flowBuilderGraph.js` | Graph transformations: `normalizeTriggerStart`, `pruneOutgoingBranches` |
| `flowBuilderValidation.js` | Client-side validation: `buildNodeErrors`, `buildValidationSummary`, `saveStateLabelFor`, `validationErrorsFromResponse` |
| `flowBuilderMeta.js` | Node metadata: `flowNodeLabel`, `flowNodeIcon`, `flowNodeCategory`, `resolveFlowNodeTitle` |
| `flowBuilderCopy.js` | i18n helper texts for UI labels |
| `flowBuilderRouting.js` | Inertia navigation helpers: `resolveFlowBuilderDestination`, `FLOW_INDEX_PATH` |
| `flowBuilderDanger.js` | Destructive operations: `beginNodeDelete`, `applyNodeDelete`, `createLeaveGuard` |
| `flowBuilderGoalPresets.js` | Preset template definitions for UI |
| `flowBuilderInsights.js` | `collectFlowInsights` — computes readiness insights for the top panel |
| `flowCanvasLayout.js` | VueFlow layout helpers: `normalizeNodeRefreshList`, `refreshFlowCanvasNodeInternals` |
| `flowCanvasRuntime.js` | Vue `provide/inject` context for canvas: `provideFlowCanvasRuntime`, `buildCanvasEdgeId`, `syncGraphNodePosition` |
| `flowNodePresenter.js` | Normalizes node type inference from config shape |
| `flowNodeVisuals.js` | Color/style mappings per node type |
| `flowIconRegistry.js` | Maps node type strings to Lucide icon components |
| `useFlowCanvasNode.js` | Composable for node-level operations |
| `useFlowCanvasSurfaceDrag.js` | Composable for drag-on-canvas behavior: start, stop, select, guard |
| `useFlowNodeInspector.js` | Composable managing inspector state: selected node, edits, secret fields |

---

## 4. Frontend Deep Analysis

### 4.1 UI Structure & Component Hierarchy

```
Builder.vue (page)
├── SettingLayout (wrapper)
├── FlowBuilderHeaderCard      ← sticky top bar
│   ├── Flow name + status badge
│   ├── Save button / autosave label
│   ├── Publish button
│   └── More menu (validate, preview, pause, duplicate, delete)
├── FlowReadinessPanel         ← validation warnings strip
├── [Left Rail] Step Library
│   ├── Messages tab (send_text, send_media, send_buttons, send_list)
│   └── Actions tab (all other types)
├── [Center] VueFlow Canvas
│   ├── FlowCanvasNode (×N)    ← one per node
│   │   ├── FlowCanvasNodeHeader
│   │   ├── FlowNodeInspectorRenderer  ← inline editor (when focused)
│   │   └── FlowCanvasNodeRoutingHealth
│   └── FlowCanvasEdge (×N)   ← one per edge
│       └── Branch label + "insert" button
├── FlowMetaEditModal
├── FlowPreviewModal
│   └── Simulated WhatsApp UI timeline
├── FlowExitConfirmModal
└── FlowBuilderDangerModals
    ├── Node delete confirm
    └── Flow delete confirm
```

### 4.2 State Management

The builder uses **local Vue 3 reactive state only** — no Pinia or Vuex. All state lives inside `Builder.vue` as `ref()` values.

**Key reactive state variables:**

| Variable | Type | Purpose |
|---|---|---|
| `draft` | `ref(Object)` | Current flow data (name, status, graph_json, ui_json) |
| `nodes` | `ref(Array)` | VueFlow node objects (canvas representation) |
| `edges` | `ref(Array)` | VueFlow edge objects (canvas representation) |
| `activeNodeId` | `ref(String)` | Currently selected node ID |
| `focusedNodeId` | `ref(String)` | Currently open inline editor node ID |
| `saveState` | `ref(String)` | `'saved'` / `'dirty'` / `'autosaving'` / `'saving'` / `'error'` |
| `validation` | `ref(Object)` | `{ valid, errors[], warnings[] }` |
| `previewData` | `ref(Object)` | Preview timeline from backend |
| `assets` | `ref(Object)` | Map of `{ uuid → assetObject }` |
| `nodeSecrets` | `ref(Object)` | Map of `{ nodeId → { host, port, user, ... } }` |

**Provider/Inject pattern:**

`provideFlowCanvasRuntime()` in `Builder.vue` injects canvas context into all child nodes via Vue's `provide`:
- `activeNodeId`, `focusedNodeId`, `draggingNodeId`
- `isNodeActive()`, `isNodeFocused()`, `isNodeDragging()` — reactive predicates
- Callbacks: `openNodeSurface`, `selectNodeSurface`, `toggleNodeInline`, `collapseNodeInline`

Child components (`FlowCanvasNode.vue`) inject this context via `useFlowCanvasRuntime()`.

### 4.3 Canvas & Node System (VueFlow)

The canvas is powered by **VueFlow** (`@vue-flow/core`), a Vue port of React Flow.

**Custom node type registration:**
```javascript
const nodeTypes = { automationCanvasNode: markRaw(FlowCanvasNode) };
const edgeTypes = { automationCanvasEdge: markRaw(FlowCanvasEdge) };
```

**Node data structure** (each VueFlow node's `data` property):
```javascript
{
  nodeType: 'send_text',          // flow node type
  config: { text: 'Hello!' },     // node configuration
  title: 'Welcome message',       // display title
  label: 'Simple text',           // type label
  category: 'messages',
  errors: [...],                  // per-node validation errors
  contactFields: [...],           // injected from props
  contactGroups: [...],
  asset: { uuid, url, ... },      // if node has media
  nodeSecret: { ... },            // SMTP credentials (display only)
  compliance: { ... },            // WhatsApp limits
  runtime: { ... },               // runtime config
  connectedBranches: [...],       // existing outgoing edge branches
  onUpdate: fn,                   // callbacks to Builder.vue
  onDelete: fn,
  onDuplicate: fn,
  onRename: fn,
  onQuickAdd: fn,
  onUploadAsset: fn,
  onRemoveAsset: fn,
  onUpdateSecret: fn,
  onRemapBranches: fn,
  onPruneBranches: fn,
  onUpdateStartingStep: fn,
}
```

**Graph ↔ Canvas sync:**

The builder maintains TWO representations simultaneously:
1. **Graph JSON** (`draft.value.graph_json`) — the canonical data format stored in DB
2. **VueFlow nodes/edges arrays** — the visual representation

These are kept in sync via:
- `rebuildCanvas(graph)` — full rebuild from graph JSON
- `syncCanvasPresentation({ nodeIds })` — partial refresh of specific nodes
- `graphFromCanvas()` — snapshot canvas back to graph JSON

**Drag & Drop:**

Two types of drag supported:
1. **Library drag**: Drag node type from left sidebar onto canvas. Uses HTML5 `dataTransfer` with type `'application/x-automation-node-type'`.
2. **Node surface drag**: Drag existing node cards to reposition. Handled by `useFlowCanvasSurfaceDrag.js` composable.

**Connection handling:**
```javascript
const onConnect = (connection) => {
  const nextEdge = {
    source_id: connection.source,
    target_id: connection.target,
    branch: connection.sourceHandle || 'default',
  };
  // Replace any existing edge on same source+branch
  graph.edges = graph.edges.filter(e => !(e.source_id === nextEdge.source_id && e.branch === nextEdge.branch));
  graph.edges.push(buildFlowEdge(...));
  rebuildDirtyCanvas(graph);
};
```

### 4.4 Autosave & Persistence

The autosave system uses a **debounced timer** approach:

```
User edits node
    └─→ markDraftDirty()
            ├─ saveState = 'dirty'
            └─ queueAutosave()
                    └─ setTimeout(persistDraft, 1200ms)  ← debounced

persistDraft()
    ├─ saveState = 'autosaving'
    ├─ POST /automation/flows/{uuid}/autosave
    ├─ saveState = 'saved'
    ├─ runValidation()
    └─ refreshPreview() (if preview panel open)
```

**Concurrency guard**: If a save is already in flight (`activeSavePromise`), new dirty events set `changesQueuedDuringSave = true`. After the in-flight save completes, another save cycle starts.

**Manual save**: `PUT /automation/flows/{uuid}` (same payload but different HTTP method). Used when user explicitly clicks "Save".

**Save payload:**
```javascript
{
  name: draft.value.name,
  description: draft.value.description,
  graph_json: graphFromCanvas(),   // latest canvas state
  ui_json: composeUiJson(),        // viewport, panel states, active node
  node_secrets: nodeSecrets.value  // SMTP credentials (never stored in graph_json)
}
```

**Navigation guard**: `router.on('before', handleInertiaBefore)` + `window.beforeunload` event prevent accidental navigation when there are unsaved changes.

### 4.5 Validation System (Client-side)

Two layers of validation:

**Layer 1 — Inline node validation** (`flowBuilderValidation.js::buildNodeErrors`):
- Runs client-side on every `syncCanvasPresentation()` call
- Each node shows its own error badges
- Does NOT require a server round-trip
- Checks: empty text, missing asset, no buttons, no branches, SMTP incomplete, etc.

**Layer 2 — Server validation** (`POST /validate`):
- Full graph validation including: circular paths, unreachable nodes, plan limits, WhatsApp compliance
- Returns `{ valid, errors[], warnings[] }`
- Triggered after every save and manually via "Validate" menu option

**Validation state** flows into:
- `FlowReadinessPanel` — shows blocking errors count
- `FlowCanvasNodeRoutingHealth` — per-node error indicators on canvas
- `FlowBuilderHeaderCard` — Publish button is disabled if `errors.length > 0`

### 4.6 Preview System

The preview generates a **simulated conversation timeline** showing what the WhatsApp chat would look like.

**Trigger**: Opens preview modal → `refreshPreview()` → `POST /automation/flows/{uuid}/preview`

**Timeline items** (each step has a `kind`):
- `'assistant'` — messages sent by the bot
- `'user'` — simulated user choices (first button pressed, first list row selected)
- `'system'` — system events (trigger fired, delay started, handoff, end)

**Focus behavior**: If `focus_node_id` is provided, the timeline is sliced to show context around that specific node.

### 4.7 Asset Upload

Media nodes (`send_media`) allow uploading images, videos, audio, and documents.

**Upload flow:**
```javascript
// Builder.vue::uploadNodeAsset()
const formData = new FormData();
formData.append('file', file);
formData.append('media_kind', mediaKind);
await axios.post(`/automation/flows/${uuid}/assets`, formData);
// → response.data.asset.uuid
// → updateNode(nodeId, { config: { asset_id: assetUuid } })
```

Assets are stored at `storage/app/automation-flows/{org_id}/{flow_uuid}/{filename}` on the `local` disk.

Asset URLs are **signed temporary URLs** (TTL: 1440 minutes by default):
```php
URL::temporarySignedRoute('flowbuilder.assets.show', now()->addMinutes(1440), [...])
```

Route `flowbuilder.assets.show` is in `routes/web/public.php` with `->middleware('signed')`.

---

## 5. Backend Deep Analysis

### 5.1 API Endpoints

All routes are under authenticated middleware. Full list:

| Method | URI | Controller Method | Purpose |
|---|---|---|---|
| GET | `/automation/flows` | `index` | List flows (paginated, searchable, filterable by status) |
| POST | `/automation/flows` | `store` | Create new flow with starter template |
| GET | `/automation/flows/{uuid}` | `show` | Load builder payload (flow + library + assets + preview + validation) |
| PUT | `/automation/flows/{uuid}` | `update` | Save draft (manual save) |
| POST | `/automation/flows/{uuid}/autosave` | `autosave` | Autosave draft (delegates to `update`) |
| POST | `/automation/flows/{uuid}/validate` | `validateDraft` | Validate graph (returns errors/warnings) |
| POST | `/automation/flows/{uuid}/preview` | `preview` | Generate preview timeline |
| POST | `/automation/flows/{uuid}/publish` | `publish` | Publish flow (validate + compile + version + activate) |
| POST | `/automation/flows/{uuid}/pause` | `pause` | Toggle pause status |
| POST | `/automation/flows/{uuid}/duplicate` | `duplicate` | Clone flow with assets and secrets |
| POST | `/automation/flows/{uuid}/assets` | `uploadAsset` | Upload media file |
| DELETE | `/automation/flows/{uuid}/assets/{assetUuid}` | `deleteAsset` | Delete media file |
| DELETE | `/automation/flows/{uuid}` | `destroy` | Soft-delete flow |
| GET | `/automation/flows/{uuid}/assets/{assetUuid}` | `showAsset` | Serve signed media file (public.php) |

**Builder payload** (returned by `show` endpoint):
```json
{
  "flow": { "id", "uuid", "name", "status", "graph_json", "ui_json", ... },
  "builder_runtime": { "autosave_debounce_ms", "builder_policy", "runtime", "whatsapp_compliance", "handoff_capabilities" },
  "library": [...],         // available node types for this org
  "plan_limits": { ... },   // flow/node/run limits
  "contact_fields": [...],  // org's contact fields
  "contact_groups": [...],  // org's contact groups
  "assignable_agents": [...],
  "assets": { uuid: assetObject },
  "node_secrets": { nodeId: { type, display_name } },
  "preview": { scenario, steps[] },
  "validation": { valid, errors[], warnings[] }
}
```

### 5.2 Service Layer Architecture

The builder uses **constructor injection** throughout. The full dependency tree from the controller:

```
AutomationFlowController
├── AutomationFlowBuilderService
│   ├── AutomationFlowGraphValidator
│   │   ├── AutomationFlowNodeCatalog
│   │   ├── AutomationFlowBuilderPolicyService
│   │   ├── AutomationFlowNodeSecretService
│   │   ├── SubscriptionPlanLimitService
│   │   ├── AutomationFlowWhatsappComplianceService
│   │   ├── AutomationFlowConversationHandoffService
│   │   └── AutomationFlowSessionVariableService
│   ├── AutomationFlowGraphCompiler
│   ├── AutomationFlowPreviewService
│   │   ├── AutomationFlowGraphCompiler
│   │   └── AutomationFlowAssetService
│   ├── AutomationFlowNodeCatalog
│   ├── AutomationFlowStarterTemplateService
│   │   ├── AutomationFlowBuilderPolicyService
│   │   ├── AutomationFlowNodeCatalog
│   │   └── SubscriptionPlanLimitService
│   ├── AutomationFlowBuilderPolicyService
│   ├── AutomationFlowAssetService
│   ├── AutomationFlowNodeSecretService
│   ├── AutomationFlowWhatsappComplianceService
│   ├── AutomationFlowConversationHandoffService
│   ├── SubscriptionPlanLimitService
│   └── OrganizationHierarchyService
├── AutomationFlowAccessService
│   └── AddonStateService
│       ├── SubscriptionPlanLimitService
│       └── TrialAddonEntitlementService
└── AutomationFlowAssetService
```

### 5.3 Graph Validation Engine

`AutomationFlowGraphValidator::validate()` performs these checks **in order**:

**1. Plan Limits:**
- Node count ≤ plan's `flow_builder_nodes_per_flow_limit`
- If `flow_builder_advanced_enabled = false`, blocks advanced node types

**2. Graph Structure:**
- At least 1 node
- Exactly 1 trigger node
- Valid `start_node_id` pointing to existing trigger
- All nodes reachable from trigger (BFS traversal)
- No circular paths (DFS cycle detection)
- No self-connections

**3. Node Config Validation** (per node type):
- `trigger`: valid `match_mode`, keywords present if `keyword_match`
- `send_text`: non-empty text
- `send_media`: valid media_type, asset uploaded
- `send_buttons`: body + min 1 button, no duplicate IDs, WhatsApp compliance
- `send_list`: body + button_label + min 1 row, section titles, no duplicate IDs
- `save_reply_to_field`: valid save_target, valid field_uuid or variable_key
- `condition`: valid source + operator + field reference
- `add_to_group` / `remove_from_group`: valid group_uuid
- `update_contact_field`: valid save_target, mode, field/variable reference
- `assign_to_agent` / `human_handoff`: ticketing active, valid assignment_mode
- `handoff_to_ai_assistant`: AI assistant module active
- `send_email`: subject + body + complete SMTP secret
- `delay`: minutes ≥ 1

**4. Edge Validation:**
- Both source and target exist
- `condition` has both `matched` AND `unmatched` branches
- Every button ID in `send_buttons` has a connected edge
- Every row ID in `send_list` has a connected edge
- `end`, `human_handoff`, `handoff_to_ai_assistant` have no outgoing edges

**5. WhatsApp Warnings:**
- Warns if flow contains message nodes (customer care window constraint)

### 5.4 Graph Compiler

`AutomationFlowGraphCompiler::compile()` converts the builder's graph format into a **runtime-optimized format**:

**Input (graph_json):**
```json
{
  "start_node_id": "trigger-1",
  "nodes": [
    { "id": "trigger-1", "type": "trigger", "position": {...}, "config": {...}, "ui": {...} }
  ],
  "edges": [
    { "id": "edge-1", "source_id": "trigger-1", "target_id": "send-text-1", "branch": "default" }
  ]
}
```

**Output (compiled_json):**
```json
{
  "start_node_id": "trigger-1",
  "nodes": {
    "trigger-1": { "id": "trigger-1", "type": "trigger", "config": {...}, "position": {...} }
  },
  "edges": [...],
  "adjacency": {
    "trigger-1": [
      { "id": "edge-1", "source_id": "trigger-1", "target_id": "send-text-1", "branch": "default" }
    ]
  }
}
```

Key differences:
- `nodes` becomes a **keyed map** (object) by ID for O(1) lookup
- `adjacency` is a **per-source-node list** of outgoing edges
- `ui` data is stripped from nodes (not needed at runtime)
- Invalid edges (referencing non-existent nodes) are filtered out

### 5.5 Runtime Execution Engine

`AutomationFlowRuntimeService` is the heart of the system.

#### Entry Points

1. **`handleInbound(Chat $chat)`** — called when a WhatsApp message arrives
2. **`resumeDelayedRun(AutomationFlowRun $run)`** — called by `ResumeAutomationFlowRunJob`

#### Contact Locking

Every execution acquires a **Redis distributed lock** per `(organization_id, contact_id)`:
- TTL: 10 seconds (configurable via `FLOW_BUILDER_V2_CONTACT_LOCK_TTL_SECONDS`)
- Wait: 3 seconds (configurable via `FLOW_BUILDER_V2_CONTACT_LOCK_WAIT_SECONDS`)
- On timeout: returns `false` (message ignored)
- On any other error: proceeds without lock (graceful degradation)

```
lock key = "automation-flow-runtime:{org_id}:{contact_id}"
```

#### Trigger Matching

For each published flow (ordered by `updated_at DESC`):
- `any_incoming`: matches if message is non-empty OR has interactive selection
- `first_in_conversation`: matches if this is the contact's very first inbound chat (count = 1)
- `keyword_match`: matches if message (lowercased) contains any configured keyword

First matching flow wins.

#### `continueRun` — The Node Execution Loop

```php
while ($currentNodeId && isset($nodeMap[$currentNodeId]) && $steps < $maxSteps) {
    $steps++;
    $node = $nodeMap[$currentNodeId];

    // 1. Policy check — blocked by builder_policy?
    // 2. Inactive node check — skip if config.active === false
    // 3. Customer care window check — block if WhatsApp 24h window closed
    // 4. Execute node based on $node['type']
    // 5. Get $nextNodeId from adjacency edges
    // 6. Update run.current_node_id = $nextNodeId → loop
}
// Fallback: max steps exceeded or invalid node → fail run
```

**Node execution outcomes:**

| Result | What Happens |
|---|---|
| Node executes successfully | `nextNodeId` set → loop continues |
| No next node | Run marked `completed` → exit |
| `send_buttons` first visit | Run marked `waiting_input`, returns |
| `send_list` first visit | Run marked `waiting_input`, returns |
| `delay` first visit | Run marked `waiting_delay`, job dispatched, returns |
| `human_handoff` | Run marked `waiting_handoff`, returns |
| `handoff_to_ai_assistant` | Run marked `waiting_handoff`, returns |
| `end` node | Run marked `completed` → return |
| WhatsApp API failure | Run marked `failed` → return |
| Max 60 steps exceeded | Run marked `failed` with reason |

#### Branch Resolution

`resolveNextNodeId(edges, preferredBranch)`:
1. Find edge with `branch === preferredBranch`
2. Fallback: edge with `branch === 'default'`
3. Fallback: first edge

#### Condition Evaluation

Sources:
- `last_user_message` — text of most recent message
- `selected_button_id` — ID of button pressed
- `selected_list_row_id` — ID of list row selected
- `contact_field` — value from contact metadata field
- `flow_variable` — value from session variables

Operators: `equals`, `not_equals`, `contains`, `filled`, `not_filled`

Result: `'matched'` or `'unmatched'` → next node from that branch

### 5.6 Preview Engine

`AutomationFlowPreviewService::project()` simulates execution **without side effects**:
- Compiles the graph (same compiler as runtime)
- Walks the graph in a loop (but NO contact locks, NO DB writes, NO WhatsApp calls)
- For interactive nodes (buttons, lists), picks the **first option** automatically
- Returns a timeline array of `{ kind, node_id, label, meta }` items

The preview represents the **"happy path"** — it always takes the first available branch.

### 5.7 Asset & Secret Management

**Assets:**
- Stored locally: `storage/app/automation-flows/{org_id}/{flow_uuid}/{filename}`
- Served via signed URLs (TTL: 24h by default) through `showAsset` route
- At runtime, `resolveMediaUrl()` generates a fresh signed URL for the WhatsApp API call
- On duplicate flow: files are physically copied in storage

**Secrets:**
- SMTP credentials are NEVER stored inside `graph_json`
- They live in `automation_flow_node_secrets` table with `payload_json` encrypted by Laravel's encryption
- The frontend receives only `{ type, display_name }` — never actual credentials
- When saving: frontend sends `node_secrets: { nodeId: { host, port, user, pass, ... } }` which is stored encrypted in the secrets table and replaced in `graph_json` with a `secret_ref` (UUID)
- At runtime: `payloadForNode()` decrypts and returns the SMTP config

---

## 6. Database Analysis

### 6.1 Table Reference

| Table | Purpose | Row Lifetime |
|---|---|---|
| `automation_flows` | Flow definition, current status, draft graph | Until soft deleted |
| `automation_flow_versions` | Immutable published snapshots | Until flow is hard-deleted |
| `automation_flow_runs` | One execution per contact trigger | Until flow deleted (cascade) |
| `automation_flow_run_steps` | Node-level execution log | Until run deleted (cascade) |
| `automation_flow_assets` | Uploaded media files | Until explicitly deleted |
| `automation_flow_node_secrets` | Encrypted SMTP credentials | Until node deleted from flow |

### 6.2 Schema Details

#### `automation_flows`

```sql
id                    BIGINT PK
uuid                  CHAR(36) UNIQUE
organization_id       FK → organizations
name                  VARCHAR
description           TEXT nullable
goal_preset           VARCHAR  default:'sales_qualification'
channel               VARCHAR  default:'whatsapp'
trigger_type          VARCHAR  default:'incoming_whatsapp_message'
status                VARCHAR  default:'draft'  -- draft|published|paused
graph_json            JSON nullable    -- nodes[], edges[], start_node_id
ui_json               JSON nullable    -- viewport, selection, layout state
current_version_id    BIGINT nullable → automation_flow_versions.id
last_published_at     TIMESTAMP nullable
has_unpublished_changes BOOLEAN default:true
runs_count            BIGINT unsigned default:0
created_by            FK → users nullable
updated_by            FK → users nullable
deleted_at            TIMESTAMP nullable (soft delete)
```

Indexes:
- `(organization_id, status)` — for listing by status
- `(organization_id, channel)` — for runtime lookup of published flows

#### `automation_flow_versions`

```sql
id                    BIGINT PK
uuid                  CHAR(36) UNIQUE
automation_flow_id    FK → automation_flows CASCADE
organization_id       FK → organizations CASCADE
version_number        INT unsigned        -- incremented on each publish
label                 VARCHAR nullable     -- 'v1', 'v2', ...
graph_json            JSON                -- snapshot of graph at publish time
ui_json               JSON nullable
compiled_json         JSON                -- optimized runtime format
published_by          FK → users nullable
published_at          TIMESTAMP
```

#### `automation_flow_runs`

```sql
id                    BIGINT PK
uuid                  CHAR(36) UNIQUE
automation_flow_id    FK → automation_flows CASCADE
automation_flow_version_id FK → automation_flow_versions CASCADE
organization_id       FK → organizations CASCADE
contact_id            FK → contacts CASCADE
chat_id               FK → chats nullable NULLONDELETE
status                VARCHAR  -- active|waiting_input|waiting_handoff|waiting_delay|completed|cancelled|expired|failed
current_node_id       VARCHAR nullable
waiting_node_id       VARCHAR nullable
waiting_for           VARCHAR nullable  -- 'button'|'list'|'free_text'|'delay'|'human_handoff'|'ai_handoff'
state_json            JSON nullable     -- { context: {...}, runtime: {...}, handoff: {...} }
last_input_json       JSON nullable     -- last inbound context
started_at            TIMESTAMP
completed_at          TIMESTAMP nullable
next_resume_at        TIMESTAMP nullable -- for delay nodes
last_activity_at      TIMESTAMP nullable
```

Indexes:
- `(organization_id, status)` — runtime lookup of active runs
- `(contact_id, status)` — per-contact active run check
- `(next_resume_at, status)` — for querying overdue delays

#### `automation_flow_run_steps`

```sql
id                    BIGINT PK
automation_flow_run_id FK → automation_flow_runs CASCADE
automation_flow_id    FK → automation_flows CASCADE
organization_id       FK → organizations CASCADE
node_id               VARCHAR
node_type             VARCHAR
status                VARCHAR  -- executed|waiting|resumed|skipped|failed|cancelled
input_json            JSON nullable   -- inbound context at execution time
output_json           JSON nullable   -- result of node action
metadata_json         JSON nullable   -- additional context/reason
occurred_at           TIMESTAMP
```

#### `automation_flow_assets`

```sql
id                    BIGINT PK
uuid                  CHAR(36) UNIQUE
automation_flow_id    FK → automation_flows CASCADE
organization_id       FK → organizations CASCADE
media_kind            VARCHAR  -- image|video|audio|document
disk                  VARCHAR  default:'local'
path                  VARCHAR  -- relative storage path
original_name         VARCHAR nullable
mime_type             VARCHAR nullable
size                  BIGINT unsigned
meta_json             JSON nullable   -- { extension: 'jpg', ... }
created_by            FK → users nullable
```

#### `automation_flow_node_secrets`

```sql
id                    BIGINT PK
uuid                  CHAR(36) UNIQUE
automation_flow_id    FK → automation_flows CASCADE
organization_id       FK → organizations CASCADE
node_id               VARCHAR
node_type             VARCHAR  -- e.g. 'send_email'
payload_json          LONGTEXT  -- Laravel-encrypted JSON
UNIQUE (automation_flow_id, node_id, node_type)
```

### 6.3 Data Flow & Relationships

```
automation_flows (1)
    │
    ├── (N) automation_flow_versions   ← one per publish
    │           │
    │           └── compiled_json ──────── used by runtime
    │
    ├── (N) automation_flow_runs       ← one per contact execution
    │           │
    │           └── (N) automation_flow_run_steps  ← one per node
    │
    ├── (N) automation_flow_assets     ← uploaded media
    │
    └── (N) automation_flow_node_secrets ← SMTP credentials
```

**Runtime uses compiled version, not draft:**
- `AutomationFlowRun` always references a specific `AutomationFlowVersion`
- Even if the flow is re-published with changes, existing runs finish against the version they started with
- `flow.currentVersion` points to the latest published version

---

## 7. Node Types Reference

### Trigger Nodes (1 per flow, mandatory)

| Type | Trigger Condition | Config |
|---|---|---|
| `trigger` | Incoming WhatsApp message | `match_mode`, `keywords[]`, `starting_step` |

**Match modes:**
- `any_incoming` — any non-empty message or interactive reply
- `first_in_conversation` — only the contact's first-ever message
- `keyword_match` — message contains any of the configured keywords

### Message Nodes (WhatsApp-native)

| Type | Description | Key Config |
|---|---|---|
| `send_text` | Plain text message | `text` (supports `{first_name}` etc.) |
| `send_media` | Image/video/audio/document | `media_type`, `asset_id`, `caption` |
| `send_buttons` | Interactive quick-reply buttons (max 3) | `body`, `header`, `footer`, `buttons[]{id, title}`, `invalid_reply_behavior` |
| `send_list` | Interactive list picker | `body`, `button_label`, `sections[]{title, rows[]{id, title, description}}`, `invalid_reply_behavior` |

**`invalid_reply_behavior`** (for buttons & lists):
- `release_to_fallback` — run ends, message falls through to normal chat
- `repeat_prompt` — resend the prompt again
- `end_run` — cancel the run

### Action Nodes

| Type | Description | Key Config |
|---|---|---|
| `save_reply_to_field` | Waits for free-text reply, saves to field or variable | `save_target`, `field_uuid`, `variable_key` |
| `condition` | Branches based on a rule | `source`, `operator`, `value`, `field_uuid`, `variable_key` |
| `add_to_group` | Adds contact to a group | `group_uuid` |
| `remove_from_group` | Removes contact from a group | `group_uuid` |
| `update_contact_field` | Updates a contact field | `save_target`, `field_uuid`, `variable_key`, `mode`, `value` |
| `assign_to_agent` | Opens/assigns a support ticket | `assignment_mode`, `agent_user_id`, `reopen_closed_ticket` |
| `human_handoff` | Hands off to human agent, **pauses flow** | `assignment_mode`, `agent_user_id` |
| `handoff_to_ai_assistant` | Hands off to AI, **pauses flow** | — |
| `send_email` | Sends email via SMTP | `subject`, `body`, `secret_ref` |
| `delay` | Waits N minutes | `minutes` (min: 1) |
| `end` | Ends the journey | — |

### Advanced vs. Basic

The plan's `flow_builder_advanced_enabled` flag gates access to all node types **except** `trigger`, `send_text`, and `end`. If `false`, only those 3 types appear in the library and can be published.

---

## 8. Flow Execution Lifecycle

### 8.1 Phase 1: Build

```
1. User opens /automation/flows/create → FlowCreateModal
2. POST /automation/flows → AutomationFlowBuilderService::create()
   → Picks starter template based on goal_preset and org plan capabilities
   → Creates AutomationFlow with status='draft'
   → Returns uuid → redirect to /automation/flows/{uuid}

3. GET /automation/flows/{uuid} → AutomationFlowBuilderService::builderPayload()
   → Loads: flow, library, plan_limits, contact_fields, contact_groups, 
            assignable_agents, assets, node_secrets, initial validation, preview
   → Inertia renders Builder.vue with full props

4. User edits nodes/edges in canvas
   → Node click: select node (activeNodeId changes)
   → Node double-click: open inline editor (focusedNodeId changes)
   → Inspector form edits: updateNode(nodeId, patch) → markDraftDirty()
   → 1.2s debounce → POST /autosave

5. Autosave:
   POST /automation/flows/{uuid}/autosave
   → SaveAutomationFlowRequest validates structure
   → AutomationFlowNodeSecretService::sanitizeGraphAndSyncSecrets()
      → Extracts SMTP credentials from node_secrets
      → Encrypts and stores in automation_flow_node_secrets
      → Replaces in graph with secret_ref UUID
   → AutomationFlow::update(graph_json, ui_json, has_unpublished_changes=true)
```

### 8.2 Phase 2: Publish

```
User clicks Publish
→ Builder.vue::publishFlow()
   1. saveDraft() — ensure latest state is saved
   2. POST /automation/flows/{uuid}/publish
      → AutomationFlowBuilderService::publish()
         a. ensureOwnership()
         b. assertActiveFlowPublishLimit() — check plan quota
         c. ensureGraphRespectsBuilderPolicy() — policy check
         d. AutomationFlowGraphValidator::ensureValid() — full validation
         e. AutomationFlowGraphCompiler::compile() — create compiled_json
         f. AutomationFlowVersion::create(version_number++, compiled_json)
         g. AutomationFlow::update(status='published', current_version_id=version.id)
      → Response: { status: 'ok', message: '...' }
   3. draft.value.status = 'published'
   4. draft.value.has_unpublished_changes = false
```

### 8.3 Phase 3: Runtime Trigger

```
WhatsApp message arrives from contact
→ (assumed) WebhookController creates Chat record, calls:
→ AutomationFlowRuntimeService::handleInbound(Chat $chat)

1. access.availableForOrganization() — feature check
2. withContactLock(org_id, contact_id, callback, 3s wait, 10s TTL)
3. Inside lock:
   a. runtimeSupport.buildInboundContext(chat)
      → { last_user_message, selected_button_id, selected_list_row_id, input_type, ... }
   
   b. Look for EXISTING active run:
      AutomationFlowRun where:
        - organization_id = chat.organization_id
        - contact_id = chat.contact_id
        - status IN (waiting_input, waiting_handoff, active)
      Latest by ID
   
   c. If run found AND stale → expire it (null)
   
   d. If run found:
      → resumeWaitingRun(run, chat) → may return immediately
   
   e. If no run (or resumeWaitingRun returned null):
      → Query published flows for org/channel=whatsapp
      → foreach flow: triggerMatches(flow, chat, context)?
      → First match: startRun(flow, chat)
```

### 8.4 Phase 4: Node Execution Loop

```
continueRun(run, chat):

while (currentNodeId && nodeExists && steps < 60):
    steps++
    node = compiled.nodes[currentNodeId]
    outgoing = compiled.adjacency[currentNodeId] || []
    
    // Pre-execution checks:
    if builderPolicy.blocksNodeType(node.type):
        failRun(reason='builder_policy_blocked')
        return
    
    if node.config.active === false (non-trigger):
        recordStep(status='skipped')
        nextNodeId = outgoing.default
        continue
    
    if whatsappWindowClosed && node sends message:
        if on_window_closed == 'release_to_fallback':
            run.state.runtime.release_to_fallback = true
            cancelRun()
            return
        else:
            failRun(reason='conversation_window_closed')
            return
    
    // Node execution:
    switch node.type:
        'trigger'         → recordStep + advance
        'send_text'       → personalize + whatsapp.sendMessage + advance
        'send_media'      → resolveMediaUrl + whatsapp.sendMedia + advance
        'send_buttons'    → if resuming: resolve branch | else: sendButtons + WAIT
        'send_list'       → if resuming: resolve row  | else: sendList + WAIT
        'save_reply_to_field' → extract last_user_message → save to field/variable + advance
        'condition'       → evaluateCondition() → 'matched' or 'unmatched' branch
        'add_to_group'    → mutations.addToGroup() + advance
        'remove_from_group' → mutations.removeFromGroup() + advance
        'update_contact_field' → persist value + advance
        'assign_to_agent' → handoff.assignToAgent() + advance (flow continues!)
        'human_handoff'   → handoff.startHumanHandoff() + WAIT (flow pauses!)
        'handoff_to_ai_assistant' → handoff.startAiHandoff() + WAIT (flow pauses!)
        'send_email'      → actionDispatch.sendEmail() + advance
        'delay'           → if resuming: advance | else: dispatch Job + WAIT
        'end'             → mark completed + return
        default           → mark failed + return
    
    // Advance:
    if nextNodeId:
        run.current_node_id = nextNodeId
        run.status = 'active'
    else:
        run.status = 'completed'
        return
```

### 8.5 Phase 5: Suspension & Resume

#### `waiting_input` (buttons / lists / free text)

```
Run suspended:
  - status = 'waiting_input'
  - waiting_node_id = currentNodeId
  - waiting_for = 'button' | 'list' | 'free_text'

Next message arrives → handleInbound():
  → Find existing run → resumeWaitingRun()
  
  For 'button':
    - chat has selected_button_id?
      YES → update run.status=active, update context → continueRun()
             (send_buttons case detects waiting_node_id match → resolve branch)
      NO  → invalid_reply_behavior:
            - 'repeat_prompt' → resend buttons
            - 'release_to_fallback' → return false (message goes to normal chat)
            - 'end_run' → cancel run
  
  For 'list': same as buttons but with selected_list_row_id
  
  For 'free_text':
    - update run.last_input_json with new message
    - update run.status = active
    - continueRun() → save_reply_to_field picks up last_user_message
```

#### `waiting_delay` (delay node)

```
Run suspended:
  - status = 'waiting_delay'
  - waiting_node_id = 'delay-X'
  - waiting_for = 'delay'
  - next_resume_at = now + N minutes

ResumeAutomationFlowRunJob dispatched with delay(N minutes)

Job fires:
  → AutomationFlowRuntimeService::resumeDelayedRun(run)
  → withContactLock()
  → fresh run from DB
  → if status != 'waiting_delay': abort (may have been cancelled/expired)
  → run.update(status='active', next_resume_at=null)
     *** waiting_node_id and waiting_for are LEFT as-is (not cleared) ***
  → continueRun(fresh_run, chat=null)
     → reaches 'delay' node
     → checks: waiting_node_id === currentNodeId && waiting_for === 'delay'
     → YES → recordStep(status='executed') + clear waiting fields + advance
```

#### `waiting_handoff`

```
Run suspended:
  - status = 'waiting_handoff'
  - waiting_for = 'human_handoff' | 'ai_handoff'

Next message arrives → resumeWaitingRun():
  → if waiting_handoff: runtimeSupport.resumeHandoffRun()
  → Handoff service checks if handoff is still active
  → If resolved: continue flow | If still active: ignore/return
```

---

## 9. Environment & Configuration

### Required Environment Variables

```dotenv
# Core feature toggle (defaults to true if missing)
FLOW_BUILDER_V2_ENABLED=true

# Node/edge limits
FLOW_BUILDER_V2_MAX_NODES=80
FLOW_BUILDER_V2_MAX_EDGES=160

# Autosave debounce (milliseconds)
FLOW_BUILDER_V2_AUTOSAVE_DEBOUNCE_MS=1200

# Runtime execution
FLOW_BUILDER_V2_MAX_EXECUTION_STEPS=60
FLOW_BUILDER_V2_RESUME_QUEUE=automation-flow-resume

# Asset serving
FLOW_BUILDER_V2_ASSET_URL_TTL_MINUTES=1440

# Contact locking (Redis)
FLOW_BUILDER_V2_CONTACT_LOCK_TTL_SECONDS=10
FLOW_BUILDER_V2_CONTACT_LOCK_WAIT_SECONDS=3

# Stale run timeouts
FLOW_BUILDER_V2_ACTIVE_RUN_STALE_MINUTES=30
FLOW_BUILDER_V2_WAITING_INPUT_STALE_MINUTES=1440
FLOW_BUILDER_V2_WAITING_HANDOFF_STALE_MINUTES=10080

# Invalid reply behavior
FLOW_BUILDER_V2_INVALID_REPLY_DEFAULT_BEHAVIOR=release_to_fallback

# WhatsApp window enforcement
FLOW_BUILDER_V2_CUSTOMER_CARE_WINDOW_HOURS=24
FLOW_BUILDER_V2_ENFORCE_CUSTOMER_CARE_WINDOW=true
FLOW_BUILDER_V2_ON_WINDOW_CLOSED=fail_run   # or: release_to_fallback

# Builder policy
FLOW_BUILDER_V2_CHANNEL=whatsapp
FLOW_BUILDER_V2_WHATSAPP_ONLY_MODE=true
FLOW_BUILDER_V2_ALLOW_EXTERNAL_ACTIONS=false
FLOW_BUILDER_V2_ALLOW_CRM_ACTIONS=true
```

### Required Services

| Service | Why Needed | Notes |
|---|---|---|
| **MySQL** | All flow data, runs, steps | All 6 tables must exist (run migrations) |
| **Redis** | Contact locking during execution | Must be configured as cache driver |
| **Queue Worker** | Delay node resume jobs | Must run `automation-flow-resume` queue |
| **Storage (local disk)** | Media asset storage | `storage/app/` must be writable |
| **WhatsApp API** | Sending messages at runtime | Configured per organization |

### Database Prerequisites

Run these migrations:
```bash
php artisan migrate
# Applies:
# 2026_03_13_010000_create_automation_flow_tables.php
# 2026_03_13_030000_create_automation_flow_assets_and_node_secrets_tables.php
```

### Addon & Plan Setup

The feature requires **3 database records** beyond migrations:

**1. Addon enabled globally:**
```sql
UPDATE addons SET status = 1, is_active = 1 WHERE name = 'Flow builder';
```

**2. Active subscription for organization:**
```sql
INSERT INTO subscriptions (uuid, organization_id, plan_id, status, start_date, valid_until, ...)
VALUES (..., 'active', NOW(), DATE_ADD(NOW(), INTERVAL 1 YEAR), ...);
```

**3. Plan metadata includes Flow builder addon:**
```sql
-- In subscription_plans.metadata (JSON):
{
  "addons": { "Flow builder": true },
  "flow_builder_active_flows_limit": -1,   -- -1 = unlimited
  "flow_builder_nodes_per_flow_limit": -1,
  "flow_builder_monthly_runs_limit": -1,
  "flow_builder_advanced_enabled": 1
}
```

---

## 10. Access Control & Feature Flags

### Access Chain (`AutomationFlowAccessService`)

```
Feature available?
    │
    ├─ FLOW_BUILDER_V2_ENABLED = true?           (env/config)
    │       NO → redirect to /automation/basic
    │
    ├─ addons.status = 1 AND addons.is_active = 1?   (addons table)
    │       NO → 403 or redirect
    │
    ├─ organization has active subscription?          (subscriptions table)
    │       NO → feature locked (limits return 0)
    │
    ├─ subscription plan has "Flow builder": true?    (plan metadata.addons)
    │       NO → feature locked
    │
    ├─ automation_flows table exists?                 (base schema)
    │       NO → 503 or redirect
    │
    └─ automation_flow_assets table exists?           (builder schema)
            NO → builder features disabled (no assets, no secrets)
```

### Permissions Required

| Action | Permission Key |
|---|---|
| View flows list | `automations.flows.view` |
| Open flow builder | `automations.flows.view` |
| Create / Duplicate flow | `automations.flows.add` |
| Save draft / Upload asset | `automations.flows.edit` |
| Publish / Pause | `automations.flows.publish` |
| Delete flow | `automations.flows.delete` |

---

## 11. Missing Parts, Bugs & Technical Debt

### ✅ Fixed Bugs (Applied During This Analysis)

#### BUG-001: Delay Node Infinite Loop *(CRITICAL — FIXED)*

**File**: `app/Services/AutomationFlows/AutomationFlowRuntimeService.php`

**Problem**: `resumeDelayedRun()` cleared `waiting_node_id` and `waiting_for` before calling `continueRun()`. When `continueRun` reached the delay node again with `waiting_node_id = null`, the delay case had no check to detect it was a resumption — it would schedule another delay and dispatch another job, looping forever.

**Fix Applied**:
1. `resumeDelayedRun()`: No longer clears `waiting_node_id`/`waiting_for` before calling `continueRun`.
2. `continueRun()` `delay` case: Added resumption check identical to `send_buttons`:
   ```php
   if ($run->waiting_node_id === $currentNodeId && $run->waiting_for === 'delay') {
       $this->recordStep($run, $currentNodeId, 'executed', $context, ['reason' => 'delay_completed']);
       $run->update(['waiting_node_id' => null, 'waiting_for' => null]);
       $nextNodeId = $this->resolveNextNodeId($outgoing, 'default');
       break;
   }
   ```

---

### ⚠️ Existing Technical Debt & Issues

#### ISSUE-002: `limitForOrganization` Returns 0 Without Subscription *(Design Choice — Potential Trap)*

**File**: `app/Services/SubscriptionPlanLimitService.php:107`

**Problem**: When no active subscription exists, `limitForOrganization()` returns `0` (not the `$default` parameter). For `flow_builder_nodes_per_flow_limit`, a limit of `0` would block ALL publishing (0 nodes allowed). This is a deliberate design choice but has no visible error message to the user — the publish action would just fail with a generic "0 nodes per flow" error.

**Mitigation**: Ensure every org has an active subscription before using Flow Builder.

---

#### ISSUE-003: No Retry Mechanism for Failed WhatsApp Sends *(Reliability Gap)*

**File**: `app/Services/AutomationFlows/AutomationFlowRuntimeService.php`

**Problem**: When any WhatsApp API call fails (network issue, rate limit, etc.), the run is immediately marked `failed` with no retry. There is no exponential backoff or retry queue.

**Impact**: A transient WhatsApp API error permanently fails the user's flow run.

**Suggestion**: Wrap WhatsApp sends in a retriable job or add retry logic with delay.

---

#### ISSUE-004: `isFirstInboundMessage` Race Condition *(Minor)*

**File**: `AutomationFlowRuntimeService.php:752`

**Problem**: `isFirstInboundMessage()` counts `inbound` chats for the contact. If called concurrently for the same contact, both calls might see count=1 and both start a run. The contact lock mitigates this for flows, but the count itself is not transactionally safe if the chat creation and the lock acquisition are not atomic.

**Impact**: Rare but theoretically possible double-trigger for `first_in_conversation` flows.

---

#### ISSUE-005: `send_media` Media URL Duplicated in Runtime Call *(Minor Code Quality)*

**File**: `AutomationFlowRuntimeService.php:403-410`

```php
$response = $this->whatsapp($contact->organization_id)->sendMedia(
    $contact->uuid,
    $mediaType,
    $this->runtimeSupport->resolveMediaName(...),
    $mediaUrl,   // param 4 — url
    $mediaUrl,   // param 5 — same url again (should be media_id?)
    'remote',
    $caption
);
```

The same `$mediaUrl` is passed twice. This likely works but suggests the `sendMedia` method signature has a `media_id` param that is unused here. Worth investigating the WhatsApp service's `sendMedia` signature.

---

#### ISSUE-006: Preview Always Takes "Happy Path" *(UX Limitation)*

**File**: `AutomationFlowPreviewService.php`

**Problem**: The preview always picks the first button/list option and always takes the `matched` branch on conditions. Users cannot preview alternative paths.

**Impact**: Users designing complex flows with multiple branches cannot preview all paths.

**Suggestion**: Add scenario selection in the preview UI with "simulate different input".

---

#### ISSUE-007: No Soft Delete Cascade for Assets *(Data Leak)*

**File**: `AutomationFlowAssetService.php`

**Problem**: When `AutomationFlow` is soft-deleted (`deleted_at` set), the associated `automation_flow_assets` records remain in the DB and the physical files remain in storage. They are not cleaned up until the flow is hard-deleted (cascade) or manually deleted.

**Impact**: Orphaned files accumulate in storage if flows are soft-deleted without a cleanup job.

---

#### ISSUE-008: `window.prompt()` Used for Node Rename *(UX Issue)*

**File**: `Builder.vue:820`

```javascript
const nextTitle = window.prompt(t('Rename node'), currentTitle);
```

Uses native browser `window.prompt()` which is blocking, unstyled, and not i18n-friendly. Should be replaced with a proper modal.

---

#### ISSUE-009: No Pagination for Run Steps *(Potential Performance)*

**File**: `app/Models/AutomationFlowRun.php`

The `hasMany(AutomationFlowRunStep)` relation is never paginated. A long-running flow at scale (60 steps × many concurrent runs) could load thousands of step records if queried without limits.

---

#### ISSUE-010: Contact Lock Falls Back to Unlocked Execution *(Safety Concern)*

**File**: `AutomationFlowRuntimeService.php:747`

```php
} catch (\Throwable $exception) {
    report($exception);
    return $callback();   // ← executes without lock!
}
```

If Redis throws an unexpected error (not `LockTimeoutException`), the code falls back to running WITHOUT the lock. This means two concurrent messages could both execute the flow for the same contact simultaneously.

**Suggestion**: Remove the fallback or make it configurable. Fail safely instead.

---

## 12. Local Setup Guide

### Prerequisites

- PHP 8.2+, Composer
- Node.js 18+, npm
- MySQL 8.0+
- Redis (for contact locking and queue)

### Step-by-Step Setup

```bash
# 1. Install PHP dependencies
composer install

# 2. Install JS dependencies
npm install

# 3. Copy environment file
cp .env.example .env

# 4. Configure .env (minimum required):
```

```dotenv
APP_KEY=        # php artisan key:generate
DB_DATABASE=botzo_sa
DB_USERNAME=root
DB_PASSWORD=your_password

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Flow Builder (defaults are fine for local dev):
FLOW_BUILDER_V2_ENABLED=true
```

```bash
# 5. Generate app key
php artisan key:generate

# 6. Run ALL migrations
php artisan migrate

# Verify these tables exist:
# automation_flows, automation_flow_versions
# automation_flow_runs, automation_flow_run_steps
# automation_flow_assets, automation_flow_node_secrets

# 7. Enable Flow Builder addon in database
php artisan tinker --execute="DB::table('addons')->where('name', 'Flow builder')->update(['status' => 1, 'is_active' => 1]);"

# 8. Create an active subscription for organization
php artisan tinker --execute="
\$plan = DB::table('subscription_plans')->first();
\$orgId = DB::table('organizations')->first()->id;
DB::table('subscriptions')->insert([
    'uuid' => \Illuminate\Support\Str::uuid(),
    'organization_id' => \$orgId,
    'plan_id' => \$plan->id,
    'status' => 'active',
    'start_date' => now(),
    'valid_until' => now()->addYears(1),
    'created_at' => now(),
    'updated_at' => now(),
]);
echo 'Subscription created';
"

# 9. Enable Flow Builder in plan metadata
php artisan tinker --execute="
\$plan = DB::table('subscription_plans')->first();
\$meta = json_decode(\$plan->metadata, true);
\$meta['addons'] = ['Flow builder' => true];
\$meta['flow_builder_active_flows_limit'] = -1;
\$meta['flow_builder_nodes_per_flow_limit'] = -1;
\$meta['flow_builder_monthly_runs_limit'] = -1;
\$meta['flow_builder_advanced_enabled'] = 1;
DB::table('subscription_plans')->where('id', \$plan->id)->update(['metadata' => json_encode(\$meta)]);
echo 'Plan updated';
"

# 10. Verify everything is ready
php artisan tinker --execute="
\$access = app(\App\Services\AutomationFlows\AutomationFlowAccessService::class);
\$report = \$access->readinessReport(1);
foreach(\$report as \$k => \$v) echo \$k.' => '.json_encode(\$v).PHP_EOL;
"
# Expected: builder_ready => true, addon_enabled => true

# 11. Build frontend assets
npm run dev     # development (with HMR)
# OR
npm run build   # production build

# 12. Start the web server
php artisan serve

# 13. IMPORTANT: Start the queue worker for delay nodes
php artisan queue:work --queue=automation-flow-resume,default

# 14. Create storage symlink (for serving assets if using public disk)
php artisan storage:link
```

### Verify Feature Works

```
http://localhost:8000/automation/flows
```

Should show the flows list page without redirecting to `/automation/basic`.

---

## 13. Production Readiness Review

### Scalability

| Aspect | Status | Notes |
|---|---|---|
| Database indexes | ✅ Good | All runtime lookup columns are indexed |
| Contact locking | ✅ Good | Redis-based distributed lock prevents concurrent runs |
| Queue isolation | ✅ Good | Dedicated `automation-flow-resume` queue |
| Node limit | ✅ Good | Max 80 nodes, 160 edges, 60 execution steps |
| Stale run cleanup | ✅ Good | Configurable TTLs for all waiting states |
| N+1 queries | ⚠️ Risk | `builderPayload()` does several single queries for fields/groups/agents |

### Security

| Aspect | Status | Notes |
|---|---|---|
| SMTP credentials | ✅ Encrypted | `payload_json` encrypted via `Crypt::encrypt` |
| Asset serving | ✅ Signed | Temporary signed URLs with 24h TTL |
| Ownership check | ✅ Present | `ensureOwnership()` in every builder operation |
| Permission gates | ✅ Present | Granular `checkPermission()` calls |
| Input validation | ✅ Present | FormRequest on all write endpoints |
| Graph injection | ⚠️ Partial | `graph_json` nodes/edges are validated for structure but free-text fields (message text) are not sanitized for XSS (WhatsApp messages don't render HTML, so risk is low) |

### Observability

| Aspect | Status | Notes |
|---|---|---|
| Execution logging | ✅ Good | Every node creates a `run_step` with input/output |
| Error tracking | ✅ Good | Failed runs have detailed `metadata_json` with reason |
| Run status | ✅ Good | Full lifecycle: active → waiting → completed/failed/cancelled |
| No metrics | ❌ Missing | No Prometheus/StatsD metrics emitted |
| No alerting | ❌ Missing | No alerts on high failure rates or queue backlog |

### Fault Tolerance

| Scenario | Handling |
|---|---|
| WhatsApp API down | Run fails (no retry) |
| Redis down | Lock bypassed (unsafe fallback) — see ISSUE-010 |
| Queue worker down | Delayed runs pile up in queue, execute when worker restarts |
| DB connection drop | Standard Laravel exception handling |
| Flow unpublished during run | `resumeDelayedRun` checks `flow.status === 'published'` before resuming |
| Contact deleted during run | `Contact::find()` returns null → run fails gracefully |

---

## 14. Developer Notes

### The 5 Most Important Files

1. **`AutomationFlowRuntimeService.php`** — the execution engine. Touch with extreme care. Every change here affects live customer conversations.

2. **`Builder.vue`** — 1446-line orchestrator. The `rebuildCanvas()` / `syncCanvasPresentation()` / `graphFromCanvas()` trinity controls all state. Understanding this is key to any frontend change.

3. **`AutomationFlowGraphValidator.php`** — the gatekeeper before publish. Any new node type must be added here.

4. **`AutomationFlowGraphCompiler.php`** — the format bridge between builder and runtime. The `adjacency` data structure is critical for performance.

5. **`AutomationFlowNodeCatalog.php`** — single source of truth for all node types. Adding a new node type starts here.

### When Adding a New Node Type

1. Add to `AutomationFlowNodeCatalog::DEFINITIONS` and `TYPES`
2. Add validation in `AutomationFlowGraphValidator::validateNodeConfig()`
3. Add execution in `AutomationFlowRuntimeService::continueRun()` switch statement
4. Add preview item in `AutomationFlowPreviewService::previewItemsForNode()`
5. Add default config in `flowBuilderDraft.js::defaultNodeConfig()`
6. Add inspector form in `FlowNodeInspectorRenderer.vue`
7. Add client validation in `flowBuilderValidation.js::buildNodeErrors()`
8. Add to `AutomationFlowBuilderPolicyService` if it needs policy gating
9. Decide if it's `ADVANCED_TYPES` in the catalog

### Sensitive Files

- `AutomationFlowNodeSecretService.php` — handles encryption/decryption. Never log `payload_json`.
- `AutomationFlowActionDispatchService.php` — sends real emails. Be careful in staging.
- `AutomationFlowRuntimeService.php` — runs against real customer data and real WhatsApp API.

### Common Gotchas

1. **Delay node** was broken (infinite loop) — now fixed. If you ever refactor `resumeDelayedRun`, ensure you preserve the `waiting_node_id`/`waiting_for` pattern.

2. **`graph_json` vs `compiled_json`**: The builder stores `graph_json` (array of nodes, array of edges). The runtime reads `compiled_json` (nodes as keyed map + adjacency list). Never confuse the two.

3. **`node_secrets` are never in `graph_json`**: The frontend sends them separately. `sanitizeGraphAndSyncSecrets()` handles the DB sync. If you bypass this, credentials will be stored in plaintext.

4. **Assets are on `local` disk**: Not publicly accessible by default. They're served via the signed route `flowbuilder.assets.show`. In production, consider moving to S3.

5. **Contact lock is not truly atomic**: See ISSUE-010. The `catch (\Throwable)` fallback runs without the lock.

6. **Max 60 steps**: A deeply nested flow will fail if it exceeds 60 node executions. This includes loops that could happen via condition-based cycles (though the validator prevents circular paths, the 60-step limit is a safety net).

7. **`first_in_conversation` trigger**: Checks `count === 1` at the moment of execution. If the webhook fires twice rapidly for the same message, both checks see count=1. The contact lock helps but isn't 100% safe here.

8. **WhatsApp customer care window**: `send_text`, `send_buttons`, `send_media`, `send_list` can only be sent within 24h of the last customer message. The runtime enforces this. During preview, the window is always treated as open (configurable via `FLOW_BUILDER_V2_PREVIEW_CUSTOMER_CARE_WINDOW_OPEN`).

---

*Generated from source code analysis — 2026-05-31*
*All code snippets reflect the actual implementation.*
