# Flow Builder — دليل الاختبار الكامل

> Checklist اختبار يدوي كامل، الـ Edge Cases، سيناريوهات الفشل، ومقترحات الـ Automated Tests.

---

## فهرس المحتويات

1. [Checklist الإعداد قبل الاختبار](#1-checklist-الإعداد-قبل-الاختبار)
2. [Checklist الاختبار اليدوي](#2-checklist-الاختبار-اليدوي)
   - [2.1 الوصول وعلامات الميزة](#21-الوصول-وعلامات-الميزة)
   - [2.2 قائمة الـ Flows](#22-قائمة-الـ-flows)
   - [2.3 إنشاء الـ Flow](#23-إنشاء-الـ-flow)
   - [2.4 الـ Canvas ونظام الـ Nodes](#24-الـ-canvas-ونظام-الـ-nodes)
   - [2.5 كل نوع Node](#25-كل-نوع-node)
   - [2.6 نظام التحقق Validation](#26-نظام-التحقق-validation)
   - [2.7 الحفظ والحفظ التلقائي](#27-الحفظ-والحفظ-التلقائي)
   - [2.8 نظام المعاينة Preview](#28-نظام-المعاينة-preview)
   - [2.9 النشر ودورة الحياة](#29-النشر-ودورة-الحياة)
   - [2.10 رفع الـ Assets](#210-رفع-الـ-assets)
   - [2.11 أسرار SMTP (send_email)](#211-أسرار-smtp-send_email)
   - [2.12 تنفيذ الـ Flow في الـ Runtime](#212-تنفيذ-الـ-flow-في-الـ-runtime)
   - [2.13 استئناف الـ Delay Node (Queue)](#213-استئناف-الـ-delay-node-queue)
   - [2.14 النسخ والحذف](#214-النسخ-والحذف)
3. [الـ Edge Cases](#3-الـ-edge-cases)
4. [سيناريوهات الفشل](#4-سيناريوهات-الفشل)
5. [مصفوفة اختبار الـ Runtime](#5-مصفوفة-اختبار-الـ-runtime)
6. [مقترحات الـ Automated Tests](#6-مقترحات-الـ-automated-tests)
7. [Checklist الجاهزية للـ Production](#7-checklist-الجاهزية-للـ-production)

---

## 1. Checklist الإعداد قبل الاختبار

قبل ما تبدأ أي اختبار، تأكد من الحاجات دي:

```
[ ] php artisan migrate → الـ 6 جداول automation موجودة
[ ] تأكد إن الـ Addon مفعّل في الـ DB:
    SELECT status, is_active FROM addons WHERE name = 'Flow builder';
    → لازم يكون: status=1, is_active=1

[ ] الـ Organization عندها اشتراك نشط:
    SELECT status, valid_until FROM subscriptions WHERE organization_id = 1;
    → لازم يكون: status='active', valid_until > NOW()

[ ] الـ Plan Metadata يشمل الـ Flow builder:
    SELECT metadata FROM subscription_plans WHERE id = 1;
    → metadata.addons["Flow builder"] = true

[ ] Redis شغّال (لقفل الـ Contact):
    redis-cli ping → PONG

[ ] Queue Worker شغّال (للـ Delay Nodes):
    php artisan queue:work --queue=automation-flow-resume,default

[ ] افتح /automation/flows → لازم ما يحوّلكش لـ /automation/basic

[ ] تحقق من الجاهزية:
    php artisan tinker --execute="
    echo json_encode(
        app(App\Services\AutomationFlows\AutomationFlowAccessService::class)
            ->readinessReport(1), JSON_PRETTY_PRINT);
    "
    → builder_ready: true
```

---

## 2. Checklist الاختبار اليدوي

### 2.1 الوصول وعلامات الميزة

```
[ ] روح /automation/flows كمستخدم متسجّل دخول
    → بتشوف صفحة قائمة الـ Flows (مش بتتحوّل لـ /automation/basic)

[ ] عطّل الـ Addon: UPDATE addons SET status=0 WHERE name='Flow builder';
    → روح /automation/flows → بتتحوّل لـ /automation/basic
    → فعّله تاني: UPDATE addons SET status=1 WHERE name='Flow builder';

[ ] اضبط FLOW_BUILDER_V2_ENABLED=false في .env، شغّل php artisan config:clear
    → روح /automation/flows → بتتحوّل لـ /automation/basic
    → فعّله تاني

[ ] اختبار من غير اشتراك نشط (اضبط valid_until لتاريخ ماضي)
    → المفروض الميزة تتحجب أو الـ Limits تبقى 0
```

### 2.2 قائمة الـ Flows

```
[ ] حالة فاضية: روح /automation/flows من غير ما يكون في Flows
    → يعرض رسالة أو UI للحالة الفاضية (مش Error)

[ ] أنشئ Flow → بيظهر في القائمة فوراً

[ ] ابحث بالاسم:
    → اكتب اسم الـ Flow في خانة البحث → القائمة بتفلتر صح
    → اكتب اسم مش موجود → القائمة فاضية

[ ] فلتر بالحالة:
    → فلتر 'draft' → بيعرض المسودات فقط
    → فلتر 'published' → بيعرض المنشور فقط
    → فلتر 'paused' → بيعرض المتوقف فقط

[ ] Pagination:
    → أنشئ 11+ Flow → الصفحة التانية بتظهر

[ ] قائمة Row Menu:
    [ ] تعديل → بيفتح الـ Builder للـ Flow ده
    [ ] نسخ → بينشئ نسخة، بيحوّل للـ Builder بتاعتها
    [ ] حذف → بيعرض Dialog تأكيد، بيحذف الـ Flow، بيشيله من القائمة
```

### 2.3 إنشاء الـ Flow

```
[ ] اضغط "إنشاء" → FlowCreateModal بيفتح

[ ] أنشئ باسم فقط (من غير وصف ومن غير Goal Preset)
    → Flow بيتنشأ بالـ Goal الافتراضي (sales_qualification)
    → بيحوّل للـ Builder مع القالب الصح

[ ] أنشئ مع كل Goal Preset:
    [ ] sales_qualification  → trigger + send_buttons (3 خيارات) + 3 texts + end
    [ ] support_routing      → trigger + send_list (3 صفوف) + 3 texts + end
    [ ] appointment_booking  → trigger + send_text + save_reply + send_text + end
    [ ] seller_intake        → trigger + send_text + save_reply + condition + 2 texts + end

[ ] أنشئ باسم طويل (120 حرف) → ينجح
[ ] أنشئ باسم 121 حرف → خطأ تحقق

[ ] أنشئ بوصف (أقصاه 1000 حرف) → ينجح
```

### 2.4 الـ Canvas ونظام الـ Nodes

```
[ ] الـ Canvas بيتعرض صح مع على الأقل الـ Trigger Node

[ ] لوحة المكتبة الأيسر:
    [ ] تاب "Messages" يعرض: Simple text، Media files، Interactive buttons، Interactive list
    [ ] تاب "Actions" يعرض: Save reply، Condition، Add to Group، Remove from Group،
                             Update Contact، Assign to Agent، Human handoff،
                             AI assistant handoff، Send Email، Delay، End
    [ ] بحث: اكتب "text" → يفلتر للأنواع المطابقة
    [ ] بحث: اكتب "xyz" → نتائج فاضية

[ ] اسحب Node من المكتبة للـ Canvas
    → الـ Node بتظهر في موضع الإسقاط
    → الـ Run بيتعلّم Dirty (الحفظ التلقائي بيشتغل بعد 1.2 ثانية)

[ ] اضغط "+" على عنصر في المكتبة → Node بتتضاف قرب الـ Node المحدودة

[ ] اضغط Node → بتتحدد (حدود مميّزة)

[ ] Double-click على Node → المحرر المدمج بيفتح جوّا الكارد

[ ] اضغط على خلفية الـ Canvas → الـ Node بتتشال من التحديد، المحرر بيقفل

[ ] اسحب كارد الـ Node لموضع جديد:
    → الـ Node بتتحرك
    → الموضع بيتحفظ في أول Autosave

[ ] Zoom الـ Canvas:
    [ ] Scroll للـ Zoom تكبير/تصغير
    [ ] تسمية الـ Zoom بتتحدث (مثلاً "85%")
    [ ] Controls التحكم (أسفل اليسار) شغّالة: +، -، Fit View

[ ] زر "Focus" (أعلى اليمين):
    → يشغّل Fullscreen للـ Browser للـ Workspace
    [ ] الخروج من Fullscreen بيشتغل

[ ] فتح في نافذة جديدة (board=1 parameter):
    → بيفتح عرض الـ Canvas فقط في تاب جديد (بدون Sidebar، بدون Header)

[ ] زر إخفاء/إظهار المكتبة بيشتغل

[ ] وصّل Node لـ Node تانية بسحب من مقبض الخرج للمدخل
    → Edge بيظهر مع تسمية الفرع

[ ] زر "إدراج Node" على الـ Edge:
    → اضغط "+" على Edge → يعرض منتقي نوع الـ Node
    → اختر نوع → Node بتتدرج بين الـ Nodes المتوصّلتين

[ ] اضغط Edge → يعرض خيار الحذف
    → تأكيد → الـ Edge بتتشال

[ ] قائمة سياق الـ Node (Click اليمين أو زر ⋮):
    [ ] إعادة تسمية → بيفتح Prompt → الاسم بيتحدث على الـ Canvas
    [ ] نسخ → بينشئ نسخة مع لاحقة "Copy" بإزاحة +80/+80
    [ ] حذف → بيعرض Dialog تأكيد → الـ Node بتتشال مع Edges بتاعتها
```

### 2.5 كل نوع Node

#### trigger
```
[ ] Dropdown وضع المطابقة: any_incoming / first_in_conversation / keyword_match
[ ] اختر keyword_match → خانة Keywords بتظهر
    → اكتب "مرحبا, أهلاً, هاي" → بتتحفظ كـ Array
[ ] اختر any_incoming → خانة Keywords تختفي
[ ] Dropdown الخطوة الأولى: يعرض كل الـ Nodes اللي مش trigger
    → اختر Node → starting_step بيتحفظ في الـ Config
```

#### send_text
```
[ ] اكتب نص الرسالة
[ ] اكتب {first_name} → بيُحترم كـ Personalization صالح
[ ] نص فاضي → يعرض خطأ تحقق على الـ Node
```

#### send_media
```
[ ] ارفع صورة (JPG/PNG أقل من 5MB) → ترفع، Thumbnail بيظهر
[ ] ارفع فيديو (MP4 أقل من 16MB) → ترفع
[ ] ارفع صوت (MP3 أقل من 16MB) → ترفع
[ ] ارفع مستند (PDF أقل من 100MB) → يرفع
[ ] ارفع ملف كبير جداً → رسالة خطأ بتظهر
[ ] خانة الـ Caption: اختياري
[ ] اشيل الميديا → Asset بيتحذف، الـ Node بترجع لحالة "رفع"
```

#### send_buttons (أزرار تفاعلية)
```
[ ] خانة الـ Body: مطلوب، أقصاه 1024 حرف
[ ] خانة الـ Header: اختياري، أقصاه 60 حرف
[ ] خانة الـ Footer: اختياري، أقصاه 60 حرف
[ ] أضف لحد 3 أزرار (أقصاه 3)
    → الزر الرابع → خطأ تحقق "أقصاه 3 أزرار"
[ ] كل زر: ID (بيتولّد تلقائياً) + نص العنوان
[ ] الـ Button IDs فريدة (مش مكررة)
[ ] كل زر محتاج Edge متوصّلة → خطأ تحقق لو ناقص
[ ] invalid_reply_behavior: release_to_fallback / repeat_prompt / end_run
```

#### send_list (قائمة تفاعلية)
```
[ ] خانة الـ Body: مطلوب
[ ] Button Label: مطلوب (نص زر "اعرض الخيارات")
[ ] أضف قسم بعنوان
[ ] أضف لحد 10 صفوف لكل قسم
    → الصف الـ 11 → خطأ تحقق
[ ] كل صف: ID + عنوان + وصف اختياري
[ ] الـ Row IDs فريدة
[ ] كل صف محتاج Edge متوصّلة → خطأ تحقق لو ناقص
[ ] invalid_reply_behavior: release_to_fallback / repeat_prompt / end_run
```

#### save_reply_to_field
```
[ ] هدف الحفظ: contact_field → يعرض Dropdown الحقول
[ ] هدف الحفظ: session_variable → يعرض خانة مفتاح المتغير
[ ] field_uuid فاضي → خطأ تحقق
[ ] variable_key فاضي → خطأ تحقق
```

#### condition
```
[ ] المصدر: last_user_message / selected_button_id / selected_list_row_id / contact_field / flow_variable
[ ] العملية: equals / not_equals / contains / filled / not_filled
[ ] المصدر = contact_field → Dropdown الحقول بيظهر
[ ] المصدر = flow_variable → خانة مفتاح المتغير بتظهر
[ ] لازم يكون ليها فرعين "matched" و"unmatched" → خطأ لو ناقص
```

#### add_to_group / remove_from_group
```
[ ] Dropdown المجموعات يعرض مجموعات الـ Org
[ ] اختر مجموعة → يتحقق
[ ] group_uuid فاضي → خطأ تحقق
```

#### update_contact_field
```
[ ] هدف الحفظ: contact_field أو session_variable
[ ] الوضع: save_last_user_message / static / last_input / session_variable
[ ] الوضع = static → خانة القيمة بتظهر
[ ] الوضع = session_variable → خانة مفتاح المتغير المصدر بتظهر
```

#### assign_to_agent
```
[ ] لو الـ Ticketing مش نشط: يعرض خطأ "فعّل الـ Ticketing أولاً"
[ ] وضع التعيين: auto_assign / specific_agent / unassigned
[ ] specific_agent → Dropdown الـ Agents بيظهر
[ ] ملحوظة: الـ Flow بيكمّل بعد الـ Node دي (مش بيوقف)
```

#### human_handoff
```
[ ] إعداد مشابه لـ assign_to_agent
[ ] ملحوظة: الـ Flow بيوقف عند الـ Node دي (waiting_handoff)
[ ] ما يتسمحلوش Edges صادرة → خطأ تحقق لو في Edges
```

#### handoff_to_ai_assistant
```
[ ] لو الـ AI Assistant مش نشط: يعرض خطأ
[ ] ما يتسمحلوش Edges صادرة
[ ] الـ Flow بيوقف عند الـ Node دي
```

#### send_email
```
[ ] الموضوع: مطلوب
[ ] الجسم: مطلوب
[ ] قسم SMTP:
    [ ] Host، Port، Username، Password، From Name، From Email
    [ ] كل الحقول مطلوبة للـ Secret المكتمل
    [ ] الـ Password مخفي
[ ] موضوع أو جسم فاضي → خطأ تحقق
[ ] إعداد SMTP ناقص → خطأ تحقق
```

#### delay
```
[ ] خانة الدقائق: رقم صحيح، أدناه 1
[ ] أدخل 0 → خطأ تحقق (لازم دقيقة على الأقل)
[ ] أدخل 5 → صالح
```

#### end
```
[ ] ما فيش حقول إعداد
[ ] ما يتسمحلوش Edges صادرة
```

### 2.6 نظام التحقق Validation

```
[ ] انشر من غير Nodes (بس trigger) → خطأ: "اختار الخطوة الأولى بعد الـ Trigger"

[ ] انشر مع Trigger بدون Edge صادرة → يعرض الخطأ

[ ] انشر مع Node منفصلة (مفيش مسار من الـ Trigger) →
    خطأ: "اشيل الخطوات المنفصلة أو وصّلها للمسار الرئيسي"

[ ] انشر مع مسار دائري (A → B → A) →
    خطأ: "اشيل المسارات الدائرية قبل النشر"

[ ] انشر مع Condition ناقصة فرع unmatched →
    خطأ: "الـ Condition محتاجة فرعي matched و unmatched"

[ ] انشر مع send_buttons وزر ما عنده Edge →
    خطأ: "وصّل كل رد زر بالخطوة التالية"

[ ] انشر مع End Node عندها Edge صادرة →
    خطأ: "خطوة النهاية ما المفروضش توصّل لأي خطوة تانية"

[ ] الأخطاء بتظهر في FlowReadinessPanel (فوق الصفحة)

[ ] الأخطاء بتظهر كشارات على الـ Node المحددة (FlowCanvasNodeRoutingHealth)

[ ] الضغط على Node الخطأ في لوحة الجاهزية → الـ Canvas بيتحرك للـ Node دي

[ ] اختبار حد الخطة:
    → اضبط flow_builder_nodes_per_flow_limit = 3 في الـ Plan Metadata
    → أضف 4+ Nodes → خطأ تحقق: "خطتك الحالية تسمح بـ 3 Nodes فقط"
```

### 2.7 الحفظ والحفظ التلقائي

```
[ ] عدّل نص Node → بعد 1.2 ثانية → طلب Network لـ /autosave
    → الـ Header يعرض "جار الحفظ..." ثم "تم الحفظ"

[ ] عدّل بسرعة (تغييرات كتير) → طلب حفظ واحد بس بعد آخر تغيير

[ ] فشل الحفظ (محاكاة Response 500) → الـ Header يعرض "خطأ في الحفظ"
    → ما في ضياع بيانات — التغييرات لسه ظاهرة في الـ UI

[ ] روّح من غير ما تحفظ →
    [ ] FlowExitConfirmModal بيظهر
    [ ] "تجاهل" → يخرج من غير حفظ
    [ ] "حفظ وخروج" → يحفظ ثم ينتقل
    [ ] إغلاق الـ Modal → يفضل في الـ Builder

[ ] إغلاق تاب المتصفح مع تغييرات غير محفوظة →
    → المتصفح يعرض Dialog "تريد المغادرة؟"

[ ] زر "حفظ" اليدوي (في الـ Header) → يرسل طلب PUT فوراً
```

### 2.8 نظام المعاينة Preview

```
[ ] اضغط "معاينة" في قائمة الـ Header → FlowPreviewModal يفتح

[ ] المعاينة بتعرض Timeline محادثة بأسلوب واتساب

[ ] Trigger Node → يعرض "الـ Flow بيبدأ لما رسالة واتساب مطابقة تيجي"

[ ] send_text → يعرض محتوى نص الرسالة

[ ] send_media → يعرض الـ Caption (ومعاينة الميديا لو Asset موجود)

[ ] send_buttons → يعرض الجسم + كل عناوين الأزرار
    → الزر الأول بيظهر كاختيار مستخدم محاكى

[ ] send_list → يعرض الجسم + القائمة → يحاكي اختيار أول صف

[ ] save_reply_to_field → يعرض "انتظار رد نصي حر واحفظه في ..."
    → رد مستخدم محاكى بيظهر

[ ] condition → يعرض "تحقق من قاعدة وروّح للفرع المطابق"
    → دايماً بياخد فرع "matched" في المعاينة

[ ] delay → يعرض "انتظر N دقيقة قبل الخطوة التالية"

[ ] end → يعرض "خلّص الرحلة"

[ ] سلوك الـ Focus: حدّد Node → المعاينة بتتمرير لـ Context الـ Node دي

[ ] عدّل الـ Graph → المعاينة بتتجدد تلقائياً (Debounce 220ms)
```

### 2.9 النشر ودورة الحياة

```
[ ] انشر Flow صالح:
    → الحالة تتغيّر من 'draft' لـ 'published'
    → شارة الـ Header تعرض "منشور"
    → علامة has_unpublished_changes تتمسح

[ ] حاول تنشر مع أخطاء تحقق:
    → زر النشر محجوب (أو الضغط يعرض الأخطاء)
    → ما يتنشأش Version

[ ] بعد النشر، عدّل Node:
    → الحالة تعرض "منشور (تغييرات غير محفوظة)" أو ما شابه
    → has_unpublished_changes = true

[ ] أعد النشر بعد التعديلات:
    → Version جديدة بتتنشأ (version_number بيزيد)
    → التغييرات بتبقى Live

[ ] وقّف Flow منشور مؤقتاً:
    → الحالة تتغيّر لـ 'paused'
    → الـ Runtime مش هيشغّل للرسايل الجديدة

[ ] استأنف Flow متوقف:
    → الحالة ترجع لـ 'published'
    → الـ Runtime يرجع يشتغل

[ ] القائمة بتعرض شارات الحالة الصح

[ ] حد Active Flows:
    → اضبط flow_builder_active_flows_limit = 1 في الخطة
    → انشر أول Flow → تمام
    → حاول تنشر تاني Flow → خطأ: "وصلت للحد الأقصى للـ Flows النشطة"
```

### 2.10 رفع الـ Assets

```
[ ] ارفع صورة على Node send_media:
    → Thumbnail بيظهر في كارد الـ Node
    → UUID الـ Asset بيظهر في Config الـ Node

[ ] ارفع فيديو:
    → أيقونة الفيديو بتظهر
    → Metadata الملف ظاهرة

[ ] ارفع ملف كبير جداً:
    → خطأ بيرجع من الـ Server
    → الـ Node لسه في حالة "رفع"

[ ] احذف الـ Asset من الـ Node:
    → الملف بيتحذف من الـ Storage
    → الـ Node ترجع لحالة "بدون ميديا"

[ ] بعد إعادة تحميل الصفحة:
    → الـ Asset لسه متوصّل
    → الـ Signed URL لسه شغّالة (صالحة 24 ساعة)

[ ] الـ Asset بيتقدّم عبر Signed URL:
    → GET /automation/flows/{uuid}/assets/{assetUuid}?signature=...&expires=...
    → يرجع Content-Type الصح
    → يرجع 403 لو الـ Signature غلط
    → يرجع 403 لو منتهي الصلاحية
```

### 2.11 أسرار SMTP (send_email)

```
[ ] أضف Node send_email
[ ] اكمّل إعدادات SMTP → بتتحفظ كـ Secret مشفّر في الـ DB
    → تحقق: SELECT node_type, node_id FROM automation_flow_node_secrets;

[ ] احفظ المسودة → الـ Secret بيتزامن في الـ DB
    (graph_json فيها secret_ref، مش البيانات الحقيقية)

[ ] أعد تحميل الـ Builder → فورم SMTP بيعرض "متصل" (عرض فقط، مش كلمة السر)

[ ] انسخ الـ Flow → الأسرار بتتنسخ في الـ DB بـ UUIDs جديدة

[ ] احذف الـ Email Node → الـ Secret المفروض يتنظّف من الـ DB
    (تحقق إن ده بيحصل فعلاً في AutomationFlowNodeSecretService::sanitizeGraphAndSyncSecrets)
```

### 2.12 تنفيذ الـ Flow في الـ Runtime

*محتاج حساب واتساب متصل وقدرة على إرسال رسايل.*

```
[ ] اختبار: trigger any_incoming
    → انشر Flow بـ trigger: any_incoming
    → ابعت أي رسالة من واتساب لرقم البوت
    → البوت يرد برسالة أول Node

[ ] اختبار: trigger keyword_match
    → انشر Flow بكلمة مفتاحية: "سعر"
    → ابعت رسالة "سعر" → الـ Flow بيبدأ
    → ابعت رسالة "أهلاً" → الـ Flow ما بيبدأش (Trigger مختلف)
    → ابعت رسالة "عايز أعرف السعر" → الـ Flow بيبدأ (Substring Match)

[ ] اختبار: trigger first_in_conversation
    → Contact جديد يبعت أول رسالة → الـ Flow بيبدأ
    → نفس الـ Contact يبعت رسالة تانية → الـ Flow ما بيبدأش تاني

[ ] اختبار: send_text
    → البوت يبعت رسالة نصية صحيحة
    → Personalization: {first_name} بيتستبدل باسم الـ Contact

[ ] اختبار: send_buttons
    → البوت يبعت رسالة أزرار تفاعلية
    → اضغط زر "خيار أ" → الـ Flow يكمّل على فرع خيار أ

[ ] اختبار: رد غير صالح على الأزرار
    → البوت يبعت الأزرار
    → ابعت نص عادي بدل ما تضغط الزر
    → السلوك حسب invalid_reply_behavior:
        - release_to_fallback: الرسالة بتظهر في الشات العادي
        - repeat_prompt: الأزرار بتتبعت تاني
        - end_run: الـ Flow بيتلغى

[ ] اختبار: send_list
    → البوت يبعت رسالة القائمة
    → اختار صف القائمة → الـ Flow يكمّل على فرع الصف ده

[ ] اختبار: save_reply_to_field
    → البوت يسأل سؤال
    → الـ Contact يرد بنص
    → الحقل بيتحدث في قاعدة البيانات:
       SELECT * FROM contact_meta WHERE contact_id=X AND field_id=Y;

[ ] اختبار: condition (contact_field)
    → الـ Contact عنده قيمة حقل "VIP"
    → condition: contact_field equals "VIP" → بياخد فرع matched

[ ] اختبار: add_to_group
    → شغّل الـ Flow مع Node add_to_group
    → الـ Contact بيظهر في المجموعة المستهدفة

[ ] اختبار: assign_to_agent
    → Ticket بيتنشأ/يتعيّن
    → الـ Flow بيكمّل بعد التعيين

[ ] اختبار: human_handoff
    → Ticket بيتنشأ/يتعيّن
    → الـ Flow بيوقف (waiting_handoff)
    → تحقق: SELECT status FROM automation_flow_runs WHERE contact_id=X;

[ ] اختبار: End Node
    → Run بيتعلّم 'completed'
    → ما في رسايل تانية بتتبعت

[ ] اختبار: مستخدمين متزامنين
    → ابعت رسايل من Contact A وContact B في نفس الوقت
    → كل Contact يجي له Run مستقل (ما في تداخل)
```

### 2.13 استئناف الـ Delay Node (Queue)

*محتاج Queue Worker شغّال.*

```
[ ] أنشئ Flow: trigger → send_text → delay(1 دقيقة) → send_text → end
[ ] انشر الـ Flow
[ ] ابعت رسالة Trigger من واتساب
    → أول send_text وصل ✓
[ ] تحقق من حالة الـ Run:
    SELECT status, waiting_for, next_resume_at FROM automation_flow_runs WHERE contact_id=X;
    → status='waiting_delay', waiting_for='delay'

[ ] انتظر دقيقة أو أكثر
    → تاني send_text وصل ✓
    → حالة الـ Run = 'completed'

[ ] تحقق إنه مفيش Infinite Loop:
    SELECT COUNT(*) FROM automation_flow_run_steps WHERE automation_flow_run_id=X;
    → المفروض تكون 4 خطوات بالظبط (trigger, send_text, delay, send_text/end)
    → مش خطوات delay بتتكرر

[ ] اختبار: Delays متعددة في تسلسل
    → trigger → delay(1 دقيقة) → delay(2 دقيقة) → end
    → كل delay بتشتغل مرة واحدة بالتوقيت الصح

[ ] اختبار: إلغاء الـ Flow أثناء الـ Delay
    → Run في حالة waiting_delay
    → وقّف/أوقف نشر الـ Flow
    → انتظر انتهاء التأخير
    → resumeDelayedRun() المفروض يلغي (الـ Flow مش منشور)
    → حالة الـ Run = 'cancelled'

[ ] اختبار: Queue Worker معطّل أثناء الـ Delay
    → أوقف الـ Worker
    → ابعت رسالة Trigger → الـ Run يدخل waiting_delay
    → أعد تشغيل الـ Worker
    → الـ Job بيشتغل (لو ما انتهتش صلاحيته)
```

### 2.14 النسخ والحذف

```
[ ] انسخ Flow من Row Menu في القائمة:
    → Flow جديد بيتنشأ مع لاحقة "نسخة" أو "Copy"
    → كل الـ Nodes/Edges محفوظة
    → الـ Assets بتتنسخ (UUIDs جديدة، ملفات فيزيائية جديدة)
    → الأسرار بتتنسخ (سجلات مشفّرة جديدة)
    → بيحوّل للـ Builder بتاع الـ Flow الجديد
    → الـ Flow الأصلي ما اتغيّرش

[ ] نسخ من قائمة "المزيد" في الـ Builder:
    → نفس السلوك
    → لو في تغييرات غير محفوظة: يطلب الحفظ أولاً

[ ] احذف Flow من القائمة:
    → Dialog تأكيد بيظهر
    → الـ Flow بيتحذف ناعم (deleted_at بتتضبط)
    → بيتشال من القائمة فوراً

[ ] احذف Flow من الـ Builder:
    → Dialog تأكيد
    → حذف → بيحوّل لـ /automation/flows

[ ] الـ Flow المحذوف مش متاح:
    → URL المباشر /automation/flows/{uuid} → 404
```

---

## 3. الـ Edge Cases

### الـ Edge Cases الخاصة بهيكل الـ Graph

```
[ ] Flow مع trigger وend فقط (أدنى Flow صالح)
    → المفروض يتحقق وينشر

[ ] Flow مع trigger → condition → فرع matched → end
    → فرع unmatched ما عندوش وصلة → المفروض يفشل التحقق

[ ] Flow بـ 80 Node (الحد الأقصى)
    → المفروض يشتغل
[ ] Flow بـ 81 Node
    → خطأ تحقق: "تجاوز الحد الأقصى للـ Nodes"

[ ] نص رسالة طوله 1024 حرف في الجسم
    → تحقق امتثال واتساب المفروض ينجح

[ ] نص رسالة أكثر من 1024 حرف
    → خطأ امتثال واتساب

[ ] 3 أزرار (الحد الأقصى)
    → صالح
[ ] عنوان زر أكثر من 20 حرف
    → خطأ امتثال واتساب

[ ] Trigger بـ 0 Keywords في وضع keyword_match
    → خطأ تحقق: "أضف كلمة مفتاحية على الأقل"

[ ] Flow تانيان كلاهما مضبوطين على any_incoming
    → كلاهما منشوران → الـ Runtime بيعالج أول Flow مطابق (حسب updated_at DESC)
    → الـ Flow التاني ما بيشتغلش لو الـ Contact بالفعل في Run
```

### الـ Edge Cases الخاصة بالـ Runtime

```
[ ] الـ Contact عنده Run نشط → رسالة Trigger جديدة تيجي
    → resumeWaitingRun() بتتعامل مع الأمر لو كان waiting_input
    → أو لو كانت حالة active: startRun() بترجع false (الـ Run الموجود بيمنع البداية)

[ ] رسالة Contact وصلت بعد 31 دقيقة من آخر نشاط (حد التقادم)
    → الـ Run بيتقادم تلقائياً
    → Run جديد بيبدأ من الأول

[ ] Contact بيضغط زر من رسالة قديمة (مثلاً بعد 25 ساعة)
    → الـ Run في waiting_input ممكن يكون تقادم (1440 دقيقة = 24 ساعة)
    → الـ Run المتقادم بيتمسح → Run جديد بيبدأ (أو بيعدي)

[ ] Delay Node مضبوطة على 0 دقائق
    → max(1, 0) = 1 → بيستخدم الحد الأدنى دقيقة واحدة

[ ] Node send_media مع Asset محذوف
    → resolveMediaUrl بيرجع null → الـ Run بيفشل مع السبب

[ ] الـ Flow بيتوقف نشره وفي Run في حالة waiting_delay
    → الـ Job بيشتغل → resumeDelayedRun بيتحقق إن flow.status != 'published' → يلغي الـ Run

[ ] نافذة Customer Care لواتساب مغلقة
    → Node send_text بتوصل
    → on_window_closed = 'fail_run': الـ Run بيفشل
    → on_window_closed = 'release_to_fallback': الـ Run بيتلغى، الرسالة بتعدي للشات
```

---

## 4. سيناريوهات الفشل

### اختبر هذه الحالات

| السيناريو | السلوك المتوقع |
|---|---|
| فشل طلب الحفظ (500) | `saveState = 'error'`، ما في ضياع بيانات في الـ UI |
| نشر Graph غير صالح | أخطاء التحقق بترجع، ما بيتنشأش Version |
| رفع Asset بـ MIME غير صحيح | Server بيرجع خطأ، الـ Node بتفضل فاضية |
| Asset أكبر من الحد | Server بيرجع 413، الـ UI يعرض خطأ |
| بيانات SMTP غلط | الإيميل بيفشل في الـ Runtime، الـ Run بيتعلّم failed |
| WhatsApp API بيرجع خطأ | Step الـ Run بيتسجّل مع الخطأ، الـ Run بيتعلّم failed |
| Redis معطّل | قفل الـ Contact بيتخطّى (Fallback غير آمن)، الـ Run بيكمّل |
| Queue Worker معطّل | الـ Runs المتأخرة بتتراكم في الـ Queue، بتتنفّذ لما الـ Worker يرجع |
| Contact بيتحذف في نص الـ Run | `Contact::find()` بيرجع null → الـ Run بيفشل بشكل سلس |
| Flow بيتحذف قوي أثناء Run | Cascade بيحذف الـ Run (ما في سجلات يتيمة) |

---

## 5. مصفوفة اختبار الـ Runtime

لكل نوع Node بيبعت رسالة واتساب أو بيعدّل بيانات:

| الـ Node | العملية | التغيير المتوقع في الـ DB | الحدث المتوقع في واتساب |
|---|---|---|---|
| trigger | رسالة وصلت | `automation_flow_run` بتتنشأ | — |
| send_text | تنفيذ | `run_step` بيتنشأ (status=executed) | الرسالة وصلت للـ Contact |
| send_media | تنفيذ | `run_step` بيتنشأ | رسالة ميديا وصلت |
| send_buttons | أول زيارة | `run_step` (status=waiting)، run=waiting_input | الأزرار وصلت للـ Contact |
| send_buttons | المستخدم ضغط | `run_step` (status=executed)، run=active | — |
| send_list | أول زيارة | `run_step` (status=waiting)، run=waiting_input | القائمة وصلت للـ Contact |
| send_list | المستخدم اختار | `run_step` (status=executed)، run=active | — |
| save_reply_to_field | تنفيذ | حقل الـ Contact اتحدث أو متغير Session اتضبط | — |
| condition | matched | `run_step` (branch=matched) | — |
| condition | unmatched | `run_step` (branch=unmatched) | — |
| add_to_group | تنفيذ | سجل contact_group_contact بيتنشأ | — |
| remove_from_group | تنفيذ | سجل contact_group_contact بيتشال | — |
| update_contact_field | تنفيذ | contact_meta بيتحدث | — |
| assign_to_agent | تنفيذ | Ticket بيتنشأ/يتعيّن | — |
| human_handoff | تنفيذ | Ticket بيتنشأ، run=waiting_handoff | — |
| delay | أول زيارة | run=waiting_delay، Job بيتوزّع | — |
| delay | استئناف | `run_step` (reason=delay_completed) | — |
| end | تنفيذ | run=completed، completed_at بيتضبط | — |

---

## 6. مقترحات الـ Automated Tests

### PHP Unit Tests

```php
// AutomationFlowGraphValidatorTest.php
test('التحقق يفشل من غير Trigger Node')
test('التحقق يفشل مع Trigger Nodes اتنين')
test('التحقق يفشل مع Node غير وصّالة')
test('التحقق يفشل مع مسار دائري')
test('التحقق يفشل مع Condition ناقصة فرع unmatched')
test('التحقق ينجح مع Flow صالح بسيط: trigger → send_text → end')
test('التحقق يمنع الـ Advanced Nodes لو الخطة معطّلة')
test('التحقق يطبّق حد عدد الـ Nodes')

// AutomationFlowGraphCompilerTest.php
test('الـ Adjacency المُجمَّع بيطابق الهيكل المتوقع')
test('الـ Compiler بيفلتر الـ Edges غير الصالحة')
test('الـ Compiler بيعمل Map للـ Nodes بالـ ID')
test('الـ Compiler بيتعامل مع Graph فاضي بسلاسة')

// AutomationFlowRuntimeServiceTest.php
test('trigger any_incoming يطابق رسالة مش فاضية')
test('trigger any_incoming ما يطابقش رسالة فاضية')
test('trigger keyword_match يطابق رسالة تحتوي على الكلمة')
test('trigger keyword_match Case Insensitive')
test('trigger first_in_conversation بيطابق أول شات فقط')
test('قفل الـ Contact بيمنع التنفيذ المتزامن')
test('الـ Run المتقادم بيتمسح قبل بدء Run جديد')
test('Delay Node بتوقّف الـ Run وتوزّع Job')
test('استئناف الـ Delay ما بيعملش Infinite Loop')  // ← الإصلاح الحرج
test('استئناف الـ Delay بيقدّم للـ Node التالية صح')
test('send_buttons بتوقّف الـ Run في انتظار الزر')
test('رد زر صالح يستأنف الـ Run على الفرع الصح')
test('رد غير صالح مع repeat_prompt يعيد إرسال الأزرار')
test('رد غير صالح مع end_run يلغي الـ Run')
test('رد غير صالح مع release_to_fallback يرجع false')
test('Condition تقيّم عملية equals صح')
test('Condition تقيّم عملية contains صح')
test('أقصاه 60 خطوة ينهي الـ Run بحالة failed')
test('الـ Run بيفشل بسلاسة لو الـ Contact بيتحذف')

// AutomationFlowBuilderServiceTest.php
test('إنشاء بيستخدم القالب الجاهز لـ Goal Preset')
test('النشر بينشئ Version جديدة برقم صحيح')
test('النشر بيزيد runs_count على الـ Flow صح')
test('النسخ ينسخ الـ Assets بـ UUIDs جديدة')
test('النسخ ينسخ الأسرار بمراجع جديدة')
```

### Feature/Integration Tests

```php
// AutomationFlowControllerTest.php
test('GET /automation/flows بيرجع 200 مع قائمة الـ Flows')
test('POST /automation/flows ينشئ Flow وبيحوّل')
test('GET /automation/flows/{uuid} بيرجع كامل بيانات الـ Builder')
test('PUT /automation/flows/{uuid} بيحفظ المسودة')
test('POST .../validate بيرجع تقرير التحقق')
test('POST .../publish بينشئ Version ويفعّل الـ Flow')
test('POST .../pause بيبدّل حالة الـ Flow')
test('POST .../duplicate ينشئ نسخة')
test('DELETE /automation/flows/{uuid} بيحذف ناعم')
test('POST .../assets بيحفظ الملف المرفوع')
test('DELETE .../assets/{assetUuid} بيشيل الملف')
test('featureGuard بيحوّل لو الـ Addon معطّل')
test('featureGuard بيمنع لو الـ Schema مش جاهز')
test('مستخدم غير مصرّح يجي له 403')

// ResumeAutomationFlowRunJobTest.php
test('الـ Job بيستدعي resumeDelayedRun بالـ Run الصح')
test('الـ Job بيبقى Noop لو الـ Run مش في waiting_delay')
```

### JavaScript Tests (Vitest/Jest)

```javascript
// flowBuilderValidation.test.js
test('buildNodeErrors بيرجع خطأ لـ send_text فاضي')
test('buildNodeErrors بيرجع خطأ للأزرار بدون Branches')
test('buildValidationSummary بيكتشف مسار دائري')
test('buildValidationSummary بيكتشف Node منفصلة')

// flowBuilderDraft.test.js
test('cloneFlowValue بيعمل Deep Clone بدون مشاركة Reference')
test('makeFlowBuilderUuid بيولّد UUIDs فريدة بـ Prefix')
test('defaultNodeConfig بيرجع الـ Defaults الصحيحة لكل نوع')
test('buildFlowEdge بينشئ هيكل Edge صحيح')

// flowBuilderGraph.test.js
test('normalizeTriggerStart يضمن إن الـ Trigger هو start_node_id')
test('pruneOutgoingBranches بيشيل الـ Edges للـ Button IDs المحذوفة')
```

### Playwright E2E Tests

المشروع عنده بالفعل `tests/Playwright/flow-builder-canvas.spec.js`. وسّعه بـ:

```javascript
test('إنشاء Flow، إضافة Node، حفظ، نشر من الأول للآخر')
test('سحب Node من المكتبة على الـ Canvas')
test('توصيل Node اتنين بسحب المقبض')
test('إدراج Node على Edge بزر +')
test('حذف Node يمسح Edges بتاعتها')
test('المعاينة بتعرض Timeline صحيح لـ Flow بسيط')
test('أخطاء التحقق بتظهر في لوحة الجاهزية')
test('الحفظ التلقائي بيشتغل بعد تعديل نص الـ Node')
```

---

## 7. Checklist الجاهزية للـ Production

```
البنية التحتية:
[ ] Redis مضبوط ومتاح (CACHE_DRIVER=redis)
[ ] Queue Worker مضبوط بـ Supervisor للإعادة التلقائية
    أمر الـ Worker: php artisan queue:work --queue=automation-flow-resume,default --tries=3 --timeout=60
[ ] Storage Disk قابل للكتابة (storage/app/)
[ ] في الـ Production: فكّر في S3 للـ Assets بدل الـ Local Disk
[ ] APP_KEY مضبوط (لتشفير أسرار الـ Nodes)

قاعدة البيانات:
[ ] كل الـ 6 جداول Automation بعد الـ Migration
[ ] Indexes متحقق منها (افحص EXPLAIN على استعلامات الـ Runtime)
[ ] سجل الـ Addon مفعّل: status=1, is_active=1
[ ] كل Organization عندها اشتراك نشط مع إضافة Flow builder

المراقبة:
[ ] مراقبة تراكم الـ Queue (Horizon أو Dashboard مخصص)
[ ] تنبيه على عدد failed_jobs العالي
[ ] تنبيه على الـ Runs العالقة في waiting_delay بعد next_resume_at
[ ] تسجيل استدعاءات WhatsApp API البطيئة

الأمان:
[ ] APP_KEY قوي وغير مكشوف
[ ] Signed Asset URLs شغّالة صح (ثبات APP_KEY)
[ ] node_secrets.payload_json مشفّر (Crypt::encrypt)
[ ] ما في بيانات SMTP في الـ Application Logs

الأداء:
[ ] فكّر في إضافة MySQL Index على automation_flow_runs(contact_id, organization_id, status)
[ ] لو في حجم عالي: فكّر في Partition لـ automation_flow_run_steps حسب التاريخ
[ ] اختبر مع 1000+ Run نشط في نفس الوقت

Feature Flags:
[ ] FLOW_BUILDER_V2_ENFORCE_CUSTOMER_CARE_WINDOW=true (افتراضي Production)
[ ] FLOW_BUILDER_V2_ON_WINDOW_CLOSED=release_to_fallback (موصى به للـ Production)
[ ] FLOW_BUILDER_V2_ALLOW_EXTERNAL_ACTIONS=false (إلا لو send_email محتاج)

اختبار Smoke بعد النشر:
[ ] أنشئ Flow اختباري (trigger + send_text + end)
[ ] انشره
[ ] ابعت رسالة واتساب للبوت
[ ] تحقق إن الرد وصل
[ ] افحص automation_flow_runs: status=completed
[ ] افحص automation_flow_run_steps: 3 صفوف (trigger, send_text, end)
[ ] احذف الـ Flow الاختباري
```

---

*تم توليده من تحليل الكود المصدري — 2026-05-31*
