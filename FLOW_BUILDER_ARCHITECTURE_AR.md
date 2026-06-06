# Flow Builder — مرجع المعمارية

> Diagrams ومرجع هيكل البيانات بشكل مختصر.
> للشرح الكامل، شوف [FLOW_BUILDER_FULL_DOCUMENTATION_AR.md](./FLOW_BUILDER_FULL_DOCUMENTATION_AR.md)

---

## معمارية النظام

```
┌──────────────────────────────────────────────────────────────────────┐
│  المتصفح (Browser)                                                   │
│                                                                      │
│  ┌─────────────────────────────────────────────────────────────┐    │
│  │  Builder.vue (1446 سطر — المنسّق الرئيسي)                   │    │
│  │                                                             │    │
│  │  الشريط الأيسر    الـ Canvas الأوسط    اليمين (مدمج)       │    │
│  │  ┌──────────┐    ┌────────────────┐   ┌─────────────┐      │    │
│  │  │ مكتبة   │    │   VueFlow      │   │  Inspector  │      │    │
│  │  │ الخطوات  │سحب▶│  FlowCanvas   │   │  (مدمج في  │      │    │
│  │  │          │    │  Node (×N)    │   │   الكارد)   │      │    │
│  │  │ Messages │    │  FlowCanvas   │   │             │      │    │
│  │  │ Actions  │    │  Edge (×N)    │   │ فورم حسب   │      │    │
│  │  └──────────┘    └────────────────┘   │  نوع Node   │      │    │
│  │                                       └─────────────┘      │    │
│  │  فوق: FlowBuilderHeaderCard (اسم، حالة، نشر، قائمة)        │    │
│  │  معلومات: FlowReadinessPanel (أخطاء، تحذيرات)              │    │
│  └───────────────────────┬─────────────────────────────────────┘    │
│                          │  Axios HTTP + Inertia.js                 │
└──────────────────────────┼───────────────────────────────────────────┘
                           │
┌──────────────────────────▼───────────────────────────────────────────┐
│  Laravel Web Server                                                  │
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
│  AutomationFlowBuilderService (التنسيق)                             │
│  ├── AutomationFlowGraphValidator   (تحقق كامل)                     │
│  ├── AutomationFlowGraphCompiler    (graph → compiled_json)          │
│  ├── AutomationFlowPreviewService   (محاكاة Timeline)                │
│  ├── AutomationFlowNodeCatalog      (الـ 16 نوع Node)               │
│  ├── AutomationFlowStarterTemplateService (4 قوالب جاهزة)           │
│  ├── AutomationFlowAssetService     (ملفات الميديا)                  │
│  ├── AutomationFlowNodeSecretService (تشفير SMTP)                    │
│  └── AutomationFlowBuilderPolicyService (تطبيق السياسة)             │
└──────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────┐
│  الـ Runtime (بيتّشغل من Webhook واتساب)                            │
│                                                                      │
│  AutomationFlowRuntimeService                                        │
│  ├── withContactLock() ──────── Redis lock                          │
│  ├── handleInboundUnlocked()                                         │
│  │   ├── لقيت Run نشط → resumeWaitingRun()                         │
│  │   └── طابق الـ Trigger → startRun() → continueRun()             │
│  ├── continueRun() ─────────── حلقة تنفيذ الـ Nodes (أقصاه 60)    │
│  │   ├── send_text    → WhatsApp API                                 │
│  │   ├── send_buttons → WhatsApp API → waiting_input                │
│  │   ├── send_list    → WhatsApp API → waiting_input                │
│  │   ├── delay        → Queue Job    → waiting_delay                │
│  │   ├── condition    → تقييم       → فرع                          │
│  │   ├── human_handoff → handoff service → waiting_handoff          │
│  │   └── end           → completed                                   │
│  └── resumeDelayedRun() ── بيتستدعى من ResumeAutomationFlowRunJob  │
│                                                                      │
│  AutomationFlowRuntimeSupportService (مساعدات الـ Runtime)         │
│  AutomationFlowPersonalizationService (استبدال {first_name})        │
│  AutomationFlowContactMutationService (تعديلات المجموعة/الحقل)     │
│  AutomationFlowActionDispatchService  (إرسال الإيميل)              │
│  AutomationFlowConversationHandoffService (تعيين الـ Agent)         │
└──────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────┐
│  طبقة البيانات                                                       │
│                                                                      │
│  MySQL:                                                              │
│  automation_flows ──────────── automation_flow_versions              │
│       │                            (compiled_json)                  │
│       └── automation_flow_runs ─── automation_flow_run_steps         │
│       └── automation_flow_assets                                     │
│       └── automation_flow_node_secrets (مشفّرة)                     │
│                                                                      │
│  Redis:  automation-flow-runtime:{org}:{contact}  (قفل Contact)    │
│  Queue:  automation-flow-resume  (وظايف استئناف التأخير)            │
└──────────────────────────────────────────────────────────────────────┘
```

---

## هياكل بيانات الـ Graph

### graph_json (صيغة الـ Builder / التخزين)

محفوظة في `automation_flows.graph_json` و`automation_flow_versions.graph_json`.

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
        "keywords": ["مرحبا", "أهلا"],
        "starting_step": "send-text-1"
      },
      "ui": { "expanded": true, "title": null }
    },
    {
      "id": "send-text-1",
      "type": "send_text",
      "position": { "x": 420, "y": 120 },
      "config": { "text": "أهلاً {first_name}! إزيك؟" },
      "ui": { "expanded": false, "title": "رسالة الترحيب" }
    },
    {
      "id": "buttons-1",
      "type": "send_buttons",
      "position": { "x": 760, "y": 120 },
      "config": {
        "body": "محتاج إيه؟",
        "header": "القائمة",
        "footer": "اختار خيار",
        "buttons": [
          { "id": "pricing", "title": "الأسعار" },
          { "id": "demo", "title": "دِيمو" },
          { "id": "help", "title": "مساعدة" }
        ],
        "invalid_reply_behavior": "repeat_prompt"
      },
      "ui": {}
    },
    { "id": "end-1", "type": "end", "position": { "x": 1100, "y": 120 }, "config": {}, "ui": {} }
  ],
  "edges": [
    { "id": "e1", "source_id": "trigger-1",   "target_id": "send-text-1", "branch": "default"  },
    { "id": "e2", "source_id": "send-text-1", "target_id": "buttons-1",   "branch": "default"  },
    { "id": "e3", "source_id": "buttons-1",   "target_id": "end-1",       "branch": "pricing"  },
    { "id": "e4", "source_id": "buttons-1",   "target_id": "end-1",       "branch": "demo"     },
    { "id": "e5", "source_id": "buttons-1",   "target_id": "end-1",       "branch": "help"     }
  ]
}
```

### compiled_json (صيغة الـ Runtime)

محفوظة في `automation_flow_versions.compiled_json`. **مش بتتعدّل يدوياً أبداً.**

```json
{
  "start_node_id": "trigger-1",
  "nodes": {
    "trigger-1":   { "id": "trigger-1",   "type": "trigger",      "config": {...} },
    "send-text-1": { "id": "send-text-1", "type": "send_text",     "config": {...} },
    "buttons-1":   { "id": "buttons-1",   "type": "send_buttons",  "config": {...} },
    "end-1":       { "id": "end-1",       "type": "end",           "config": {}    }
  },
  "edges": [...],
  "adjacency": {
    "trigger-1":   [{ "source_id": "trigger-1",   "target_id": "send-text-1", "branch": "default"  }],
    "send-text-1": [{ "source_id": "send-text-1", "target_id": "buttons-1",   "branch": "default"  }],
    "buttons-1":   [
      { "source_id": "buttons-1", "target_id": "end-1", "branch": "pricing" },
      { "source_id": "buttons-1", "target_id": "end-1", "branch": "demo"    },
      { "source_id": "buttons-1", "target_id": "end-1", "branch": "help"    }
    ]
  }
}
```

### state_json (حالة الـ Run في الـ Runtime)

محفوظة في `automation_flow_runs.state_json`. بتتحدّث أثناء التنفيذ.

```json
{
  "context": {
    "last_user_message": "مرحبا",
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
    "preferred_slot": "صباح",
    "seller_property_type": "شقة"
  }
}
```

---

## State Machine دورة حياة الـ Run

```
                     ┌─────────────────────────┐
                     │        [البداية]         │
                     │   رسالة واصلت            │
                     └────────────┬────────────┘
                                  │ startRun()
                                  ▼
                            ┌──────────┐
                     ┌──────│  active  │──────┐
                     │      └──────────┘      │
                     │           │            │
              send_buttons    delay node    end node
              send_list       بيوزّع Job   وصلت
              استنّى رد
                     │           │            │
                     ▼           ▼            ▼
              ┌─────────────┐ ┌────────────┐ ┌───────────┐
              │waiting_input│ │waiting_    │ │ completed │ ← نهائي
              └──────┬──────┘ │delay       │ └───────────┘
                     │        └─────┬──────┘
              المستخدم         الـ Job
              رد صح            اشتغل
                     │              │
                     └──────┬───────┘
                            │ continueRun()
                            ▼
                      ┌──────────┐
                      │  active  │ ─── (loop يكمّل)
                      └──────────┘

  human_handoff / AI handoff:
                      ┌──────────────────┐
                      │ waiting_handoff  │
                      └────────┬─────────┘
                               │ الـ Handoff اتحلّ
                               ▼
                         ┌──────────┐
                         │  active  │  أو  completed

  في أي وقت، الأخطاء بتودّي لـ:
              ┌──────────┐    ┌────────────┐    ┌─────────┐
              │  failed  │    │ cancelled  │    │ expired │ ← كلهم نهائيين
              └──────────┘    └────────────┘    └─────────┘
```

---

## شجرة اعتمادية الـ Frontend Components

```
Builder.vue
├── يستورد flowBuilderStudio.js
│       └── يستخدم: buildStartingStepOptions، toCanvasEdge، toGraphEdge،
│                   insertNodeOnCanvasEdge، nextBranchForNode، composeFlowBuilderUiJson
├── يستورد flowBuilderDraft.js
│       └── يستخدم: cloneFlowValue، buildFlowEdge، defaultNodeConfig، makeFlowBuilderUuid
├── يستورد flowBuilderGraph.js
│       └── يستخدم: normalizeTriggerStart، pruneOutgoingBranches
├── يستورد flowBuilderValidation.js
│       └── يستخدم: buildNodeErrors، buildValidationSummary، saveStateLabelFor
├── يستورد flowBuilderMeta.js
│       └── يستخدم: flowNodeLabel، flowNodeIcon، flowNodeCategory
├── يستورد flowBuilderDanger.js
│       └── يستخدم: beginNodeDelete، applyNodeDelete، createLeaveGuard
├── يستورد flowCanvasRuntime.js
│       └── يستخدم: provideFlowCanvasRuntime، buildCanvasEdgeId
├── يستورد useFlowCanvasSurfaceDrag.js  (Composable)
│
├── يرندر FlowBuilderHeaderCard
├── يرندر FlowReadinessPanel
├── يرندر VueFlow
│       ├── يرندر FlowCanvasNode (نوع Node مخصص)
│       │       ├── FlowCanvasNodeHeader
│       │       ├── FlowNodeInspectorRenderer (لو مفتوح)
│       │       │       ├── FlowNodeInspectorTriggerForm
│       │       │       ├── FlowNodeInspectorTextMediaForm
│       │       │       ├── FlowNodeInspectorInteractiveForm
│       │       │       ├── FlowNodeInspectorContactActionForm
│       │       │       ├── FlowNodeInspectorHandoffForm
│       │       │       ├── FlowNodeInspectorExternalActionForm
│       │       │       └── FlowNodeInspectorConditionForm
│       │       ├── FlowCanvasNodeMenu
│       │       └── FlowCanvasNodeRoutingHealth
│       └── يرندر FlowCanvasEdge (نوع Edge مخصص)
│
├── يرندر FlowMetaEditModal
├── يرندر FlowPreviewModal
├── يرندر FlowExitConfirmModal
└── يرندر FlowBuilderDangerModals
```

---

## شجرة قرار التحكم في الوصول

```
طلب جاي للـ AutomationFlowController
                │
                ▼
    FLOW_BUILDER_V2_ENABLED = true؟
         لأ ─────── 404 / Redirect لـ /automation/basic
         │
         أيوه
         ▼
    addons.name='Flow builder'
    AND status=1 AND is_active=1؟
         لأ ─────── 403 (الميزة مش مفعّلة)
         │
         أيوه
         ▼
    الـ Org عندها اشتراك نشط؟
    (جدول subscriptions، valid_until > now)
         لأ ─────── الـ Limits بترجع 0 (محجوب فعلياً)
         │
         أيوه
         ▼
    plan.metadata.addons['Flow builder'] = true؟
         لأ ─────── 403
         │
         أيوه
         ▼
    جدول automation_flows موجود؟  (base_schema_ready)
         لأ ─────── 503 (شغّل الـ Migrations)
         │
         أيوه
         ▼
    جدول automation_flow_assets موجود؟  (builder_schema_ready)
         لأ ─────── ميزات الـ Assets/Secrets معطّلة
         │           (قائمة الـ Flows لسه بتشتغل)
         أيوه
         ▼
    الـ Org عندها Permission للعملية؟
    (automations.flows.view/add/edit/publish/delete)
         لأ ─────── 403 ممنوع
         │
         أيوه
         ▼
         ✅ كمّل
```

---

## مرجع سريع لأنواع الـ Nodes

```
طبقة الـ TRIGGER (واحدة لكل Flow — إلزامية)
─────────────────────────────────────────
trigger   any_incoming | first_in_conversation | keyword_match

طبقة الـ MESSAGES (واتساب Native — محتاجة نافذة Customer Care)
─────────────────────────────────────────
send_text     نص عادي (بيدعم {first_name} وغيرها)
send_media    صورة/فيديو/صوت/مستند عبر Signed URL
send_buttons  أزرار Quick Reply تفاعلية (أقصاه 3) → فروع حسب button.id
send_list     قائمة تفاعلية (أقصاه 10 صفوف/قسم) → فروع حسب row.id

طبقة الـ ACTIONS (مش محتاجة نافذة Customer Care)
─────────────────────────────────────────
save_reply_to_field   استنى نص حر → احفظ في contact_field أو session_variable
condition             قيّم قاعدة → فرع 'matched' أو 'unmatched'
add_to_group          أضف الـ Contact لمجموعة
remove_from_group     اشيل الـ Contact من مجموعة
update_contact_field  اضبط حقل الـ Contact بقيمة static/last_input/session_variable
assign_to_agent       افتح/عيّن Ticket (الـ Flow **بيكمّل** بعدها)
human_handoff         عيّن + **وقّف** الـ Flow (waiting_handoff)
handoff_to_ai_assist  AI يتولّى + **وقّف** الـ Flow
send_email            إيميل SMTP عبر Node Secret مشفّر
delay                 استنّى N دقيقة (ResumeAutomationFlowRunJob)
end                   علّم الـ Run completed

الأنواع المتقدمة (محتاجة flow_builder_advanced_enabled=true في الخطة):
send_media، send_buttons، send_list، save_reply_to_field، condition،
add_to_group، remove_from_group، update_contact_field، send_email،
assign_to_agent، human_handoff، handoff_to_ai_assistant، delay
```

---

## معمارية الـ Queue

```
Queue: automation-flow-resume
    │
    └── ResumeAutomationFlowRunJob
            └── بيتوزّع من:  delay node في continueRun()
            └── الـ Payload:  run.id (integer)
            └── التأخير:     N دقيقة (من إعداد الـ Node)
            └── المعالج:     AutomationFlowRuntimeService::resumeDelayedRun()

تشغيل الـ Worker:
  php artisan queue:work --queue=automation-flow-resume,default

للـ Production (يُفضَّل Supervisor):
  [program:flow-resume-worker]
  command=php /path/to/artisan queue:work
          --queue=automation-flow-resume,default
          --tries=3
          --timeout=60
          --sleep=3
```

---

## مرجع سريع للمتغيرات البيئية

```
FLOW_BUILDER_V2_ENABLED                       bool    true
FLOW_BUILDER_V2_MAX_NODES                     int     80
FLOW_BUILDER_V2_MAX_EDGES                     int     160
FLOW_BUILDER_V2_AUTOSAVE_DEBOUNCE_MS          int     1200
FLOW_BUILDER_V2_MAX_EXECUTION_STEPS           int     60
FLOW_BUILDER_V2_RESUME_QUEUE                  string  automation-flow-resume
FLOW_BUILDER_V2_ASSET_URL_TTL_MINUTES         int     1440
FLOW_BUILDER_V2_ACTIVE_RUN_STALE_MINUTES      int     30
FLOW_BUILDER_V2_WAITING_INPUT_STALE_MINUTES   int     1440
FLOW_BUILDER_V2_WAITING_HANDOFF_STALE_MINUTES int     10080
FLOW_BUILDER_V2_CONTACT_LOCK_TTL_SECONDS      int     10
FLOW_BUILDER_V2_CONTACT_LOCK_WAIT_SECONDS     int     3
FLOW_BUILDER_V2_INVALID_REPLY_DEFAULT_BEHAVIOR string  release_to_fallback
FLOW_BUILDER_V2_CUSTOMER_CARE_WINDOW_HOURS    int     24
FLOW_BUILDER_V2_ENFORCE_CUSTOMER_CARE_WINDOW  bool    true
FLOW_BUILDER_V2_ON_WINDOW_CLOSED              string  fail_run
FLOW_BUILDER_V2_CHANNEL                       string  whatsapp
FLOW_BUILDER_V2_WHATSAPP_ONLY_MODE            bool    true
FLOW_BUILDER_V2_ALLOW_EXTERNAL_ACTIONS        bool    false
FLOW_BUILDER_V2_ALLOW_CRM_ACTIONS             bool    true
```
