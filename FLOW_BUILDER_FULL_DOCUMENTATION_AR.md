# Flow Builder — التوثيق التقني الكامل

> **الإصدار**: 2.0 (محرك الـ Canvas الجديد)
> **التقنيات**: Laravel 11 + Vue 3 + Inertia.js + VueFlow
> **القناة**: واتساب فقط
> **آخر مراجعة**: 2026-05-31

---

## فهرس المحتويات

1. [ملخص تنفيذي](#1-ملخص-تنفيذي)
2. [نظرة عامة على المعمارية](#2-نظرة-عامة-على-المعمارية)
3. [خريطة الملفات والمجلدات](#3-خريطة-الملفات-والمجلدات)
   - [ملفات الـ Backend](#31-ملفات-الـ-backend)
   - [ملفات الـ Frontend](#32-ملفات-الـ-frontend)
4. [تحليل الـ Frontend بالتفصيل](#4-تحليل-الـ-frontend-بالتفصيل)
   - [هيكل الـ UI والـ Components](#41-هيكل-الـ-ui-والـ-components)
   - [إدارة الحالة State Management](#42-إدارة-الحالة-state-management)
   - [الـ Canvas ونظام الـ Nodes](#43-الـ-canvas-ونظام-الـ-nodes)
   - [الحفظ التلقائي Autosave](#44-الحفظ-التلقائي-autosave)
   - [نظام التحقق Validation](#45-نظام-التحقق-validation)
   - [نظام المعاينة Preview](#46-نظام-المعاينة-preview)
   - [رفع الملفات Assets](#47-رفع-الملفات-assets)
5. [تحليل الـ Backend بالتفصيل](#5-تحليل-الـ-backend-بالتفصيل)
   - [الـ API Endpoints](#51-الـ-api-endpoints)
   - [طبقة الـ Services](#52-طبقة-الـ-services)
   - [محرك التحقق من الـ Graph](#53-محرك-التحقق-من-الـ-graph)
   - [مُحوِّل الـ Graph Compiler](#54-مُحوِّل-الـ-graph-compiler)
   - [محرك التنفيذ Runtime](#55-محرك-التنفيذ-runtime)
   - [محرك المعاينة Preview Engine](#56-محرك-المعاينة-preview-engine)
   - [إدارة الملفات والأسرار](#57-إدارة-الملفات-والأسرار)
6. [تحليل قاعدة البيانات](#6-تحليل-قاعدة-البيانات)
   - [الجداول](#61-الجداول)
   - [تفاصيل الـ Schema](#62-تفاصيل-الـ-schema)
   - [العلاقات وتدفق البيانات](#63-العلاقات-وتدفق-البيانات)
7. [مرجع أنواع الـ Nodes](#7-مرجع-أنواع-الـ-nodes)
8. [دورة حياة تنفيذ الـ Flow](#8-دورة-حياة-تنفيذ-الـ-flow)
9. [البيئة والإعدادات](#9-البيئة-والإعدادات)
10. [التحكم في الوصول](#10-التحكم-في-الوصول)
11. [المشاكل والـ Bugs والـ Technical Debt](#11-المشاكل-والـ-bugs-والـ-technical-debt)
12. [دليل التشغيل المحلي](#12-دليل-التشغيل-المحلي)
13. [مراجعة الجاهزية للـ Production](#13-مراجعة-الجاهزية-للـ-production)
14. [ملاحظات للمطورين](#14-ملاحظات-للمطورين)

---

## 1. ملخص تنفيذي

### إيه هي الميزة دي؟

الـ **Flow Builder** (اسمه داخلياً "Automation Flows v2") هو **محرك أتمتة محادثات بصري بدون كود** مدمج في منصة Botzo. بيسمح للمشغّلين إنهم يصمّموا رحلات محادثة آلية متعددة الخطوات بتتنفّذ على **واتساب** من غير ما يكتبوا أي كود.

### إيه المشكلة اللي بيحلّها؟

قبل الميزة دي، الردود الآلية كانت محدودة بالردود الجاهزة البسيطة اللي في `/automation/basic`. الـ Flow Builder بيستبدل ده بنظام مبني على الـ Graph حيث:

- ممكن تتبعت رسايل كتير في تسلسل.
- المحادثة بتتفرّع بناءً على اختيارات العميل (أزرار، قوائم).
- بيانات الـ Contact بتتاخد وتتحفظ أثناء المحادثة.
- التأخيرات والشروط والتحويل للإنسان كلها بتتعامل معاها تلقائياً.
- الـ Flows بتشتغل بشكل غير متزامن وبترجع تكمل بعد فترات الانتظار.

### القيمة التجارية

| الحالة | الحل بالـ Flow Builder |
|---|---|
| تأهيل العملاء المحتملين | Flow بأزرار بيجمع النية |
| توجيه الدعم | رسالة قائمة توجّه للقسم الصح |
| حجز المواعيد | حفظ الرد بيالتقط الموعد المفضّل |
| إثراء بيانات الـ Contact | تحديث حقول الـ Contact أثناء المحادثة |
| التحويل للإنسان | انتقال سلس للـ Agent مع الـ context |

### الفكرة العامة

```
المشغّل يبني الـ Graph في الـ UI ← ينشره ← الـ Flow يستنى الـ Triggers ←
الـ Runtime يعالج كل Node ← العملاء يتلقّوا رحلة آلية
```

---

## 2. نظرة عامة على المعمارية

### طبقات النظام

```
┌─────────────────────────────────────────────────────────┐
│                    المتصفح (Vue 3)                       │
│  Builder.vue ─ Canvas بـ VueFlow ─ لوحة الـ Inspector   │
│  flowBuilderStudio.js ─ flowBuilderDraft.js              │
│  flowBuilderValidation.js ─ flowBuilderGraph.js          │
└────────────────────┬────────────────────────────────────┘
                     │  Inertia.js / Axios (HTTP)
┌────────────────────▼────────────────────────────────────┐
│               Laravel Web Server                         │
│  AutomationFlowController                                │
│  ├─ AutomationFlowBuilderService (CRUD + نشر)            │
│  ├─ AutomationFlowGraphValidator (تحقق)                  │
│  ├─ AutomationFlowGraphCompiler  (تحويل للـ Runtime)     │
│  ├─ AutomationFlowPreviewService (محاكاة)                │
│  └─ AutomationFlowAssetService   (الميديا)               │
└────────────────────┬────────────────────────────────────┘
                     │  Database / Cache / Queue
┌────────────────────▼────────────────────────────────────┐
│               طبقة البيانات                              │
│  MySQL: automation_flows, versions, runs, steps          │
│         assets, node_secrets                             │
│  Redis: قفل الـ Contact (10 ثانية TTL)                   │
│  Queue: automation-flow-resume (وظايف التأخير)           │
└────────────────────┬────────────────────────────────────┘
                     │  بيتّشغل لما رسالة واتساب تيجي
┌────────────────────▼────────────────────────────────────┐
│               محرك الـ Runtime                           │
│  AutomationFlowRuntimeService::handleInbound(Chat)       │
│  ├─ قفل الـ Contact (Redis، 10 ثانية)                    │
│  ├─ كشف الـ Run الشغّال / تسقيط الـ Runs القديمة        │
│  ├─ مطابقة الـ Trigger (أي / أول / كلمة مفتاحية)        │
│  ├─ إنشاء AutomationFlowRun                              │
│  └─ حلقة تنفيذ الـ Nodes (أقصاه 60 خطوة)               │
│      ├─ send_text    ← WhatsApp API                      │
│      ├─ send_buttons ← WhatsApp API ← استنى رد          │
│      ├─ delay        ← Job مؤجّل                         │
│      ├─ condition    ← تقييم ← فرع                      │
│      └─ handoff      ← وقّف الـ Run                     │
└─────────────────────────────────────────────────────────┘
```

### تدفق البيانات لجلسة عمل عادية

```
1. POST /automation/flows              ← إنشاء (قالب جاهز)
2. GET  /automation/flows/{uuid}       ← تحميل كل بيانات الـ Builder
3. POST /automation/flows/{uuid}/autosave ← حفظ تلقائي كل 1.2 ثانية
4. POST /automation/flows/{uuid}/validate ← تحقق فوري
5. POST /automation/flows/{uuid}/preview  ← محاكاة المسار
6. POST /automation/flows/{uuid}/publish  ← تجميع + نسخة + تفعيل
   ↓
7. رسالة واتساب واردة
   → AutomationFlowRuntimeService::handleInbound()
   → الـ Flow بيشتغل Node بـ Node
   → AutomationFlowRun بيتتبّع الحالة
   → AutomationFlowRunStep بيسجّل كل خطوة
```

### تدفق الأحداث (Runtime)

```
Webhook واتساب
    │
    ▼
WebhookController::handle()
    │
    ▼
AutomationFlowRuntimeService::handleInbound(Chat $chat)
    │
    ├── withContactLock() ─── قفل Redis (10 ثانية، انتظر 3 ثواني)
    │       │
    │       ▼
    │   handleInboundUnlocked()
    │       ├── لقيت Run شغّال للـ Contact؟
    │       │       ├── أيوه ← resumeWaitingRun()
    │       │       └── لأ   ← دوّر على Flow مطابق
    │       │                     └── startRun()
    │       │                           └── continueRun()
    │       │
    │       └── continueRun() ─── حلقة تنفيذ الـ Nodes
    │               ├── send_text    → WhatsApp API
    │               ├── send_buttons → WhatsApp API → WAIT
    │               ├── delay        → Job مؤجّل   → WAIT
    │               ├── condition    → تقييم الفرع  → loop
    │               └── end          → اكتمل         → return
    │
    └── حرّر القفل
```

---

## 3. خريطة الملفات والمجلدات

### 3.1 ملفات الـ Backend

#### الـ Controller

| الملف | الدور |
|---|---|
| `app/Http/Controllers/User/AutomationFlowController.php` | 13 endpoint HTTP. بيفوّض كل منطق الأعمال للـ Services. بيعمل Feature Guard (تحقق من الـ Schema والـ Addon). |

#### الـ Models (6 نماذج)

| الملف | الدور | العلاقات الرئيسية |
|---|---|---|
| `app/Models/AutomationFlow.php` | الكيان الأساسي. بيحفظ الـ Graph والحالة. | `currentVersion()`, `versions()`, `runs()`, `assets()`, `nodeSecrets()` |
| `app/Models/AutomationFlowVersion.php` | نسخة غير قابلة للتغيير من الـ Graph المنشور | `belongsTo(AutomationFlow)` |
| `app/Models/AutomationFlowRun.php` | تنفيذ واحد لكل Contact لكل Flow مُشغَّل | `belongsTo(AutomationFlow)`, `hasMany(AutomationFlowRunStep)` |
| `app/Models/AutomationFlowRunStep.php` | صف واحد لكل Node اتنفّذت | `belongsTo(AutomationFlowRun)` |
| `app/Models/AutomationFlowAsset.php` | ملفات الميديا المرفوعة للـ Nodes | `belongsTo(AutomationFlow)` |
| `app/Models/AutomationFlowNodeSecret.php` | بيانات SMTP المشفّرة لكل Node | `belongsTo(AutomationFlow)` |

#### الـ HTTP Requests (تحقق من الإدخال)

| الملف | بيتحقق من |
|---|---|
| `StoreAutomationFlowRequest.php` | `name` (مطلوب، أقصاه 120 حرف)، `description` (اختياري)، `goal_preset` |
| `SaveAutomationFlowRequest.php` | `name`، `graph_json` (الـ Nodes لازم يكون ليها `id`+`type`)، `ui_json`، `node_secrets` |
| `ValidateAutomationFlowRequest.php` | `graph_json`، `node_secrets` |
| `PreviewAutomationFlowRequest.php` | `graph_json`، `focus_node_id` (اختياري) |
| `UploadAutomationFlowAssetRequest.php` | `file` (مطلوب)، `media_kind` (اختياري) |

#### الـ Services (20 كلاس)

| الملف | الدور |
|---|---|
| `AutomationFlowAccessService.php` | بيتحقق: هل الـ Runtime شغّال؟ الـ Addon مفعّل؟ الـ Schema موجود؟ المصدر الوحيد للحقيقة. |
| `AutomationFlowBuilderService.php` | يُنسّق كل عمليات الـ Builder: list، create، update، validate، publish، preview، duplicate |
| `AutomationFlowGraphValidator.php` | التحقق الكامل من الـ Graph: الهيكل، إعدادات الـ Nodes، حدود الخطة، امتثال واتساب، المسارات الدائرية |
| `AutomationFlowGraphCompiler.php` | بيحوّل الـ Graph الخام لصيغة Runtime محسّنة مع Adjacency List |
| `AutomationFlowRuntimeService.php` | **محرك التنفيذ الأساسي.** بيتعامل مع الرسايل الواردة، يشغّل الـ Nodes، يدير حالة الـ Flow |
| `AutomationFlowRuntimeSupportService.php` | Helper methods للـ Runtime: buildInboundContext، resolveNextNodeId، recordStep، failRunAtNode |
| `AutomationFlowPreviewService.php` | يحاكي التنفيذ عشان يولّد Timeline للمعاينة (من غير أي تأثيرات جانبية) |
| `AutomationFlowNodeCatalog.php` | بيعرّف كل أنواع الـ 16 Node بميتاداتا (تسمية، أيقونة، فئة، هل advanced) |
| `AutomationFlowStarterTemplateService.php` | بيولّد الـ Graphs الجاهزة للـ 4 أهداف المسبقة |
| `AutomationFlowBuilderPolicyService.php` | بيطبّق سياسة الـ Builder: أنهي أنواع Nodes مسموح بيها |
| `AutomationFlowAssetService.php` | تخزين/حذف/نسخ الملفات، توليد الـ Signed URLs |
| `AutomationFlowNodeSecretService.php` | تشفير/فكّ تشفير بيانات SMTP، مزامنة الـ Secret Refs |
| `AutomationFlowPersonalizationService.php` | بيستبدل `{first_name}` و`{email}` وغيرها في نص الرسايل وقت التنفيذ |
| `AutomationFlowContactMutationService.php` | عمليات add_to_group، remove_from_group، update_contact_field |
| `AutomationFlowActionDispatchService.php` | إرسال الإيميلات عن طريق SMTP باستخدام الـ Node Secrets |
| `AutomationFlowConversationHandoffService.php` | منطق assign_to_agent، human_handoff، handoff_to_ai_assistant |
| `AutomationFlowRunQuotaService.php` | بيتحقق من حصص الـ Runs الشهرية مع حدود الخطة |
| `AutomationFlowSessionVariableService.php` | بيدير المتغيرات في الذاكرة أثناء التنفيذ |
| `AutomationFlowWhatsappComplianceService.php` | بيتحقق من الأزرار (أقصاه 3)، القوائم (أقصاه 10 صفوف)، حدود طول النص |

#### الـ Jobs (وظايف الـ Queue)

| الملف | الدور |
|---|---|
| `app/Jobs/ResumeAutomationFlowRunJob.php` | وظيفة Queue بتتوزّع من الـ Delay Node. بتستدعي `resumeDelayedRun()` بعد انتهاء مدة التأخير. Queue: `automation-flow-resume` |

#### الـ Migrations

| الملف | بينشئ |
|---|---|
| `2026_03_13_010000_create_automation_flow_tables.php` | `automation_flows`، `automation_flow_versions`، `automation_flow_runs`، `automation_flow_run_steps` |
| `2026_03_13_030000_create_automation_flow_assets_and_node_secrets_tables.php` | `automation_flow_assets`، `automation_flow_node_secrets` |

#### الـ Routes

| الملف | المحتوى |
|---|---|
| `routes/web/automation.php` | 13 Route محمية للـ Builder CRUD |
| `routes/web/public.php` (السطر 94) | `GET /automation/flows/{uuid}/assets/{assetUuid}` — Route بـ Signed URL لتقديم الميديا (`flowbuilder.assets.show`) |

#### الـ Config

| الملف | المهم فيه |
|---|---|
| `config/automation_flows.php` | كل إعدادات الميزة: تفعيل، max_nodes، max_edges، debounce، إعدادات Runtime، إعدادات واتساب، سياسة الـ Builder |

---

### 3.2 ملفات الـ Frontend

#### الـ Pages (الصفحات)

| الملف | الدور |
|---|---|
| `resources/js/Pages/User/Automation/Flows/Index.vue` | قائمة الـ Flows مع البحث والفلتر، زر الإنشاء، لوحة الحالة |
| `resources/js/Pages/User/Automation/Flows/Builder.vue` | **المحرر الرئيسي** (1446 سطر). بيُنسّق كل حاجة: Canvas، Inspector، Preview، Autosave، Publish، Drag/Drop |

#### الـ Components (27 ملف في `resources/js/Components/AutomationFlows/`)

**الهيدر والتحكم:**

| الـ Component | الدور |
|---|---|
| `FlowBuilderHeaderCard.vue` | الشريط العلوي: اسم الـ Flow، شارة الحالة، زر النشر، القائمة |
| `FlowReadinessPanel.vue` | بيعرض أخطاء التحقق وتحذيرات حدود الخطة فوق الـ Canvas |

**الـ Canvas:**

| الـ Component | الدور |
|---|---|
| `FlowCanvasNode.vue` | بيرسم كارد الـ Node الواحدة على الـ Canvas |
| `FlowCanvasNodeHeader.vue` | رأس الكارد: الأيقونة، شارة النوع، العنوان، زر القائمة |
| `FlowCanvasNodeMenu.vue` | القائمة السياقية: إعادة تسمية، نسخ، حذف |
| `FlowCanvasEdge.vue` | بيرسم الوصلة بين الـ Nodes مع تسمية الفرع وزر "إدراج Node" |
| `FlowCanvasNodeRoutingHealth.vue` | مؤشر بصري لأخطاء التحقق على الـ Node |

**الـ Inspector (لوحة التعديل اليمنى):**

| الـ Component | الدور |
|---|---|
| `FlowInspectorPanel.vue` | الحاوية. بتعرض تعليمات لو ما في Node محدودة |
| `FlowNodeInspectorRenderer.vue` | بيوجّه للفورم الصح حسب نوع الـ Node |
| `FlowNodeInspectorTriggerForm.vue` | تعديل الـ Trigger: match_mode، keywords، starting_step |
| `FlowNodeInspectorTextMediaForm.vue` | تعديل send_text وsend_media |
| `FlowNodeInspectorInteractiveForm.vue` | تعديل send_buttons وsend_list |
| `FlowNodeInspectorContactActionForm.vue` | تعديل أعمال الـ Contact |
| `FlowNodeInspectorHandoffForm.vue` | تعديل الـ Handoff Nodes |
| `FlowNodeInspectorExternalActionForm.vue` | تعديل send_email والـ delay |
| `FlowNodeInspectorConditionForm.vue` | تعديل الـ Condition Node |

**الـ Modals:**

| الـ Component | الدور |
|---|---|
| `FlowCreateModal.vue` | نافذة إنشاء Flow جديد |
| `FlowMetaEditModal.vue` | تعديل اسم ووصف الـ Flow |
| `FlowPreviewModal.vue` | نافذة المعاينة الكاملة بمحاكاة واتساب |
| `FlowExitConfirmModal.vue` | تحذير من تغييرات غير محفوظة |
| `FlowBuilderDangerModals.vue` | تأكيد الحذف (Node وFlow) |

#### ملفات JavaScript (15+ ملف)

| الملف | الدور |
|---|---|
| `flowBuilderStudio.js` | تنسيق الـ Canvas: تحويل Nodes/Edges، إدراج Node على Edge |
| `flowBuilderDraft.js` | أدوات المسودة: نسخ البيانات، بناء Edge، الإعدادات الافتراضية للـ Nodes |
| `flowBuilderGraph.js` | تحويلات الـ Graph: تطبيع الـ Trigger، تقليم الفروع |
| `flowBuilderValidation.js` | التحقق من جهة الـ Client: أخطاء كل Node، ملخص الأخطاء |
| `flowBuilderMeta.js` | ميتاداتا الـ Nodes: التسمية، الأيقونة، الفئة |
| `flowBuilderDanger.js` | العمليات الخطيرة: حذف Node، حذف Flow، حماية التنقل |
| `flowBuilderInsights.js` | حساب رؤى الجاهزية للوحة المعلومات |
| `flowCanvasLayout.js` | مساعدات VueFlow: تحديث الـ Nodes |
| `flowCanvasRuntime.js` | Context الـ Canvas عبر provide/inject |
| `flowNodePresenter.js` | استنتاج نوع الـ Node من شكل الـ Config |
| `flowNodeVisuals.js` | ألوان وأنماط كل نوع Node |
| `flowIconRegistry.js` | ربط أنواع الـ Nodes بأيقونات Lucide |
| `useFlowCanvasSurfaceDrag.js` | Composable لسحب الـ Node على الـ Canvas |

---

## 4. تحليل الـ Frontend بالتفصيل

### 4.1 هيكل الـ UI والـ Components

```
Builder.vue (الصفحة الرئيسية)
├── SettingLayout (الغلاف)
├── FlowBuilderHeaderCard      ← شريط علوي ثابت
│   ├── اسم الـ Flow + شارة الحالة
│   ├── زر الحفظ / تسمية الحفظ التلقائي
│   ├── زر النشر
│   └── قائمة More (تحقق، معاينة، إيقاف مؤقت، نسخ، حذف)
├── FlowReadinessPanel         ← شريط تحذيرات التحقق
├── [الشريط الأيسر] مكتبة الخطوات
│   ├── تاب Messages (send_text, send_media, send_buttons, send_list)
│   └── تاب Actions (كل الأنواع التانية)
├── [الوسط] VueFlow Canvas
│   ├── FlowCanvasNode (× عدد الـ Nodes)
│   │   ├── FlowCanvasNodeHeader
│   │   ├── FlowNodeInspectorRenderer  ← محرر مدمج (لو مفتوح)
│   │   └── FlowCanvasNodeRoutingHealth
│   └── FlowCanvasEdge (× عدد الـ Edges)
│       └── تسمية الفرع + زر "إدراج"
├── FlowMetaEditModal
├── FlowPreviewModal
│   └── محاكاة واجهة واتساب
├── FlowExitConfirmModal
└── FlowBuilderDangerModals
```

### 4.2 إدارة الحالة State Management

الـ Builder بيستخدم **Vue 3 Reactive State محلي فقط** — من غير Pinia أو Vuex. كل الحالة محفوظة في `Builder.vue` كـ `ref()`.

**متغيرات الحالة الرئيسية:**

| المتغير | النوع | الغرض |
|---|---|---|
| `draft` | `ref(Object)` | بيانات الـ Flow الحالية (الاسم، الحالة، graph_json، ui_json) |
| `nodes` | `ref(Array)` | كائنات VueFlow Nodes (التمثيل البصري) |
| `edges` | `ref(Array)` | كائنات VueFlow Edges |
| `activeNodeId` | `ref(String)` | ID الـ Node المحدودة حالياً |
| `focusedNodeId` | `ref(String)` | ID الـ Node اللي المحرر المدمج بتاعها مفتوح |
| `saveState` | `ref(String)` | `'saved'` / `'dirty'` / `'autosaving'` / `'saving'` / `'error'` |
| `validation` | `ref(Object)` | `{ valid, errors[], warnings[] }` |
| `previewData` | `ref(Object)` | Timeline المعاينة من الـ Backend |
| `assets` | `ref(Object)` | Map من `{ uuid → assetObject }` |
| `nodeSecrets` | `ref(Object)` | Map من `{ nodeId → { host, port, user, ... } }` |

**نمط الـ Provider/Inject:**

`provideFlowCanvasRuntime()` في `Builder.vue` بتحقن الـ Canvas context في كل الـ Child Nodes عبر Vue's `provide`:
- `activeNodeId`، `focusedNodeId`، `draggingNodeId`
- `isNodeActive()`، `isNodeFocused()`، `isNodeDragging()` — Predicates متفاعلة
- Callbacks: `openNodeSurface`، `toggleNodeInline`، `collapseNodeInline`

الـ Child Components بتستخدم `useFlowCanvasRuntime()` عشان تاخد الـ Context ده.

### 4.3 الـ Canvas ونظام الـ Nodes (VueFlow)

الـ Canvas مشغّل بـ **VueFlow** (`@vue-flow/core`)، النسخة Vue من React Flow.

**تسجيل الأنواع المخصصة:**
```javascript
const nodeTypes = { automationCanvasNode: markRaw(FlowCanvasNode) };
const edgeTypes = { automationCanvasEdge: markRaw(FlowCanvasEdge) };
```

**هيكل بيانات الـ Node** (الـ `data` property لكل VueFlow Node):
```javascript
{
  nodeType: 'send_text',          // نوع الـ Flow Node
  config: { text: 'أهلاً!' },    // إعدادات الـ Node
  title: 'رسالة الترحيب',        // العنوان المعروض
  label: 'نص بسيط',              // تسمية النوع
  errors: [...],                  // أخطاء التحقق لكل Node
  contactFields: [...],           // حقول الـ Contact (جاية من الـ Props)
  asset: { uuid, url, ... },      // لو الـ Node فيها ميديا
  nodeSecret: { ... },            // بيانات SMTP (عرض فقط)
  onUpdate: fn,                   // Callbacks لـ Builder.vue
  onDelete: fn,
  onDuplicate: fn,
  // ... callbacks تانية
}
```

**مزامنة الـ Graph والـ Canvas:**

الـ Builder بيحتفظ بتمثيلين في نفس الوقت:
1. **Graph JSON** (`draft.value.graph_json`) — صيغة البيانات الرسمية المحفوظة في الـ DB
2. **VueFlow nodes/edges arrays** — التمثيل البصري

بيتزامنوا عبر:
- `rebuildCanvas(graph)` — بناء كامل من الـ Graph JSON
- `syncCanvasPresentation({ nodeIds })` — تحديث جزئي لـ Nodes معينة
- `graphFromCanvas()` — تصوير الـ Canvas راجع لـ Graph JSON

**Drag & Drop:**

فيه نوعين Drag:
1. **Library drag**: سحب نوع Node من الشريط الأيسر للـ Canvas. بيستخدم HTML5 `dataTransfer`.
2. **Node surface drag**: سحب كروت الـ Nodes الموجودة لإعادة تموضعها. Handled بـ `useFlowCanvasSurfaceDrag.js`.

### 4.4 الحفظ التلقائي Autosave

```
المستخدم يعدّل Node
    └─→ markDraftDirty()
            ├─ saveState = 'dirty'
            └─ queueAutosave()
                    └─ setTimeout(persistDraft, 1200ms)  ← Debounced

persistDraft()
    ├─ saveState = 'autosaving'
    ├─ POST /automation/flows/{uuid}/autosave
    ├─ saveState = 'saved'
    ├─ runValidation()
    └─ refreshPreview() (لو لوحة المعاينة مفتوحة)
```

**حماية التزامن**: لو في حفظ جاري (`activeSavePromise`)، التغييرات الجديدة بتحطّ `changesQueuedDuringSave = true`. بعد انتهاء الحفظ الجاري، دورة حفظ تانية بتبدأ.

**الحفظ اليدوي**: `PUT /automation/flows/{uuid}` (نفس الـ Payload بس HTTP Method مختلف). بيتستخدم لما المستخدم يضغط "حفظ" بنفسه.

**Payload الحفظ:**
```javascript
{
  name: draft.value.name,
  description: draft.value.description,
  graph_json: graphFromCanvas(),   // أحدث حالة للـ Canvas
  ui_json: composeUiJson(),        // الـ Viewport، حالة اللوحات، الـ Node النشطة
  node_secrets: nodeSecrets.value  // بيانات SMTP (مش بتتحفظ في graph_json أبداً)
}
```

### 4.5 نظام التحقق Validation

فيه طبقتين:

**الطبقة الأولى — تحقق مدمج في الـ Node** (`flowBuilderValidation.js::buildNodeErrors`):
- بيشتغل من جهة الـ Client عند كل `syncCanvasPresentation()`
- كل Node بتعرض أخطاءها بنفسها
- محتاجش Round-trip للـ Server
- بيتحقق من: نص فاضي، ملف ميديا مش موجود، ما في أزرار، SMTP ناقص، وغيرها

**الطبقة التانية — تحقق Server-side** (`POST /validate`):
- تحقق كامل من الـ Graph بما فيه: مسارات دائرية، Nodes مش وصّالة، حدود الخطة، امتثال واتساب
- بيرجع `{ valid, errors[], warnings[] }`
- بيتشغّل بعد كل حفظ وبشكل يدوي من القائمة

### 4.6 نظام المعاينة Preview

المعاينة بتولّد **Timeline محاكاة للمحادثة** بتوضّح شكل شات واتساب.

**التشغيل**: فتح نافذة المعاينة ← `refreshPreview()` ← `POST /automation/flows/{uuid}/preview`

**عناصر الـ Timeline** (كل خطوة ليها `kind`):
- `'assistant'` — رسايل بيبعتها البوت
- `'user'` — اختيارات المستخدم المحاكاة (أول زر، أول صف قائمة)
- `'system'` — أحداث النظام (بدء الـ Trigger، التأخير، الـ Handoff، النهاية)

### 4.7 رفع الملفات Assets

**مسار الرفع:**
```javascript
// Builder.vue::uploadNodeAsset()
const formData = new FormData();
formData.append('file', file);
formData.append('media_kind', mediaKind);
await axios.post(`/automation/flows/${uuid}/assets`, formData);
// ← response.data.asset.uuid
// ← updateNode(nodeId, { config: { asset_id: assetUuid } })
```

الملفات بتتخزن في `storage/app/automation-flows/{org_id}/{flow_uuid}/{filename}` على الـ `local` disk.

روابط الملفات هي **Signed Temporary URLs** (مدتها 1440 دقيقة افتراضياً).

---

## 5. تحليل الـ Backend بالتفصيل

### 5.1 الـ API Endpoints

كل الـ Routes تحت Middleware المصادقة:

| الطريقة | الـ URI | الميثود | الغرض |
|---|---|---|---|
| GET | `/automation/flows` | `index` | قائمة الـ Flows (paginated، قابلة للبحث والفلتر) |
| POST | `/automation/flows` | `store` | إنشاء Flow جديد بقالب جاهز |
| GET | `/automation/flows/{uuid}` | `show` | تحميل كل بيانات الـ Builder |
| PUT | `/automation/flows/{uuid}` | `update` | حفظ المسودة (حفظ يدوي) |
| POST | `/automation/flows/{uuid}/autosave` | `autosave` | حفظ تلقائي (بيفوّض لـ `update`) |
| POST | `/automation/flows/{uuid}/validate` | `validateDraft` | تحقق من الـ Graph |
| POST | `/automation/flows/{uuid}/preview` | `preview` | توليد Timeline المعاينة |
| POST | `/automation/flows/{uuid}/publish` | `publish` | نشر (تحقق + تجميع + نسخة + تفعيل) |
| POST | `/automation/flows/{uuid}/pause` | `pause` | تبديل حالة الإيقاف المؤقت |
| POST | `/automation/flows/{uuid}/duplicate` | `duplicate` | نسخ الـ Flow مع ملفاته وأسراره |
| POST | `/automation/flows/{uuid}/assets` | `uploadAsset` | رفع ملف ميديا |
| DELETE | `/automation/flows/{uuid}/assets/{assetUuid}` | `deleteAsset` | حذف ملف ميديا |
| DELETE | `/automation/flows/{uuid}` | `destroy` | حذف ناعم للـ Flow |
| GET | `/automation/flows/{uuid}/assets/{assetUuid}` | `showAsset` | تقديم ملف ميديا بـ Signed URL (في public.php) |

**الـ Builder Payload** (اللي بيرجعه endpoint الـ `show`):
```json
{
  "flow": { "id", "uuid", "name", "status", "graph_json", "ui_json", ... },
  "builder_runtime": { "autosave_debounce_ms", "builder_policy", "runtime", "whatsapp_compliance" },
  "library": [...],           // أنواع الـ Nodes المتاحة للـ Org دي
  "plan_limits": { ... },     // حدود الـ Flows والـ Nodes والـ Runs
  "contact_fields": [...],    // حقول الـ Contact بتاعة الـ Org
  "contact_groups": [...],    // مجموعات الـ Contact
  "assignable_agents": [...], // الـ Agents القابلين للتعيين
  "assets": { uuid: assetObject },
  "node_secrets": { nodeId: { type, display_name } },
  "preview": { scenario, steps[] },
  "validation": { valid, errors[], warnings[] }
}
```

### 5.2 طبقة الـ Services

شجرة الاعتمادية الكاملة من الـ Controller:

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
│   ├── AutomationFlowNodeCatalog
│   ├── AutomationFlowStarterTemplateService
│   ├── AutomationFlowBuilderPolicyService
│   ├── AutomationFlowAssetService
│   ├── AutomationFlowNodeSecretService
│   ├── AutomationFlowWhatsappComplianceService
│   ├── AutomationFlowConversationHandoffService
│   ├── SubscriptionPlanLimitService
│   └── OrganizationHierarchyService
├── AutomationFlowAccessService
│   └── AddonStateService
└── AutomationFlowAssetService
```

### 5.3 محرك التحقق من الـ Graph

`AutomationFlowGraphValidator::validate()` بيعمل الفحوصات دي **بالترتيب**:

**1. حدود الخطة:**
- عدد الـ Nodes ≤ `flow_builder_nodes_per_flow_limit`
- لو `flow_builder_advanced_enabled = false`، بيمنع أنواع الـ Nodes المتقدمة

**2. هيكل الـ Graph:**
- على الأقل Node واحدة
- Trigger واحد بالظبط
- `start_node_id` صالح يشاور على الـ Trigger
- كل الـ Nodes وصّالة من الـ Trigger (BFS traversal)
- ما فيش مسارات دائرية (DFS cycle detection)
- ما فيش اتصال الـ Node بنفسها

**3. تحقق من إعدادات كل Node:**
- `trigger`: `match_mode` صالح، keywords موجودة لو `keyword_match`
- `send_text`: نص مش فاضي
- `send_media`: `media_type` صالح، ملف اتحمل
- `send_buttons`: body + على الأقل زر، ما فيش IDs مكررة، امتثال واتساب
- `send_list`: body + button_label + صف واحد، عناوين الأقسام، ما فيش IDs مكررة
- `condition`: source + operator + مرجع الحقل صالح
- `add_to_group` / `remove_from_group`: group_uuid صالح
- `send_email`: subject + body + SMTP secret مكتمل
- `delay`: minutes ≥ 1

**4. تحقق من الـ Edges:**
- `condition` ليها فرعين `matched` و`unmatched`
- كل زر في `send_buttons` ليه Edge متصلة
- كل صف في `send_list` ليه Edge متصلة
- `end`، `human_handoff`، `handoff_to_ai_assistant` ما عندهاش Edges صادرة

### 5.4 مُحوِّل الـ Graph Compiler

`AutomationFlowGraphCompiler::compile()` بيحوّل صيغة الـ Builder لصيغة **محسّنة للـ Runtime**:

**الدخل (graph_json):**
```json
{
  "start_node_id": "trigger-1",
  "nodes": [{ "id": "trigger-1", "type": "trigger", "position": {...}, "config": {...} }],
  "edges": [{ "id": "e1", "source_id": "trigger-1", "target_id": "send-text-1", "branch": "default" }]
}
```

**الخرج (compiled_json):**
```json
{
  "start_node_id": "trigger-1",
  "nodes": {
    "trigger-1": { "id": "trigger-1", "type": "trigger", "config": {...} }
  },
  "edges": [...],
  "adjacency": {
    "trigger-1": [{ "source_id": "trigger-1", "target_id": "send-text-1", "branch": "default" }]
  }
}
```

الفرق الأساسي:
- `nodes` بيبقى **Map مفهرس بالـ ID** (Object) لبحث O(1)
- `adjacency` بيبقى **قائمة لكل Node مصدر** بـ Edges الصادرة منها
- بيانات الـ `ui` بتتشال من الـ Nodes (مش محتاجاها في الـ Runtime)
- الـ Edges الباطلة (بتشاور على Nodes مش موجودة) بتتفلتر

### 5.5 محرك التنفيذ Runtime

`AutomationFlowRuntimeService` هو قلب النظام.

#### نقاط الدخول

1. **`handleInbound(Chat $chat)`** — بيتّستدعى لما رسالة واتساب تيجي
2. **`resumeDelayedRun(AutomationFlowRun $run)`** — بيتّستدعى من `ResumeAutomationFlowRunJob`

#### قفل الـ Contact

كل تنفيذ بياخد **Redis Distributed Lock** لكل `(organization_id, contact_id)`:
- TTL: 10 ثانية
- انتظر: 3 ثواني
- لو timeout: برجع `false` (الرسالة بتتتجاهل)
- لو أي Error تاني: بيكمّل من غير قفل (Graceful degradation — لكن ده unsafe)

```
مفتاح القفل = "automation-flow-runtime:{org_id}:{contact_id}"
```

#### مطابقة الـ Trigger

لكل Flow منشور (مرتبين بـ `updated_at DESC`):
- `any_incoming`: بيطابق لو الرسالة مش فاضية أو في Interactive Selection
- `first_in_conversation`: بيطابق لو ده أول شات Inbound للـ Contact (count = 1)
- `keyword_match`: بيطابق لو الرسالة (lowercase) فيها أي كلمة من الكلمات المضبوطة

أول Flow يطابق هو اللي بيشتغل.

#### `continueRun` — حلقة تنفيذ الـ Nodes

```php
while ($currentNodeId && nodeExists && $steps < 60):
    $steps++
    $node = nodeMap[$currentNodeId]

    // 1. تحقق السياسة — ممنوع بـ builder_policy؟
    // 2. تحقق Node مش نشطة — تخطّي لو config.active === false
    // 3. تحقق نافذة Customer Care — منع لو نافذة واتساب 24 ساعة مغلقة
    // 4. تنفيذ الـ Node حسب $node['type']
    // 5. الحصول على $nextNodeId من الـ Adjacency Edges
    // 6. تحديث run.current_node_id = $nextNodeId ← loop
endwhile
// Fallback: تجاوز 60 خطوة أو Node مش موجودة → fail the run
```

**نتائج تنفيذ الـ Node:**

| النتيجة | اللي بيحصل |
|---|---|
| الـ Node اتنفّذت بنجاح | `nextNodeId` بيتضبط ← الـ Loop بيكمّل |
| ما فيش Node تالية | الـ Run بيتعلّم `completed` ← خروج |
| أول زيارة لـ `send_buttons` | الـ Run بيبقى `waiting_input`، برجع |
| أول زيارة لـ `send_list` | الـ Run بيبقى `waiting_input`، برجع |
| أول زيارة لـ `delay` | الـ Run بيبقى `waiting_delay`، Job بيتوزّع، برجع |
| `human_handoff` | الـ Run بيبقى `waiting_handoff`، برجع |
| `end` node | الـ Run بيتعلّم `completed`، برجع |
| فشل WhatsApp API | الـ Run بيتعلّم `failed`، برجع |
| تجاوز 60 خطوة | الـ Run بيتعلّم `failed` مع سبب |

#### حلّ الفرع

`resolveNextNodeId(edges, preferredBranch)`:
1. ابحث عن Edge بـ `branch === preferredBranch`
2. Fallback: Edge بـ `branch === 'default'`
3. Fallback: أول Edge

### 5.6 محرك المعاينة

`AutomationFlowPreviewService::project()` بيحاكي التنفيذ **من غير تأثيرات جانبية**:
- بيجمّع الـ Graph (نفس الـ Compiler المستخدم في الـ Runtime)
- بيمشي في الـ Graph في حلقة (بدون قفل Contact، بدون كتابة DB، بدون WhatsApp API calls)
- للـ Nodes التفاعلية (أزرار، قوائم)، بياخد **أول خيار** تلقائياً
- بيرجع Timeline من `{ kind, node_id, label, meta }` items

المعاينة بتمثّل **المسار السعيد (Happy Path)** — دايماً بتاخد أول فرع متاح.

### 5.7 إدارة الملفات والأسرار

**الـ Assets:**
- بتتحفظ محلياً: `storage/app/automation-flows/{org_id}/{flow_uuid}/{filename}`
- بتتقدّم عبر Signed URLs (مدتها 24 ساعة افتراضياً) من خلال Route `showAsset`
- في الـ Runtime، `resolveMediaUrl()` بيولّد Signed URL جديدة لاستدعاء WhatsApp API
- عند نسخ الـ Flow: الملفات بتتنسخ فيزيائياً في الـ Storage

**الأسرار:**
- بيانات SMTP مش بتتحفظ أبداً في `graph_json`
- بتعيش في جدول `automation_flow_node_secrets` مع `payload_json` مشفّر بـ Laravel's Encryption
- الـ Frontend بياخد `{ type, display_name }` بس — مش بيانات حقيقية
- عند الحفظ: الـ Frontend بيبعت `node_secrets` اللي بتتحفظ مشفّرة واتستبدل في `graph_json` بـ `secret_ref` UUID
- في الـ Runtime: `payloadForNode()` بيفكّ التشفير ويرجع إعدادات SMTP

---

## 6. تحليل قاعدة البيانات

### 6.1 الجداول

| الجدول | الغرض | عمر الـ Row |
|---|---|---|
| `automation_flows` | تعريف الـ Flow، الحالة الحالية، الـ Draft Graph | لحد الحذف الناعم |
| `automation_flow_versions` | نسخ منشورة غير قابلة للتغيير | لحد الحذف القوي |
| `automation_flow_runs` | تنفيذ واحد لكل Contact Trigger | لحد حذف الـ Flow (cascade) |
| `automation_flow_run_steps` | سجل التنفيذ لكل Node | لحد حذف الـ Run (cascade) |
| `automation_flow_assets` | ملفات الميديا المرفوعة | لحد الحذف الصريح |
| `automation_flow_node_secrets` | بيانات SMTP المشفّرة | لحد حذف الـ Node من الـ Flow |

### 6.2 تفاصيل الـ Schema

#### `automation_flows`

```sql
id                      BIGINT PK
uuid                    CHAR(36) UNIQUE
organization_id         FK → organizations
name                    VARCHAR
description             TEXT nullable
goal_preset             VARCHAR  default:'sales_qualification'
channel                 VARCHAR  default:'whatsapp'
trigger_type            VARCHAR  default:'incoming_whatsapp_message'
status                  VARCHAR  default:'draft'  -- draft|published|paused
graph_json              JSON nullable    -- nodes[], edges[], start_node_id
ui_json                 JSON nullable    -- viewport، selection، layout state
current_version_id      BIGINT nullable → automation_flow_versions.id
last_published_at       TIMESTAMP nullable
has_unpublished_changes BOOLEAN default:true
runs_count              BIGINT unsigned default:0
created_by              FK → users nullable
updated_by              FK → users nullable
deleted_at              TIMESTAMP nullable  (Soft Delete)
```

**الـ Indexes:**
- `(organization_id, status)` — للقائمة حسب الحالة
- `(organization_id, channel)` — لبحث الـ Runtime على الـ Flows المنشورة

#### `automation_flow_versions`

```sql
id                      BIGINT PK
uuid                    CHAR(36) UNIQUE
automation_flow_id      FK → automation_flows CASCADE
organization_id         FK → organizations CASCADE
version_number          INT unsigned      -- بيزيد عند كل نشر
label                   VARCHAR nullable  -- 'v1'، 'v2'، ...
graph_json              JSON             -- نسخة الـ Graph وقت النشر
ui_json                 JSON nullable
compiled_json           JSON             -- الصيغة المحسّنة للـ Runtime
published_by            FK → users nullable
published_at            TIMESTAMP
```

#### `automation_flow_runs`

```sql
id                      BIGINT PK
uuid                    CHAR(36) UNIQUE
automation_flow_id      FK → automation_flows CASCADE
automation_flow_version_id FK → automation_flow_versions CASCADE
organization_id         FK → organizations CASCADE
contact_id              FK → contacts CASCADE
chat_id                 FK → chats nullable
status                  VARCHAR  -- active|waiting_input|waiting_handoff|waiting_delay|completed|cancelled|expired|failed
current_node_id         VARCHAR nullable
waiting_node_id         VARCHAR nullable
waiting_for             VARCHAR nullable  -- 'button'|'list'|'free_text'|'delay'|'human_handoff'|'ai_handoff'
state_json              JSON nullable     -- { context، runtime، handoff، variables }
last_input_json         JSON nullable     -- آخر Inbound Context
started_at              TIMESTAMP
completed_at            TIMESTAMP nullable
next_resume_at          TIMESTAMP nullable  -- للـ Delay Nodes
last_activity_at        TIMESTAMP nullable
```

**الـ Indexes:**
- `(organization_id, status)` — بحث الـ Runtime على الـ Active Runs
- `(contact_id, status)` — تحقق الـ Run النشط لكل Contact
- `(next_resume_at, status)` — لاستعلام التأخيرات المتأخرة

#### `automation_flow_run_steps`

```sql
id                      BIGINT PK
automation_flow_run_id  FK → automation_flow_runs CASCADE
automation_flow_id      FK → automation_flows CASCADE
organization_id         FK → organizations CASCADE
node_id                 VARCHAR
node_type               VARCHAR
status                  VARCHAR  -- executed|waiting|resumed|skipped|failed|cancelled
input_json              JSON nullable  -- الـ Context الواردة وقت التنفيذ
output_json             JSON nullable  -- نتيجة عمل الـ Node
metadata_json           JSON nullable  -- Context/سبب إضافي
occurred_at             TIMESTAMP
```

#### `automation_flow_node_secrets`

```sql
id                      BIGINT PK
uuid                    CHAR(36) UNIQUE
automation_flow_id      FK → automation_flows CASCADE
organization_id         FK → organizations CASCADE
node_id                 VARCHAR
node_type               VARCHAR  -- مثلاً: 'send_email'
payload_json            LONGTEXT  -- JSON مشفّر بـ Laravel Encryption
UNIQUE (automation_flow_id, node_id, node_type)
```

### 6.3 العلاقات وتدفق البيانات

```
automation_flows (1)
    │
    ├── (N) automation_flow_versions   ← واحدة لكل نشر
    │           │
    │           └── compiled_json ──── بيستخدمه الـ Runtime
    │
    ├── (N) automation_flow_runs       ← واحدة لكل تنفيذ Contact
    │           │
    │           └── (N) automation_flow_run_steps  ← واحدة لكل Node
    │
    ├── (N) automation_flow_assets     ← ملفات الميديا المرفوعة
    │
    └── (N) automation_flow_node_secrets ← بيانات SMTP المشفّرة
```

**الـ Runtime بيستخدم النسخة المجمّعة، مش المسودة:**
- `AutomationFlowRun` دايماً بيشاور على `AutomationFlowVersion` محدد
- حتى لو الـ Flow اتنشر من جديد بتغييرات، الـ Runs الشغّالة بتكمّل على النسخة اللي بدأت بيها
- `flow.currentVersion` بيشاور على آخر نسخة منشورة

---

## 7. مرجع أنواع الـ Nodes

### Trigger Nodes (واحدة لكل Flow، إلزامية)

| النوع | شرط الـ Trigger | الإعدادات |
|---|---|---|
| `trigger` | رسالة واتساب واردة | `match_mode`، `keywords[]`، `starting_step` |

**أوضاع المطابقة:**
- `any_incoming` — أي رسالة مش فاضية أو أي رد تفاعلي
- `first_in_conversation` — أول رسالة في الـ Contact بالكامل
- `keyword_match` — الرسالة فيها أي من الكلمات المضبوطة

### Message Nodes (واتساب Native — محتاجة نافذة Customer Care)

| النوع | الوصف | الإعدادات الأساسية |
|---|---|---|
| `send_text` | رسالة نصية عادية | `text` (يدعم `{first_name}` وغيرها) |
| `send_media` | صورة/فيديو/صوت/مستند | `media_type`، `asset_id`، `caption` |
| `send_buttons` | أزرار Quick Reply تفاعلية (أقصاه 3) | `body`، `header`، `footer`، `buttons[]{id, title}`، `invalid_reply_behavior` |
| `send_list` | قائمة تفاعلية | `body`، `button_label`، `sections[]{title, rows[]{id, title, description}}`، `invalid_reply_behavior` |

**`invalid_reply_behavior`** (للأزرار والقوائم):
- `release_to_fallback` — الـ Run بيخلص والرسالة بتعدي للشات العادي
- `repeat_prompt` — بعت الـ Prompt تاني
- `end_run` — الغِ الـ Run

### Action Nodes (ما محتاجتش نافذة Customer Care)

| النوع | الوصف | الإعدادات الأساسية |
|---|---|---|
| `save_reply_to_field` | استنى رد نصي حر، احفظه في حقل أو متغير | `save_target`، `field_uuid`، `variable_key` |
| `condition` | تفرّع بناءً على قاعدة | `source`، `operator`، `value`، `field_uuid`، `variable_key` |
| `add_to_group` | أضف الـ Contact لمجموعة | `group_uuid` |
| `remove_from_group` | اشيل الـ Contact من مجموعة | `group_uuid` |
| `update_contact_field` | حدّث حقل الـ Contact | `save_target`، `field_uuid`، `mode`، `value` |
| `assign_to_agent` | افتح/عيّن Ticket دعم (الـ Flow **بيكمّل** بعدها) | `assignment_mode`، `agent_user_id` |
| `human_handoff` | عيّن Ticket وـ**وقّف** الـ Flow (waiting_handoff) | `assignment_mode`، `agent_user_id` |
| `handoff_to_ai_assistant` | AI يتولّى وـ**وقّف** الـ Flow | — |
| `send_email` | ابعت إيميل عبر SMTP | `subject`، `body`، `secret_ref` |
| `delay` | استنى N دقيقة | `minutes` (أدنى: 1) |
| `end` | خلّص الرحلة | — |

### المتقدمة مقابل الأساسية

علامة `flow_builder_advanced_enabled` في الخطة بتتحكم في الوصول لكل أنواع الـ Nodes **إلا** `trigger`، `send_text`، و`end`. لو `false`، بس الـ 3 أنواع دي بتظهر في المكتبة وممكن تتنشر.

---

## 8. دورة حياة تنفيذ الـ Flow

### المرحلة الأولى: البناء

```
1. المستخدم يفتح /automation/flows/create → FlowCreateModal
2. POST /automation/flows → AutomationFlowBuilderService::create()
   → يختار القالب الجاهز حسب goal_preset وقدرات الخطة
   → ينشئ AutomationFlow بـ status='draft'
   → يرجع uuid → Redirect لـ /automation/flows/{uuid}

3. GET /automation/flows/{uuid} → AutomationFlowBuilderService::builderPayload()
   → بيحمّل: flow، library، plan_limits، contact_fields، contact_groups،
             assignable_agents، assets، node_secrets، validation، preview
   → Inertia بيرندر Builder.vue بكل الـ Props

4. المستخدم يعدّل Nodes/Edges في الـ Canvas
   → Click على Node: تحديد (activeNodeId بيتغيّر)
   → Double-click: فتح المحرر المدمج (focusedNodeId بيتغيّر)
   → التعديل في الـ Inspector: updateNode(nodeId, patch) → markDraftDirty()
   → Debounce 1.2 ثانية → POST /autosave

5. الحفظ التلقائي:
   POST /automation/flows/{uuid}/autosave
   → SaveAutomationFlowRequest بيتحقق من الهيكل
   → AutomationFlowNodeSecretService::sanitizeGraphAndSyncSecrets()
      → بيستخرج بيانات SMTP من node_secrets
      → بيشفّرها ويحفظها في automation_flow_node_secrets
      → بيستبدلها في graph_json بـ secret_ref UUID
   → AutomationFlow::update(graph_json، ui_json، has_unpublished_changes=true)
```

### المرحلة الثانية: النشر

```
المستخدم يضغط "نشر"
→ Builder.vue::publishFlow()
   1. saveDraft() — نأكد إن آخر حالة متحفظة
   2. POST /automation/flows/{uuid}/publish
      → AutomationFlowBuilderService::publish()
         a. ensureOwnership()
         b. assertActiveFlowPublishLimit() — تحقق من حصة الخطة
         c. ensureGraphRespectsBuilderPolicy() — تحقق السياسة
         d. AutomationFlowGraphValidator::ensureValid() — تحقق كامل
         e. AutomationFlowGraphCompiler::compile() — إنشاء compiled_json
         f. AutomationFlowVersion::create(version_number++, compiled_json)
         g. AutomationFlow::update(status='published', current_version_id=version.id)
      → Response: { status: 'ok', message: '...' }
   3. draft.value.status = 'published'
   4. draft.value.has_unpublished_changes = false
```

### المرحلة الثالثة: الـ Trigger في الـ Runtime

```
رسالة واتساب من Contact تيجي
→ (مفترض) WebhookController ينشئ Chat record، ويستدعي:
→ AutomationFlowRuntimeService::handleInbound(Chat $chat)

1. access.availableForOrganization() — تحقق الميزة
2. withContactLock(org_id, contact_id, callback, انتظار 3 ثواني، TTL 10 ثواني)
3. داخل القفل:
   a. runtimeSupport.buildInboundContext(chat)
      → { last_user_message، selected_button_id، selected_list_row_id، input_type، ... }
   
   b. دوّر على Run موجود نشط:
      AutomationFlowRun حيث:
        - organization_id = chat.organization_id
        - contact_id = chat.contact_id
        - status IN (waiting_input، waiting_handoff، active)
      آخر واحد بالـ ID
   
   c. لو لقيت Run وهو قديم → سقّطه (null)
   
   d. لو لقيت Run:
      → resumeWaitingRun(run, chat)
   
   e. لو ما فيش Run (أو resumeWaitingRun رجع null):
      → ابحث على الـ Flows المنشورة للـ Org/channel=whatsapp
      → لكل Flow: triggerMatches(flow, chat, context)?
      → أول تطابق: startRun(flow, chat)
```

### المرحلة الرابعة: حلقة تنفيذ الـ Nodes

```
continueRun(run, chat):

while (currentNodeId && nodeExists && steps < 60):
    steps++
    node = compiled.nodes[currentNodeId]
    outgoing = compiled.adjacency[currentNodeId] || []
    
    // تحققات قبل التنفيذ:
    لو builderPolicy.blocksNodeType(node.type):
        failRun(reason='builder_policy_blocked')
        return
    
    لو node.config.active === false (مش trigger):
        recordStep(status='skipped')
        nextNodeId = outgoing.default
        كمّل
    
    لو نافذة واتساب مغلقة && node بتبعت رسالة:
        لو on_window_closed == 'release_to_fallback':
            cancelRun() مع release_to_fallback = true
            return
        إلا:
            failRun(reason='conversation_window_closed')
            return
    
    switch node.type:
        'trigger'              → recordStep + كمّل
        'send_text'            → استبدال Personalization + WhatsApp.sendMessage + كمّل
        'send_media'           → resolveMediaUrl + WhatsApp.sendMedia + كمّل
        'send_buttons'         → لو بنكمّل: حلّ الفرع | إلا: sendButtons + انتظر
        'send_list'            → لو بنكمّل: حلّ الصف  | إلا: sendList + انتظر
        'save_reply_to_field'  → استخرج last_user_message → احفظ في حقل/متغير + كمّل
        'condition'            → evaluateCondition() → 'matched' أو 'unmatched'
        'add_to_group'         → mutations.addToGroup() + كمّل
        'remove_from_group'    → mutations.removeFromGroup() + كمّل
        'update_contact_field' → احفظ القيمة + كمّل
        'assign_to_agent'      → handoff.assignToAgent() + كمّل (الـ Flow بيكمّل!)
        'human_handoff'        → handoff.startHumanHandoff() + انتظر (الـ Flow بيوقف!)
        'handoff_to_ai_assist' → handoff.startAiHandoff() + انتظر (الـ Flow بيوقف!)
        'send_email'           → actionDispatch.sendEmail() + كمّل
        'delay'                → لو بنكمّل: كمّل | إلا: وزّع Job + انتظر
        'end'                  → علّم completed + return
        default                → علّم failed + return
    
    لو nextNodeId موجود:
        run.current_node_id = nextNodeId
        run.status = 'active'
    إلا:
        run.status = 'completed'
        return
```

### المرحلة الخامسة: التعليق والاستئناف

#### `waiting_input` (أزرار / قوائم / نص حر)

```
الـ Run معلّق:
  - status = 'waiting_input'
  - waiting_node_id = currentNodeId
  - waiting_for = 'button' | 'list' | 'free_text'

رسالة تانية تيجي → handleInbound():
  → لقيت Run موجود → resumeWaitingRun()
  
  للـ 'button':
    - الشات فيه selected_button_id؟
      أيوه → تحديث run.status=active → continueRun()
               (send_buttons case بيكتشف waiting_node_id match → حلّ الفرع)
      لأ   → invalid_reply_behavior:
            - 'repeat_prompt' → بعت الأزرار تاني
            - 'release_to_fallback' → ارجع false (الرسالة تعدي للشات العادي)
            - 'end_run' → ألغِ الـ Run
  
  للـ 'list': نفس الأزرار بس بـ selected_list_row_id
  
  للـ 'free_text':
    - تحديث run.last_input_json بالرسالة الجديدة
    - تحديث run.status = active
    - continueRun() → save_reply_to_field بتالتقط last_user_message
```

#### `waiting_delay` (الـ Delay Node)

```
الـ Run معلّق:
  - status = 'waiting_delay'
  - waiting_node_id = 'delay-X'
  - waiting_for = 'delay'
  - next_resume_at = الوقت الحالي + N دقيقة

ResumeAutomationFlowRunJob بيتوزّع بتأخير N دقيقة

الـ Job بيشتغل:
  → AutomationFlowRuntimeService::resumeDelayedRun(run)
  → withContactLock()
  → استرداد الـ Run الجديد من الـ DB
  → لو status != 'waiting_delay': ألغِ (ممكن يكون اتلغى/انتهت صلاحيته)
  → run.update(status='active', next_resume_at=null)
     *** waiting_node_id وwaiting_for بيفضلوا زي ما هم (مش بيتمسحوا) ***
  → continueRun(fresh_run, chat=null)
     → بيوصل لـ 'delay' node
     → بيتحقق: waiting_node_id === currentNodeId && waiting_for === 'delay'
     → أيوه → recordStep(status='executed') + مسح waiting fields + كمّل
```

---

## 9. البيئة والإعدادات

### المتغيرات البيئية المطلوبة

```dotenv
# التشغيل الأساسي (بيبقى true لو مش موجود)
FLOW_BUILDER_V2_ENABLED=true

# حدود الـ Graph
FLOW_BUILDER_V2_MAX_NODES=80
FLOW_BUILDER_V2_MAX_EDGES=160

# الحفظ التلقائي
FLOW_BUILDER_V2_AUTOSAVE_DEBOUNCE_MS=1200

# التنفيذ
FLOW_BUILDER_V2_MAX_EXECUTION_STEPS=60
FLOW_BUILDER_V2_RESUME_QUEUE=automation-flow-resume

# ملفات الميديا
FLOW_BUILDER_V2_ASSET_URL_TTL_MINUTES=1440

# قفل الـ Contact (Redis)
FLOW_BUILDER_V2_CONTACT_LOCK_TTL_SECONDS=10
FLOW_BUILDER_V2_CONTACT_LOCK_WAIT_SECONDS=3

# تقادم الـ Runs
FLOW_BUILDER_V2_ACTIVE_RUN_STALE_MINUTES=30
FLOW_BUILDER_V2_WAITING_INPUT_STALE_MINUTES=1440
FLOW_BUILDER_V2_WAITING_HANDOFF_STALE_MINUTES=10080

# الرد الغير صالح
FLOW_BUILDER_V2_INVALID_REPLY_DEFAULT_BEHAVIOR=release_to_fallback

# نافذة واتساب
FLOW_BUILDER_V2_CUSTOMER_CARE_WINDOW_HOURS=24
FLOW_BUILDER_V2_ENFORCE_CUSTOMER_CARE_WINDOW=true
FLOW_BUILDER_V2_ON_WINDOW_CLOSED=fail_run   # أو: release_to_fallback

# سياسة الـ Builder
FLOW_BUILDER_V2_CHANNEL=whatsapp
FLOW_BUILDER_V2_WHATSAPP_ONLY_MODE=true
FLOW_BUILDER_V2_ALLOW_EXTERNAL_ACTIONS=false
FLOW_BUILDER_V2_ALLOW_CRM_ACTIONS=true
```

### الخدمات المطلوبة

| الخدمة | ليه محتاجينها | ملاحظات |
|---|---|---|
| **MySQL** | كل بيانات الـ Flow، الـ Runs، الـ Steps | الـ 6 جداول لازم تتعمل (Migration) |
| **Redis** | قفل الـ Contact أثناء التنفيذ | لازم يكون Cache Driver |
| **Queue Worker** | وظايف استئناف الـ Delay | لازم يشتغل على `automation-flow-resume` queue |
| **Storage (Local Disk)** | تخزين ملفات الميديا | `storage/app/` لازم تكون قابلة للكتابة |
| **WhatsApp API** | إرسال الرسايل في الـ Runtime | بتتضبط لكل Organization |

### المتطلبات في قاعدة البيانات

**3 سجلات لازم تكون موجودة زيادة على الـ Migrations:**

```sql
-- 1. تفعيل الـ Addon
UPDATE addons SET status = 1, is_active = 1 WHERE name = 'Flow builder';

-- 2. اشتراك نشط للـ Organization
INSERT INTO subscriptions (uuid, organization_id, plan_id, status, start_date, valid_until, ...)
VALUES (..., 'active', NOW(), DATE_ADD(NOW(), INTERVAL 1 YEAR), ...);

-- 3. الـ Plan Metadata يشمل Flow builder
-- في subscription_plans.metadata (JSON):
{
  "addons": { "Flow builder": true },
  "flow_builder_active_flows_limit": -1,
  "flow_builder_nodes_per_flow_limit": -1,
  "flow_builder_monthly_runs_limit": -1,
  "flow_builder_advanced_enabled": 1
}
```

---

## 10. التحكم في الوصول

### سلسلة التحقق (`AutomationFlowAccessService`)

```
طلب جاي للـ AutomationFlowController
                │
                ▼
    FLOW_BUILDER_V2_ENABLED = true؟
         لأ ─────────────────────── 404 / Redirect لـ /automation/basic
         │
         أيوه
         ▼
    addons.status = 1 AND addons.is_active = 1؟
         لأ ─────────────────────── 403 (الميزة مش مفعّلة)
         │
         أيوه
         ▼
    الـ Org عندها اشتراك نشط؟
         لأ ─────────────────────── الـ Limits بترجع 0 (محجوب فعلياً)
         │
         أيوه
         ▼
    plan.metadata.addons['Flow builder'] = true؟
         لأ ─────────────────────── 403
         │
         أيوه
         ▼
    جدول automation_flows موجود؟ (base_schema_ready)
         لأ ─────────────────────── 503 (شغّل الـ Migrations)
         │
         أيوه
         ▼
    جدول automation_flow_assets موجود؟ (builder_schema_ready)
         لأ ─────────────────────── الميزات المتقدمة (Assets/Secrets) معطّلة
         │
         أيوه
         ▼
    الـ Org عندها Permission للعملية؟
         لأ ─────────────────────── 403 ممنوع
         │
         أيوه
         ▼
         ✅ كمّل
```

### الـ Permissions المطلوبة

| العملية | مفتاح الـ Permission |
|---|---|
| عرض قائمة الـ Flows | `automations.flows.view` |
| فتح الـ Flow Builder | `automations.flows.view` |
| إنشاء / نسخ Flow | `automations.flows.add` |
| حفظ المسودة / رفع Asset | `automations.flows.edit` |
| نشر / إيقاف مؤقت | `automations.flows.publish` |
| حذف Flow | `automations.flows.delete` |

---

## 11. المشاكل والـ Bugs والـ Technical Debt

### ✅ Bugs مصلَحة (أثناء التحليل ده)

#### BUG-001: الـ Delay Node بيعمل Infinite Loop *(حرج — مصلَح)*

**الملف**: `app/Services/AutomationFlows/AutomationFlowRuntimeService.php`

**المشكلة**: `resumeDelayedRun()` كان بيمسح `waiting_node_id` و`waiting_for` قبل استدعاء `continueRun()`. لما `continueRun` بيوصل للـ Delay Node تاني مع `waiting_node_id = null`، كانت الـ Delay case مفيهاش Check تكتشف إن ده Resume مش بداية جديدة — كانت بتجدوّل Delay تانية وتوزّع Job تاني، في حلقة لا نهاية.

**الإصلاح المطبّق**:
1. `resumeDelayedRun()`: بقى مش بيمسح `waiting_node_id`/`waiting_for` قبل استدعاء `continueRun`.
2. `continueRun()` في الـ `delay` case: أضفنا Check للاستئناف مطابق تماماً لـ `send_buttons`:
   ```php
   if ($run->waiting_node_id === $currentNodeId && $run->waiting_for === 'delay') {
       $this->recordStep($run, $currentNodeId, 'executed', $context, ['reason' => 'delay_completed']);
       $run->update(['waiting_node_id' => null, 'waiting_for' => null]);
       $nextNodeId = $this->resolveNextNodeId($outgoing, 'default');
       break;
   }
   ```

---

### ⚠️ Technical Debt موجود

#### ISSUE-002: `limitForOrganization` بترجع 0 من غير Subscription *(تصميم — فخ محتمل)*

**الملف**: `SubscriptionPlanLimitService.php:107`

**المشكلة**: لو ما فيش اشتراك نشط، `limitForOrganization()` بترجع `0` (مش الـ `$default`). للـ `flow_builder_nodes_per_flow_limit`، قيمة 0 بتمنع كل النشر.

**الحل**: تأكد إن كل Org عندها اشتراك نشط قبل استخدام الـ Flow Builder.

---

#### ISSUE-003: ما فيش Retry Mechanism للـ WhatsApp Sends الفاشلة *(فجوة موثوقية)*

**الملف**: `AutomationFlowRuntimeService.php`

**المشكلة**: لو أي استدعاء WhatsApp API فشل (مشكلة شبكة، Rate Limit)، الـ Run بيتعلّم فوراً `failed` من غير Retry.

**التأثير**: خطأ عابر في الـ WhatsApp API بيفشّل الـ Flow Run بشكل دائم.

**المقترح**: لفّ مكالمات WhatsApp في Job قابل للـ Retry مع Exponential Backoff.

---

#### ISSUE-010: القفل Redis بيتخطّى لو في Error *(أمان)*

**الملف**: `AutomationFlowRuntimeService.php:747`

```php
} catch (\Throwable $exception) {
    report($exception);
    return $callback();   // ← بيشتغل من غير قفل!
}
```

لو Redis رمى Error غير متوقع (مش `LockTimeoutException`)، الكود بيشتغل **من غير قفل**. يعني رسالتين متزامنتين لنفس الـ Contact ممكن يشغّلوا الـ Flow مع بعض في نفس الوقت.

**المقترح**: شيل الـ Fallback أو خلّيه Configurable. افشل بأمان بدل ما تفشل بشكل خطر.

---

#### ISSUE-007: الـ Assets ما بتتمسحش عند Soft Delete *(تسرّب بيانات)*

**المشكلة**: لما `AutomationFlow` يتحذف ناعم (`deleted_at` بتتضبط)، الـ `automation_flow_assets` والملفات الفيزيائية بتفضل. ما بتتنظّفش لحد ما الـ Flow يتحذف قوي.

---

#### ISSUE-008: `window.prompt()` للـ Rename *(مشكلة UX)*

```javascript
const nextTitle = window.prompt(t('Rename node'), currentTitle);
```

بيستخدم `window.prompt()` Native اللي blocking وبدون Style. المفروض يتبدّل بـ Modal محترم.

---

## 12. دليل التشغيل المحلي

### المتطلبات

- PHP 8.2+، Composer
- Node.js 18+، npm
- MySQL 8.0+
- Redis

### الخطوات خطوة بخطوة

```bash
# 1. تثبيت الـ PHP Dependencies
composer install

# 2. تثبيت الـ JS Dependencies
npm install

# 3. نسخ ملف البيئة
cp .env.example .env

# 4. إعدادات .env الأساسية:
```

```dotenv
APP_KEY=        # php artisan key:generate
DB_DATABASE=botzo_sa
DB_USERNAME=root
DB_PASSWORD=كلمة_سرك

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# الـ Flow Builder (الـ Defaults كويسة للـ Dev المحلي):
FLOW_BUILDER_V2_ENABLED=true
```

```bash
# 5. توليد الـ App Key
php artisan key:generate

# 6. تشغيل كل الـ Migrations
php artisan migrate

# تحقق إن الجداول دي موجودة:
# automation_flows, automation_flow_versions
# automation_flow_runs, automation_flow_run_steps
# automation_flow_assets, automation_flow_node_secrets

# 7. تفعيل Addon الـ Flow Builder في الـ DB
php artisan tinker --execute="DB::table('addons')->where('name', 'Flow builder')->update(['status' => 1, 'is_active' => 1]);"

# 8. إنشاء اشتراك نشط للـ Organization
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
echo 'تم إنشاء الاشتراك';
"

# 9. تفعيل الـ Flow Builder في الـ Plan Metadata
php artisan tinker --execute="
\$plan = DB::table('subscription_plans')->first();
\$meta = json_decode(\$plan->metadata, true);
\$meta['addons'] = ['Flow builder' => true];
\$meta['flow_builder_active_flows_limit'] = -1;
\$meta['flow_builder_nodes_per_flow_limit'] = -1;
\$meta['flow_builder_monthly_runs_limit'] = -1;
\$meta['flow_builder_advanced_enabled'] = 1;
DB::table('subscription_plans')->where('id', \$plan->id)->update(['metadata' => json_encode(\$meta)]);
echo 'تم تحديث الخطة';
"

# 10. تحقق من كل حاجة
php artisan tinker --execute="
\$access = app(\App\Services\AutomationFlows\AutomationFlowAccessService::class);
\$report = \$access->readinessReport(1);
foreach(\$report as \$k => \$v) echo \$k.' => '.json_encode(\$v).PHP_EOL;
"
# المتوقع: builder_ready: true, addon_enabled: true

# 11. Build الـ Frontend
npm run dev     # تطوير (مع HMR)
# أو
npm run build   # Build للـ Production

# 12. تشغيل الـ Web Server
php artisan serve

# 13. مهم: تشغيل Queue Worker للـ Delay Nodes
php artisan queue:work --queue=automation-flow-resume,default

# 14. Symlink للـ Storage (لو محتاج الـ Public Disk)
php artisan storage:link
```

### التحقق من عمل الميزة

```
http://localhost:8000/automation/flows
```

المفروض تشوف صفحة قائمة الـ Flows من غير ما تتحوّل لـ `/automation/basic`.

---

## 13. مراجعة الجاهزية للـ Production

### الـ Scalability

| الجانب | الحالة | ملاحظات |
|---|---|---|
| Indexes قاعدة البيانات | ✅ كويس | كل أعمدة البحث في الـ Runtime عندها Index |
| قفل الـ Contact | ✅ كويس | Redis Distributed Lock يمنع التشغيل المتزامن |
| عزل الـ Queue | ✅ كويس | Queue مخصصة `automation-flow-resume` |
| حدود الـ Nodes | ✅ كويس | أقصاه 80 Node، 160 Edge، 60 خطوة تنفيذ |
| تنظيف الـ Runs القديمة | ✅ كويس | TTLs قابلة للضبط لكل حالات الانتظار |

### الأمان

| الجانب | الحالة | ملاحظات |
|---|---|---|
| بيانات SMTP | ✅ مشفّرة | `payload_json` مشفّر بـ `Crypt::encrypt` |
| تقديم الملفات | ✅ مؤمّن | Signed URLs مؤقتة بـ TTL 24 ساعة |
| تحقق الملكية | ✅ موجود | `ensureOwnership()` في كل عملية Builder |
| بوابات الـ Permission | ✅ موجودة | `checkPermission()` دقيقة في كل Endpoint |
| التحقق من الإدخال | ✅ موجود | FormRequest على كل Endpoints الكتابة |

### الـ Observability

| الجانب | الحالة | ملاحظات |
|---|---|---|
| تسجيل التنفيذ | ✅ كويس | كل Node بتنشئ `run_step` مع Input/Output |
| تتبع الأخطاء | ✅ كويس | الـ Runs الفاشلة عندها `metadata_json` تفصيلي مع السبب |
| حالة الـ Run | ✅ كويس | دورة حياة كاملة: active ← waiting ← completed/failed/cancelled |
| Metrics | ❌ ناقص | ما فيش Prometheus/StatsD Metrics |
| تنبيهات | ❌ ناقص | ما فيش تنبيهات على معدلات الفشل أو تراكم الـ Queue |

### الـ Fault Tolerance

| السيناريو | التعامل |
|---|---|
| WhatsApp API معطّل | الـ Run بيفشل (ما فيش Retry) |
| Redis معطّل | القفل بيتخطّى (Fallback غير آمن) |
| Queue Worker معطّل | الـ Delayed Runs بتتراكم في الـ Queue، بتتنفّذ لما الـ Worker يرجع |
| قطع الـ DB | معالجة استثناء Laravel العادية |
| Flow بيتوقف أثناء Run | `resumeDelayedRun` بيتحقق إن `flow.status === 'published'` قبل الاستئناف |
| Contact بيتحذف أثناء Run | `Contact::find()` بيرجع null ← الـ Run بيفشل بشكل سلس |

---

## 14. ملاحظات للمطورين

### أهم 5 ملفات لازم تعرفها

1. **`AutomationFlowRuntimeService.php`** — محرك التنفيذ. اعمل فيه أي تعديل بحرص شديد. كل تغيير هنا بيأثر على محادثات عملاء حقيقيين في اللحظة دي.

2. **`Builder.vue`** — 1446 سطر بينسّق كل حاجة. ثلاثي `rebuildCanvas()` / `syncCanvasPresentation()` / `graphFromCanvas()` بيتحكم في كل الحالة. فهمهم أساسي لأي تغيير Frontend.

3. **`AutomationFlowGraphValidator.php`** — الحارس قبل النشر. أي نوع Node جديد لازم يتضاف هنا.

4. **`AutomationFlowGraphCompiler.php`** — جسر الصيغ بين الـ Builder والـ Runtime. هيكل `adjacency` أساسي للأداء.

5. **`AutomationFlowNodeCatalog.php`** — المصدر الوحيد للحقيقة لكل أنواع الـ Nodes. إضافة نوع جديد تبدأ من هنا.

### لما تضيف نوع Node جديد

1. أضفه في `AutomationFlowNodeCatalog::DEFINITIONS` و`TYPES`
2. أضف تحقق في `AutomationFlowGraphValidator::validateNodeConfig()`
3. أضف تنفيذ في `AutomationFlowRuntimeService::continueRun()` switch
4. أضف Preview item في `AutomationFlowPreviewService::previewItemsForNode()`
5. أضف Default Config في `flowBuilderDraft.js::defaultNodeConfig()`
6. أضف Inspector Form في `FlowNodeInspectorRenderer.vue`
7. أضف Client Validation في `flowBuilderValidation.js::buildNodeErrors()`
8. حدّد لو هو من `ADVANCED_TYPES` في الـ Catalog

### الملفات الحساسة

- `AutomationFlowNodeSecretService.php` — بيتعامل مع التشفير/فكّ التشفير. ما تعملش Log لـ `payload_json` أبداً.
- `AutomationFlowActionDispatchService.php` — بيبعت إيميلات حقيقية. احذر في الـ Staging.
- `AutomationFlowRuntimeService.php` — بيشتغل على بيانات عملاء حقيقيين وـ WhatsApp API حقيقي.

### أخطر الأماكن والمصائد الشائعة

1. **الـ Delay Node كان فيها Bug** (Infinite Loop) — اتصلح دلوقتي. لو حد بيعمل Refactor لـ `resumeDelayedRun`، لازم يحافظ على نمط `waiting_node_id`/`waiting_for`.

2. **`graph_json` مقابل `compiled_json`**: الـ Builder بيحفظ `graph_json` (Array من Nodes، Array من Edges). الـ Runtime بيقرأ `compiled_json` (Nodes Map مفهرسة + Adjacency List). ما تخلطهمش أبداً.

3. **`node_secrets` مش موجودة أبداً في `graph_json`**: الـ Frontend بيبعتهم بشكل منفصل. `sanitizeGraphAndSyncSecrets()` بتتعامل مع الـ DB Sync. لو تخطّيتها، البيانات هتتحفظ بدون تشفير.

4. **الـ Assets على الـ Local Disk**: مش متاحة للعموم بشكل مباشر. بتتقدّم عبر الـ Signed Route `flowbuilder.assets.show`. في الـ Production، فكّر تنقلهم لـ S3.

5. **قفل الـ Contact مش Atomic بالكامل**: شوف ISSUE-010. الـ `catch (\Throwable)` Fallback بيشتغل من غير قفل.

6. **أقصاه 60 خطوة**: Flow عميق التشعّب هيفشل لو تجاوز 60 Node Execution. الـ Validator بيمنع المسارات الدائرية، لكن الـ 60 خطوة هي شبكة الأمان.

7. **`first_in_conversation` Trigger**: بيعمل Count=1 وقت التنفيذ. لو الـ Webhook اشتغل مرتين بسرعة لنفس الرسالة، الاتنين ممكن يشوفوا Count=1. قفل الـ Contact بيساعد بس مش 100% آمن هنا.

8. **نافذة Customer Care لواتساب**: `send_text`، `send_buttons`، `send_media`، `send_list` ممكن تتبعت بس في خلال 24 ساعة من آخر رسالة للـ Customer. الـ Runtime بيطبّق ده. في المعاينة، النافذة دايماً محسوبة مفتوحة.

---

*تم توليده من تحليل الكود المصدري — 2026-05-31*
*كل الـ Code Snippets بتعكس التنفيذ الفعلي.*
