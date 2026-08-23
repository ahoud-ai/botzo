# تقرير تدقيق شامل — نظام إدارة الاشتراكات والفوترة (Subscriptions & Billing)

**الصفحة المستهدفة:** `/billing` (Inertia + Vue) — Stack: Laravel 12 + Inertia.js + Moyasar Gateway
**تاريخ التقرير:** 2026-06-16
**نوع المراجعة:** Static Code Audit + Live DB Integrity Audit (باستخدام أوامر Artisan الموجودة فعليًا في المشروع) — بدون اختبار Live عبر متصفح لعدم توفر بيانات اعتماد اختبار.

> ملاحظة منهجية مهمة: هذه الصفحة **ليست REST API منفصل**. هي صفحات Laravel + Inertia.js، يعني الطلبات بترجع *redirect + flash session data* مش JSON موحّد بأكواد حالة (status codes) متباينة بين النجاح والفشل. تم توضيح هذا التأثير بالتفصيل في القسم 4.

---

## فهرس المحتوى

1. [تحليل النظام الحالي](#1-تحليل-النظام-الحالي)
2. [مراجعة الفواتير — السيناريوهات الخمسة](#2-مراجعة-الفواتير--السيناريوهات-الخمسة)
3. [اختبار قاعدة البيانات](#3-اختبار-قاعدة-البيانات)
4. [اختبار "الـ API" (Routes/Controllers)](#4-اختبار-الـ-api-routescontrollers)
5. [اختبارات Edge Cases](#5-اختبارات-edge-cases)
6. [التقرير النهائي: الـ Lifecycle، الأخطاء، والتوصيات](#6-التقرير-النهائي)

---

## 1. تحليل النظام الحالي

### 1.1 الخريطة المعمارية

| الطبقة | الملفات |
|---|---|
| Controllers | `app/Http/Controllers/User/SubscriptionController.php`, `app/Http/Controllers/User/BillingController.php`, `app/Http/Controllers/PaymentController.php` |
| Business Logic (نشط/Live) | `app/Services/BillingCheckoutIntentService.php`, `app/Services/SubscriptionService.php` (جزء منه قديم) |
| Payment Gateway | `app/Services/MoyasarService.php` |
| Models | `Subscription`, `SubscriptionPlan`, `BillingInvoice`, `BillingPayment`, `BillingTransaction`, `BillingCheckoutIntent`, `BillingCredit`, `BillingDebit`, `Coupon` |
| Audit Tools (موجودة بالفعل في المشروع) | `app/Console/Commands/SystemBillingIntegrityAuditCommand.php`, `SystemSignupBillingAuditCommand.php` + الخدمتين `App\Services\System\BillingIntegrityAuditService` و `OnboardingBillingAuditService` |

**ملاحظة مهمة جدًا:** يوجد **مساران منفصلان** لمنطق الفوترة في الكود:

- **المسار الحديث/الفعلي (Live):** `BillingCheckoutIntentService` — هذا هو المسار الذي تستخدمه شاشة `/billing` فعليًا عبر `SubscriptionController::store()`.
- **مسار قديم (Legacy)**: دوال `SubscriptionService::store()` / `createBillingInvoice()` / `updateSubscriptionPlan()` / `activateSubscriptionIfInactiveAndExpiredWithCredits()`. هذا المسار لم يُحذف، ولا يزال يُستدعى في حالة واحدة حية: عند إتمام عملية **شحن رصيد (Top-up)** بينما يكون الاشتراك منتهيًا (راجع §6.4 البند B-2).

وجود مسارين لمنطق متطابق (حساب proration، إنشاء فاتورة، خصم الكوبون) هو خطر معماري بحد ذاته (راجع B-4).

### 1.2 كيف يتم جلب الباقة الحالية؟

`SubscriptionPlanLimitService::subscriptionForOrganization()` ([app/Services/SubscriptionPlanLimitService.php:23](app/Services/SubscriptionPlanLimitService.php#L23)):

- يجيب **كل** صفوف `subscriptions` الخاصة بـ `billing_owner` (المنظمة الأم في حالة الفروع — راجع `OrganizationHierarchyService`)، ثم يفرزها بالأولوية: (1) نشطة تشغيليًا أولًا، (2) أحدث `start_date`، (3) أحدث `valid_until`، (4) أكبر `id`. ثم يرجّع أول نتيجة.
- **ملاحظة تصميمية:** لا يوجد قيد `unique` على `organization_id` في جدول `subscriptions`. النظام لا يفترض "صف واحد فقط لكل منظمة" بل "أفضل صف" بالأولوية. هذا يفتح الباب نظريًا لتكرار صفوف نشطة (Race condition عند إنشاء أول اشتراك لمنظمتين طلبتا في نفس اللحظة) — راجع B-7.
- الحالة التشغيلية (`operationalStatus`) تُحسب **ديناميكيًا في كل قراءة** (لا تُخزَّن في عمود status بشكل تلقائي): إذا `valid_until` في الماضي → `expired` فورًا بدون أي Cron Job، حتى لو الحقل `status` لا يزال `active` في القاعدة. هذا تصميم سليم لعرض الحالة، لكنه **لا يُحدّث أي شيء فعليًا** (لا يبدّل الباقة المجدولة، لا يلغي الوصول إلا عبر `isOperationallyActive()` المستخدمة في `CheckSubscriptionStatus` middleware).

### 1.3 كيف يتم تنفيذ Upgrade؟

في `SubscriptionService::determinePlanChangeAction()` ([app/Services/SubscriptionService.php:131](app/Services/SubscriptionService.php#L131)):

- المقارنة تتم عبر **Tier Rank** (`SubscriptionPlan::tierRank()` من `metadata.tier_rank`، أو fallback لترتيب السعر داخل نفس الـ period فقط إذا لم يُحدَّد tier_rank — راجع B-6).
- إذا `selectedRank > currentRank` → `action = 'upgrade_now'` → **تفعيل فوري**.
- التنفيذ الفعلي: `SubscriptionController::store()` → `BillingCheckoutIntentService::createSubscriptionPurchaseIntent()` → دفع فعلي (Moyasar) أو إكمال مباشر إذا `amountDue == 0` → `completeIntent()` / `completeWithoutGateway()` → `updateLocalSubscriptionFromIntent()` التي **تحدّث نفس صف `subscriptions`** فورًا: `plan_id` الجديد، `start_date = now()`، `valid_until = now() + period جديدة بالكامل` (شهر/سنة كاملة من لحظة الترقية، وليس "تكملة الفترة القديمة").

### 1.4 كيف يتم تنفيذ Downgrade؟

- إذا `selectedRank < currentRank` → `action = 'downgrade_at_renewal'`.
- **لا يحدث أي تغيير فوري.** فقط `SubscriptionService::schedulePlanChangeAtRenewal()` ([app/Services/SubscriptionService.php:244](app/Services/SubscriptionService.php#L244)) يكتب `scheduled_plan_id` + `scheduled_plan_change_at = valid_until الحالي` على **نفس صف الاشتراك**.
- الباقة الحالية تستمر فعليًا حتى `valid_until`.
- **المشكلة الجوهرية (B-1):** لا يوجد أي آلية تلقائية (Cron/Job) تُطبّق هذا التغيير المجدول عند حلول `valid_until`. راجع التفاصيل الكاملة في §6.4.

### 1.5 هل تُفعَّل الباقة الجديدة فورًا أم عند الانتهاء؟

| الحالة | التوقيت |
|---|---|
| Upgrade (باقة أعلى Rank، أو سنوي بدل شهري) | **فوري** بعد نجاح الدفع |
| Downgrade (باقة أقل Rank) | **مُجدوَل** نظريًا عند `valid_until`، لكن **لا يُطبَّق تلقائيًا في الواقع** (Bug) |

### 1.6 ماذا يحدث للباقة القديمة بعد التغيير؟

لا يوجد سجل تاريخي مستقل لـ"الاشتراكات السابقة". صف `subscriptions` يُحدَّث (`UPDATE`) في مكانه (`upsertOperationalSubscription()` و `updateLocalSubscriptionFromIntent()`)، فتُفقد القيمة القديمة لـ `plan_id`/`valid_until` من هذا الجدول. **السجل التاريخي الوحيد** هو جدول `billing_invoices` (كل فاتورة تحمل `plan_id` كما كان وقت الشراء) — وهذا يكفي للمحاسبة، لكنه لا يصلح كـ"Subscription History" حقيقي (لا تواريخ بداية/نهاية لكل فترة باقة محفوظة بشكل مستقل).

### 1.7 هل يُحتفظ بالأيام المتبقية أم تُلغى؟

لا تُحفظ كـ "أيام". تتحول إلى **رصيد مالي (Credit)** فقط في حالة الـ Upgrade، عبر `calculateProratedAmount()`. عند الـ Downgrade، **لا يوجد أي proration على الإطلاق** — المستخدم يكمل باقته الحالية المدفوعة لحين `valid_until` بدون أي رد فلوس أو رصيد، وهذا منطقي تجاريًا (هو لسه مستخدم اللي دفعه).

### 1.8 هل يوجد Proration؟

نعم، **لكن فقط للترقية (Upgrade)**، وبطريقة "Credit-based" وليست "exact daily line-item" على طريقة Stripe:

```
amountPerDay = آخر فاتورة (lastInvoice.total) ÷ عدد أيام الفترة الكاملة (start_date → valid_until)
proratedCredit = amountPerDay × الأيام المتبقية الفعلية (الآن → valid_until)
amountDue = (سعر الباقة الجديدة + ضريبة) − proratedCredit − الرصيد المتوفر − خصم الكوبون
```

(الكود: [app/Services/SubscriptionService.php:593-640](app/Services/SubscriptionService.php#L593))

**نقطة قوة محققة:** تتبّعت معادلة الدفتر (Ledger) الكاملة في المسار الحي (`BillingCheckoutIntentService`) رياضيًا، والنتيجة أن **الرصيد يرجع لـ 0 تمامًا بعد كل عملية دفع ناجحة عبر البوابة** (`invoiceTotal = netAmount − couponAmount`، و`paidAmount` المحسوب يُلغي بالضبط الفرق). هذه نقطة تصميم سليمة ✅. لكن — راجع B-4 — نفس الحساب **غير متطابق** في المسار القديم.

---

## 2. مراجعة الفواتير — السيناريوهات الخمسة

### السيناريو 1: مستخدم على Basic ويريد الترقية إلى Pro أثناء الاشتراك

| البند | التفاصيل |
|---|---|
| **قبل العملية** | `subscriptions.status='active'`, `plan_id=Basic`, `valid_until` في المستقبل |
| **Backend** | `POST /subscription` → `determinePlanChangeAction` يرجّع `upgrade_now` → `planSelectionPreview` يحسب proration → `createSubscriptionPurchaseIntent` (status=`pending` لو amountDue>0) → `beginGatewayCheckout` → Redirect لصفحة Moyasar |
| **بعد الدفع** | Webhook/Callback → `completeIntent()` → `resolveOrCreateSubscriptionInvoice()` |
| **DB** | إنشاء صف جديد في `billing_checkout_intents` (status ينتقل pending→processing→completed)، صف جديد `billing_invoices` (plan_id=Pro)، صفوف `billing_tax_rates`، صف `billing_payments`، **3 صفوف** `billing_transactions` (invoice سالب، credit موجب لو فيه proration، payment موجب)، وتحديث صف `subscriptions` الموجود (لا إنشاء صف جديد) |
| **فاتورة جديدة؟** | نعم |
| **فاتورة قديمة؟** | لا تُلغى أو تُعدَّل — تبقى كما هي (سجل تاريخي ثابت) |
| **Refund/Credit؟** | لا Refund نقدي؛ فقط Credit داخلي (رصيد محسوب) يُخصم من فاتورة Pro الجديدة |
| **بعد العملية** | `plan_id=Pro`, `valid_until = now()+period كاملة`, `scheduled_plan_id=null` |

### السيناريو 2: مستخدم على Pro ويريد الرجوع لـ Basic

| البند | التفاصيل |
|---|---|
| **قبل العملية** | `plan_id=Pro`, نشط |
| **Backend** | `determinePlanChangeAction` → `downgrade_at_renewal` → `schedulePlanChangeAtRenewal()` فقط |
| **DB** | **تحديث واحد فقط**: `subscriptions.scheduled_plan_id = Basic`, `scheduled_plan_change_at = valid_until الحالي`. **لا فاتورة، لا دفعة، لا transaction** |
| **فاتورة جديدة؟** | لا (ولن تُنشأ تلقائيًا عند الموعد أيضًا — Bug B-1) |
| **بعد العملية** | المستخدم يستمر على Pro حتى `valid_until`. الواجهة تعرض بانر "Downgrade scheduled" مع زر إلغاء (`/subscription/scheduled-change/cancel`) |
| **⚠️ خطر فعلي** | عند حلول `valid_until` فعليًا، **لا شيء يحدث تلقائيًا**. الحالة التشغيلية تتحول إلى `expired` (لأن `valid_until` فات)، ويفقد المستخدم الوصول، رغم أنه "جدول" تخفيض الباقة لا إلغاءها بالكامل. هذا تناقض مباشر مع توقّع المستخدم. |

### السيناريو 3: مستخدم لديه "فاتورة غير مدفوعة" ثم يحاول تغيير الباقة

**ملاحظة معمارية حرجة:** هذا النظام **لا يُنشئ صف `billing_invoices` إلا بعد نجاح الدفع** (`resolveOrCreateSubscriptionInvoice` تُستدعى فقط من داخل `completeIntent`/`completeWithoutGateway` الناجحين). لذلك **لا يوجد مفهوم "فاتورة غير مدفوعة" في هذا المخطط** كما في أنظمة مثل Stripe (لا توجد حالة `invoice.status = 'pending'`). أقرب تمثيل فعلي هو: صف `billing_checkout_intents` بحالة `pending` أو `processing` (طلب دفع بدأ ولم يكتمل).

| البند | التفاصيل |
|---|---|
| **قبل العملية** | يوجد `billing_checkout_intents` بحالة `processing` (مثلاً ضغط Upgrade ثم تركها صفحة الدفع مفتوحة) |
| **يحاول تغيير الباقة مرة ثانية** | `SubscriptionController::store()` **لا يفحص وجود intent معلّق من الأساس** ([app/Http/Controllers/User/SubscriptionController.php:88](app/Http/Controllers/User/SubscriptionController.php#L88)) → يُنشئ intent **ثانٍ** مستقل تمامًا |
| **النتيجة** | فتورتين/طلبي دفع منفصلين على Moyasar لنفس المنظمة في نفس الوقت. لو المستخدم دفع الاثنين بالخطأ (نسي تبويب قديم مفتوح) → **دفعتين فعليتين**، لكن `completeIntent` لكل intent مُحمي بـ `lockForUpdate` + idempotency check (`status==='completed'` يرجّع نجاح بدون تكرار)، فلن يُكرَّر تطبيق نفس intent مرتين، **لكن سيُطبَّق intent #1 ثم intent #2** كل بفاتورته وعملية الدفع الخاصة به — أي **المستخدم يدفع مرتين فعليًا بدون داعٍ**. |
| **دليل حي من قاعدة البيانات الحالية** | تشغيل `php artisan system:signup-billing-audit` كشف فعليًا عن **`stuck_moyasar_processing_intents = 1`** في القاعدة الحالية — أي يوجد الآن intent عالق بحالة `processing` لم يُسوَّ. هذا تأكيد عملي على وجود هذا السيناريو في البيئة الحالية، وليس افتراضًا نظريًا. |

### السيناريو 4: مستخدم غيّر الباقة عدة مرات متتالية

- **Upgrade متكرر:** كل عملية upgrade تُنشئ فاتورة ودفعة جديدتين فوريًا، وتحسب الـ proration بالاعتماد على **آخر فاتورة فقط** (`BillingInvoice::orderBy('id','desc')->first()`) — متّسق رياضيًا، لكن المستخدم **يدفع السعر الكامل للباقة الجديدة في كل مرة منقوصًا فقط الأيام المتبقية من الباقة السابقة (المدفوعة بالكامل أيضًا)**. لو غيّر 3 مرات في نفس اليوم، يدفع 3 فواتير كاملة تقريبًا (مع كريدت صغير جدًا في كل مرة). هذا ليس Bug تقني، لكنه قرار Business Logic يستحق مراجعة (راجع التوصيات).
- **Downgrade متكرر:** كل استدعاء لـ `schedulePlanChangeAtRenewal()` يعمل `UPDATE` غير شرطي على `scheduled_plan_id` — آخر طلب يفوز (override صحيح وآمن، لا تراكم أخطاء).
- **Upgrade بعد Downgrade مجدوَل:** لو المستخدم جدول downgrade لـ Basic، ثم غيّر رأيه واختار Pro (أعلى من الحالي): يدخل في فرع جديد كليًا `upgrade_now` بحساب proration طبيعي، و `updateLocalSubscriptionFromIntent` يصفّر `scheduled_plan_id = null` تلقائيًا ✅ سليم.

### السيناريو 5: مستخدم ألغى الاشتراك ثم أعاد الاشتراك

**🔴 اكتشاف جوهري:** زر/إجراء "إلغاء الاشتراك" **غير منفّذ على الإطلاق**.

```php
// app/Http/Controllers/User/SubscriptionController.php:238
public function destroy($id)
{
    // Your logic for deleting a specific resource
}
```

`Route::resource('subscription', ...)` يسجّل `DELETE /subscription/{id}` فعليًا تشير لهذه الدالة، لكنها **دالة فاضية**. أي استدعاء لها سيرجع نجاح (HTTP 200/302) بدون تنفيذ أي منطق — لا تغيير حالة، لا إلغاء وصول، لا أثر في القاعدة. هذا الموجود الوحيد القريب من "الإلغاء" هو `cancelScheduledChange()` — وهي **تُلغي تغيير باقة مجدوَل (Downgrade)**، وليست إلغاء الاشتراك نفسه — قد يحدث خلط هنا. راجع B-3 في القسم الأخير لتفاصيل الأثر والإصلاح المقترح.

---

## 3. اختبار قاعدة البيانات

### 3.1 الجداول والعلاقات

```
organizations 1───* subscriptions *───1 subscription_plans (plan_id, scheduled_plan_id)
organizations 1───* billing_invoices (plan_id ← subscription_plans, لا FK فعلي)
organizations 1───* billing_payments (FK غير موجود) ──0..1 invoice_id → billing_invoices
organizations 1───* billing_transactions (entity_type: payment|invoice|credit|debit, entity_id بدون FK لأنه Polymorphic)
organizations 1───* billing_checkout_intents ──0..1 target_plan_id, coupon_id, completed_invoice_id, completed_payment_id
billing_invoices 1───* billing_tax_rates
coupons 1───* billing_checkout_intents / billing_invoices (بدون FK حقيقي)
```

| الجدول | مفاتيح أجنبية (Foreign Keys) حقيقية في الـ Migration؟ |
|---|---|
| `subscriptions.organization_id` | ✅ `onDelete('cascade')` إلى `organizations` |
| `subscriptions.scheduled_plan_id` | ✅ `nullOnDelete()` إلى `subscription_plans` |
| `subscriptions.plan_id` | ❌ **بدون FK** (عمود `unsignedBigInteger` فقط) |
| `billing_invoices.organization_id` / `plan_id` | ❌ **بدون FK** (عمود `integer` عادي) |
| `billing_payments.organization_id` / `invoice_id` | ❌ **بدون FK** |
| `billing_transactions.organization_id` / `entity_id` | ❌ **بدون FK** (مفهوم لأن `entity_id` Polymorphic، لكن `organization_id` لا عذر له) |
| `billing_checkout_intents.*_id` (organization/plan/coupon/invoice/payment) | ❌ **بدون FK** على أي منها رغم وجودها كـ`unsignedBigInteger` |

**الخلاصة:** كل الجداول المالية (Invoices/Payments/Transactions/Checkout Intents) **بلا أي قيد مرجعي على مستوى القاعدة**. هذا يجعل تكامل البيانات يعتمد بالكامل على "حُسن نية" الكود التطبيقي — ولهذا بالذات تم بناء أمري `system:billing-integrity-audit` و `system:signup-billing-audit` كحل تعويضي (Compensating Control) بعد وقوع المشكلة، لا كمنع وقوعها. راجع B-8.

### 3.2 ماذا يُنشأ/يُعدَّل فعليًا عند تغيير الباقة (تتبّع فعلي من الكود)

**عند Upgrade ناجح (دفع عبر البوابة):**
1. `INSERT billing_checkout_intents` (status=pending) → `UPDATE` (status=processing, ثم completed)
2. `INSERT billing_payments`
3. `INSERT billing_invoices`
4. `INSERT billing_tax_rates` × (عدد الضرائب الفعّالة)
5. `INSERT billing_transactions` (entity_type=invoice, amount سالب)
6. `INSERT billing_credit` + `INSERT billing_transactions` (entity_type=credit) — فقط لو فيه proration > 0
7. `INSERT billing_transactions` (entity_type=payment, amount موجب)
8. `UPDATE subscriptions` (نفس الصف — plan_id/valid_until/start_date/scheduled_plan_id)
9. `coupons.quantity_redeemed++` لو فيه كوبون

**عند Downgrade (جدولة فقط):**
1. `UPDATE subscriptions` (`scheduled_plan_id`, `scheduled_plan_change_at`) — **فقط**. 8 خطوات أعلاه لا تحدث على الإطلاق حتى لحظة `valid_until`.

### 3.3 نتائج تشغيل أدوات التدقيق الموجودة (على القاعدة الحالية فعليًا)

تم تشغيل الأمرين الجاهزين في المشروع مباشرة (Read-only، بدون `--apply-safe-fixes`):

```
$ php artisan system:billing-integrity-audit --format=text
status: review_required
before total issues: 13
 - main_orgs_without_valid_plan        => 5
 - orphan_branches                     => 8
 - branches_with_standalone_subscriptions => 0
 - subscriptions_with_missing_current_plan => 0
 - subscriptions_with_missing_scheduled_plan => 0
 - expired_subscriptions_still_marked_active => 0
 - duplicate_payment_groups             => 0
 - payments_linked_to_foreign_invoice    => 0
 - invoice_payment_link_candidates       => 0 (auto-fixable)

$ php artisan system:signup-billing-audit --format=text --days=90
status: safe_fixes_available
before total issues: 1
 - auto_provisioned_unpaid_no_usage      => 0 (auto-fixable)
 - auto_provisioned_unpaid_with_usage    => 0
 - stuck_moyasar_processing_intents      => 1 (auto-fixable)
 - gateway_configuration_warnings        => 0
```

**تحليل النتائج:**
- **`main_orgs_without_valid_plan = 5`**: 5 منظمات رئيسية بلا اشتراك صالح حاليًا (طبيعي جزئيًا لو فيه تجارب/Trials منتهية، لكن يستحق تأكيد بشري).
- **`orphan_branches = 8`**: 8 فروع منظمات بدون منظمة أصل صحيحة في تسلسل الفوترة (`OrganizationHierarchyService`) — قد يكون له أثر مباشر على من يُحاسَب فعليًا لهذه الفروع.
- **`stuck_moyasar_processing_intents = 1`**: تأكيد مباشر للسيناريو 3 أعلاه (intent دفع عالق فعليًا الآن).
- الأمران يدعمان `--apply-safe-fixes` لإصلاح تلقائي لمشاكل محددة منخفضة الخطورة (مثل ربط دفعة بفاتورتها أو تسوية intent عالق) — **لم يتم تنفيذه في هذه المراجعة** لأنه يُعدّل بيانات حقيقية ويتطلب موافقتكم المباشرة.

---

## 4. اختبار "الـ API" (Routes/Controllers)

تذكير: الردود كلها Inertia (redirect + flash session)، وليست JSON بأكواد حالة متمايزة. الجدول التالي يوثّق العقد الحقيقي من الكود.

| الوظيفة المطلوبة | Route فعلي | Method | الـ Validation | الرد عند النجاح | الرد عند الفشل |
|---|---|---|---|---|---|
| Get Current Plan | `GET /billing` , `GET /subscription` | GET | — | `Inertia::render` مع `subscription`, `subscriptionDetails`, `scheduledPlanChange` | — (صفحة تعرض دائمًا، لا حالة فشل محددة) |
| Upgrade Plan | `POST /subscription` | POST | `StoreSubscriptionPurchaseRequest`: `plan` (مطلوب، يجب موجود وفعّال)، `method` (اختياري، لكن يصبح مطلوبًا منطقيًا لو amountDue>0 — **تحقق يدوي بعد الـ FormRequest** وليس Rule)، `coupon` (اختياري، مع فحص صلاحية وكمية) | 302 redirect → `inertia::location($gatewayUrl)` أو redirect لـ `user.billing.index` مع `status.type=success` | 302 redirect back مع `status.type=error` أو `$errors['method']`/`$errors['plan']` |
| Downgrade Plan | نفس `POST /subscription` (بدون endpoint مستقل) | POST | نفس الأعلى | 302 redirect مع `status.type=success` ("downgrade scheduled") | نفس الأعلى |
| Cancel Subscription | `DELETE /subscription/{id}` | DELETE | — | **🔴 الدالة فاضية — ترجع 302/200 بدون أي تنفيذ** | — |
| Cancel **Scheduled Change** (وليس الاشتراك) | `POST /subscription/scheduled-change/cancel` | POST | — | يصفّر `scheduled_plan_id`/`scheduled_plan_change_at` | — |
| Renew Subscription | **لا يوجد Endpoint مخصّص** | — | — | يحدث ضمنيًا فقط عبر إعادة اختيار باقة من `/billing` (`POST /subscription`)، أو تلقائيًا (نادرًا) عبر `activateSubscriptionIfInactiveAndExpiredWithCredits` أثناء Top-up | — |
| Invoice History | `GET /billing` (قائمة) + `GET /billing/invoices/{uuid}` (تفصيل) + `/preview` `/print` `/download` | GET | `checkPermission('settings.billing_subscription')` + ملكية الفاتورة عبر `documentForViewerOrganization` | Inertia render / Blade view (للـ print/preview) / تحميل ملف PDF | 403 لو خارج صلاحية المنظمة |
| Apply Coupon (Preview) | `POST /subscription/coupon/apply/{id}` | POST | `CouponRequest`: `coupon` مطلوب + فعّال + **🔴 لا يتحقق من `quantity === null` (راجع B-9)** | redirect back مع `response_data.data` (المعاينة الجديدة بالخصم) | `$errors['coupon']` = "The coupon has expired!" — **حتى لو الكوبون غير محدود الكمية** |
| Remove Coupon | `DELETE /subscription/coupon/remove/{id}` | DELETE | — | redirect back مع معاينة بدون كوبون | — |
| Top-up Balance | `POST /pay` | POST | `PaymentRequest`: `amount` (رقم > 0)، `method` (بوابة دفع متاحة) | redirect لبوابة الدفع | `status.type=error` |
| Moyasar Webhook | `POST /payment/moyasar/webhook` | POST | توقيع Webhook (`secret_token` أو `?token=`) عبر `hash_equals` | `200 {status: success}` | `401` (توقيع خاطئ) / `422` (مرجع دفع مفقود) / `202 {status: ignored}` (نتيجة غير ناجحة لكن معالجة بدون خطأ) |

**ملاحظة QA مهمة:** بما أن كل الردود تقريبًا 302، فإن أي اختبار Playwright/E2E لازم يتحقق من **محتوى flash message** (`status.type` / `status.message` / `$errors`) لا من HTTP status code فقط — لو حد كتب اختبارات تتحقق من `response.status === 200` هيفوّت كل حالات الفشل الحقيقية لأنها كلها كمان بترجع 302/200 بعد الـ redirect.

---

## 5. اختبارات Edge Cases

| # | الحالة | السلوك الفعلي في الكود | الخلاصة |
|---|---|---|---|
| 1 | الترقية لنفس الباقة الحالية | `determinePlanChangeAction` → `action='current_plan'` → redirect مع `status.type=info`: "You are already subscribed to this plan." **بدون** إنشاء أي intent/فاتورة | ✅ مُعالَج بشكل صحيح |
| 2 | الترقية أثناء وجود Intent سابق "Processing" (Payment Pending) | **لا فحص للـ intents المعلّقة** قبل إنشاء intent جديد (راجع السيناريو 3) | 🔴 خطر دفع مكرر فعلي — مؤكَّد بوجود `stuck_moyasar_processing_intents=1` حاليًا |
| 3 | تغيير الباقة أثناء Trial | `subscriptionStatus = currentSubscription?->status ?? 'trial'`؛ لو `trial`، `calculateProratedAmount` لا يُحسَب (الشرط `if ($subscriptionStatus != 'trial')`)، فـ `proratedCreditAmount=0` — يدفع السعر الكامل للباقة الجديدة بدون أي تعقيد | ✅ منطقي، لكن **غير مذكور بوضوح للمستخدم في الواجهة** أن الترقية من Trial لا تحصل على أي خصم (قد يتوقع المستخدم معاملة الـ Trial كـ"وقت مستهلَك" يُخصَم) |
| 4 | انتهاء الاشتراك أثناء عملية الدفع (المستخدم بدأ الدفع وقت الباقة سارية، لكن انتهت قبل اكتمال الـ webhook) | `completeIntent()` لا يتحقق من حالة الاشتراك الحالية وقت الإكمال — فقط يطابق `processor`/`currency`/`amount`. الإكمال سينجح ويحدّث `valid_until` من `now()` وقت الإكمال (لا وقت بداية الدفع) | ✅ النتيجة النهائية صحيحة عمليًا (المستخدم لسه بيدفع ويتفعل من اللحظة الحالية) |
| 5 | فشل عملية الدفع بعد إنشاء Invoice | **لا يمكن أن يحدث في هذا التصميم** — الفاتورة (`billing_invoices`) لا تُنشأ إلا **بعد** تأكيد الدفع الناجح (`completeIntent`/`completeWithoutGateway`). الفشل قبل ذلك يُسجَّل فقط في `billing_checkout_intents.status='failed'` مع `last_error`، بدون أي فاتورة | ✅ تصميم سليم يمنع المشكلة الكلاسيكية لفواتير "معلّقة بدون دفع" |
| 6 | الضغط على زر Upgrade عدة مرات بسرعة | **Frontend:** `buttonLoading.value=true` يعطّل الزر فعليًا أثناء الطلب ([Plan.vue:651](resources/js/Pages/User/Billing/Plan.vue#L651))، وهذا تخفيف حقيقي وفعّال للحالة الشائعة. **Backend:** لا يوجد Idempotency Key أو قفل على مستوى المنظمة يمنع إنشاء أكثر من intent مفتوح في حالة Race Condition حقيقية (تابين، طلب شبكة معاد) | ⚠️ محمي جزئيًا (Client) — غير محمي بالكامل (Server) |
| 7 *(إضافي اكتشفته أثناء المراجعة)* | كوبون "غير محدود الكمية" (`quantity = null`) في شاشة المعاينة | `CouponRequest` (`/subscription/coupon/apply/{id}`) يرفضه برسالة "The coupon has expired!" بسبب bug في المقارنة (راجع B-9) — بينما الشراء النهائي (`StoreSubscriptionPurchaseRequest`) يتعامل معه بشكل صحيح لأنه يفحص `null` بشكل صريح | 🔴 Bug مؤكَّد، قابل لإعادة الإنتاج فورًا بأي كوبون `quantity=NULL` |
| 8 *(إضافي)* | فرع تابع لمنظمة أم (Branch) يحاول الوصول لصفحة الباقات | `abortIfBranchSubscriptionManagedByParent()` → `abort(403)` على كل أفعال التغيير، لكن صفحة العرض (`index`) **مسموحة** وتعرض بانر "Inherited subscription" | ✅ سلوك متوقَّع ومتّسق |

---

## 6. التقرير النهائي

### 6.1 شرح آلية عمل النظام بالكامل

النظام يدير الباقات عبر صف واحد متغيّر (Mutable) في جدول `subscriptions` لكل منظمة (وليس عبر سجل تاريخي Append-only). كل تغيير باقة هو إما:
- **تنفيذ فوري** (Upgrade) يمر عبر `BillingCheckoutIntent` → دفع (أو تخطّي الدفع لو الرصيد/الكوبون يغطّي بالكامل) → تحديث الصف، أو
- **حجز نية تغيير مستقبلي** (Downgrade) بدون أي حركة مالية، تُترجَم لاحقًا (نظريًا فقط حاليًا) عند الانتهاء.

الدفع مرتبط بمزوّد واحد (Moyasar) عبر نمط "Invoice API" (إنشاء فاتورة دفع مستضافة + Webhook)، وكل "نية دفع" تُمثَّل بصف `billing_checkout_intents` يحمل Snapshot كامل لحالة الحساب وقت الطلب (`snapshot_json`) لضمان أن إكمال الدفع لاحقًا (حتى لو الأسعار تغيّرت بعدها) يستخدم الأرقام المتفق عليها وقت البدء.

### 6.2 Flow Diagram لعملية تغيير الباقة

```
                         ┌────────────────────┐
                         │ POST /subscription  │
                         └─────────┬───────────┘
                                   │
                    determinePlanChangeAction()
                                   │
        ┌──────────────┬──────────┼──────────────┬───────────────────┐
        │              │          │              │                    │
   current_plan   scheduled_   downgrade_     upgrade_now        invalid_plan
   (لا شيء)        downgrade   at_renewal     /subscribe_now      (رسالة خطأ)
                   (لا شيء،         │               │
                   مجدوَل بالفعل)    │               │
                          schedulePlanChangeAtRenewal   planSelectionPreview()
                          (UPDATE scheduled_plan_id)         │
                                                    ┌─────────┴─────────┐
                                                amountDue=0          amountDue>0
                                                    │                    │
                                      createSubscriptionPurchaseIntent   │
                                                    │                    │
                                          completeWithoutGateway   beginGatewayCheckout
                                                    │              (Moyasar Invoice API)
                                                    │                    │
                                                    │           redirect → صفحة دفع
                                                    │                    │
                                                    │            Webhook/Callback
                                                    │                    │
                                                    └──────────┬─────────┘
                                                               │
                                                  completeIntent() [DB transaction + lock]
                                                               │
                                       ┌───────────────────────┼───────────────────────┐
                                INSERT invoice            INSERT payment          UPDATE subscriptions
                                + tax rows + credit       + transaction            (plan_id, valid_until,
                                + transaction (سالب)      (موجب)                   scheduled_plan_id=null)
```

**⚠️ الحلقة المفقودة في الرسم (غير موجودة في الكود):**
```
            [valid_until ينتهي + scheduled_plan_id موجود]
                              │
                    ❌ لا يوجد Cron/Job هنا ❌
                              │
                  (الاشتراك يصبح "expired" فقط ديناميكيًا،
                   والـ downgrade المجدوَل لا يُطبَّق أبدًا تلقائيًا)
```

### 6.3 دورة حياة الاشتراك (Subscription Lifecycle)

```
trial ──(شراء باقة / amountDue=0 أو دفع ناجح)──► active
active ──(upgrade_now ناجح)──► active (باقة أعلى، فترة جديدة كاملة)
active ──(downgrade_at_renewal)──► active (نفس الباقة) + scheduled_plan_id معلَّق
active ──(valid_until ينقضي بدون أي إجراء)──► [ديناميكيًا: expired] لكن status يبقى 'active' في DB
[expired] ──(دفع Top-up يغطي التجديد، مسار قديم)──► active (نادر الحدوث)
[expired] ──(المستخدم يدخل /billing ويختار باقة)──► subscribe_now (شراء جديد، كأنه عميل جديد، بدون proration)
أي حالة ──(DELETE /subscription/{id})──► 🔴 لا تغيير فعلي (دالة فاضية)
```

### 6.4 دورة حياة الفاتورة (Invoice Lifecycle)

```
(لا يوجد) ──► [عند نجاح الدفع/الإكمال فقط] ──► billing_invoices (سجل ثابت، لا تعديل لاحق)
                                                     │
                                          billing_tax_rates (تفصيل الضريبة، Snapshot)
                                                     │
                                  billing_transactions (entity_type=invoice, amount سالب)
```
لا توجد حالات `draft` / `pending` / `void` / `refunded` للفاتورة في هذا المخطط — الفاتورة "تُولد مدفوعة" دائمًا، أو لا تُولد أصلًا.

### 6.5 جميع المشاكل المكتشفة (مرتّبة بالخطورة)

#### 🔴 B-1 — الـ Downgrade المجدوَل لا يُطبَّق تلقائيًا أبدًا (Critical)
**المشكلة:** `scheduled_plan_id` يُكتب في `subscriptions` لكن لا يوجد Job/Cron يقرأه عند انتهاء `valid_until`.
**السبب:** `bootstrap/app.php` (`withSchedule`) يحتوي فقط على `queue:restart`, `queue:prune-failed`, `queue:prune-batches`, `whatsapp:refresh-tokens`, `model:prune`. لا يوجد أمر لتجديد/تبديل الاشتراكات. الدالة الوحيدة التي تستهلك `scheduled_plan_id` (`renewalPlanId()` داخل `activateSubscriptionIfInactiveAndExpiredWithCredits`) لا تُستدعى إلا من مسار شحن الرصيد (Top-up) — وهو مسار غير مرتبط منطقيًا بتجديد الاشتراك من منظور المستخدم.
**الأثر:** أي مستخدم يجدول Downgrade سيخسر وصوله بالكامل (Status=expired) عند `valid_until` بدلًا من الانتقال السلس للباقة الأقل كما وُعِد في الواجهة.
**الحل المقترح:**
```php
// 1) أمر Artisan جديد: app/Console/Commands/ApplyScheduledSubscriptionChanges.php
public function handle(): int
{
    Subscription::whereNotNull('scheduled_plan_id')
        ->where('scheduled_plan_change_at', '<=', now())
        ->each(function (Subscription $subscription) {
            DB::transaction(function () use ($subscription) {
                $plan = $subscription->scheduledPlan;
                $subscription->update([
                    'plan_id' => $plan->id,
                    'scheduled_plan_id' => null,
                    'scheduled_plan_change_at' => null,
                    'start_date' => now(),
                    'valid_until' => $plan->period === 'yearly' ? now()->addYear() : now()->addMonth(),
                ]);
                // اختياري: إنشاء فاتورة $0 أو بسعر الباقة الجديدة حسب سياسة الشركة
            });
        });

    return self::SUCCESS;
}

// 2) bootstrap/app.php
$schedule->command('subscriptions:apply-scheduled-changes')->hourly();
```
**لماذا هذا الحل:** يحترم نفس بنية `upsertOperationalSubscription`/`updateLocalSubscriptionFromIntent` الموجودة (نفس أسلوب تحديث الصف)، ولا يكسر أي عقد حالي، ويُغلق الفجوة الزمنية لأقل من ساعة.

#### 🔴 B-2 — "Cancel Subscription" غير منفَّذ (Critical)
**المشكلة:** `SubscriptionController::destroy()` دالة فاضية تمامًا.
**الأثر:** أي محاولة فعلية لإلغاء الاشتراك (DELETE) لا تفعل شيئًا — لا في الواجهة (لا يوجد حتى زر يستدعيها ظاهريًا في `Plan.vue` كما يبدو من الفحص) ولا Backend. لو فُعِّل زر بالواجهة لاحقًا بافتراض أن الإندبوينت يعمل، فالنتيجة وهمية بالكامل.
**الحل المقترح:**
```php
public function destroy($id)
{
    $organizationId = session()->get('current_organization');
    $this->checkPermission('settings.billing_subscription', $organizationId);
    $this->abortIfBranchSubscriptionManagedByParent((int) $organizationId);

    $subscription = SubscriptionService::resolveActiveSubscriptionForOrg($organizationId); // expose as public
    abort_unless($subscription, 404);

    $subscription->update([
        'scheduled_plan_id' => null,
        'scheduled_plan_change_at' => null,
        // سياسة مقترحة: إيقاف الوصول فورًا أو عند نهاية الفترة المدفوعة (الأكثر شيوعًا في SaaS)
        'valid_until' => now(), // أو الإبقاء على valid_until الحالي لو السياسة "يستمر لنهاية الفترة المدفوعة"
    ]);

    return Redirect::route('user.billing.index')->with('status', [
        'type' => 'success',
        'message' => __('Your subscription has been canceled.'),
    ]);
}
```
**لماذا هذا الحل:** يتبع نمط Stripe/Paddle المعتاد ("Cancel at period end" هو الافتراضي الأكثر أمانًا تجاريًا) — يجب اتخاذ قرار منتج واضح: إلغاء فوري أو في نهاية الفترة، والكود الحالي لا يقدّم لا هذا ولا ذاك.

#### 🟠 B-3 — عدم وجود فحص لـ Intents معلّقة قبل إنشاء intent جديد (High)
**المشكلة:** `SubscriptionController::store()` لا يتحقق من وجود `billing_checkout_intents` بحالة `pending`/`processing` لنفس المنظمة قبل إنشاء intent جديد.
**الدليل الفعلي:** تشغيل `system:signup-billing-audit` على القاعدة الحالية أظهر `stuck_moyasar_processing_intents = 1` فعليًا.
**الحل المقترح:**
```php
$pendingIntent = BillingCheckoutIntent::where('billing_organization_id', $organizationId)
    ->where('type', 'subscription_purchase')
    ->whereIn('status', ['pending', 'processing'])
    ->where('expires_at', '>', now())
    ->latest('id')->first();

if ($pendingIntent) {
    return Redirect::back()->with('status', [
        'type' => 'info',
        'message' => __('You already have a payment in progress. Please complete or wait for it to expire before starting a new one.'),
    ]);
}
```

#### 🟠 B-4 — تكرار منطق إنشاء الفاتورة بين مسارين غير متطابقين (High — Maintainability/Correctness)
**المشكلة:** `SubscriptionService::createBillingInvoice()` (القديم) لا يطرح `coupon_amount` من `invoice.total`، بينما `BillingCheckoutIntentService::resolveOrCreateSubscriptionInvoice()` (الحديث) يطرحه بشكل صحيح. أي تعديل مستقبلي على منطق الضريبة/الكوبون في أحد المسارين دون الآخر سيُنتج فواتير غير متطابقة محاسبيًا حسب المسار الذي وُلِّدت منه.
**الحل المقترح:** دمج إنشاء الفاتورة في خدمة واحدة (`InvoiceFactory`/`BillingInvoiceFactory`) يستدعيها كل من `BillingCheckoutIntentService` والمسار القديم (Top-up-triggered renewal)، وحذف الكود المكرر في `SubscriptionService::createBillingInvoice()`/`updateSubscriptionPlan()`.

#### 🟡 B-5 — `expires_at` على `BillingCheckoutIntent` غير مُفعَّل فعليًا (Medium)
**المشكلة:** `expires_at = now()->addHours(2)` يُخزَّن لكن `completeIntent()`/`completeIntentByUuid()` لا يتحقق منه أبدًا. Webhook متأخر جدًا (أيام) سيُكمل العملية بنجاح حتى لو الـ Snapshot أصبح غير ذي صلة (تغيّر السعر، تغيّرت الباقة، إلخ).
**الحل المقترح:**
```php
if ($lockedIntent->expires_at && $lockedIntent->expires_at->isPast()) {
    $this->markIntentAsFailed($lockedIntent, __('This checkout request has expired.'));
    return (object) ['success' => false, 'message' => __('This checkout request has expired.')];
}
```
وإضافة Job دوري (مثلاً ضمن نفس أمر B-1 أو مستقل) يحوّل أي intent `pending`/`processing` تجاوز `expires_at` إلى `expired` تلقائيًا — بدل الاعتماد فقط على تشغيل يدوي لـ `system:signup-billing-audit --apply-safe-fixes`.

#### 🟡 B-6 — مقارنة Tier Rank عبر باقات Fallback قد تُصنّف Upgrade/Downgrade خطأ بين الفترات المختلفة (Medium، مشروط)
**المشكلة:** `resolveFallbackPlanRank()` يحسب الترتيب **داخل نفس period فقط** (شهري وحده، سنوي وحده) عند غياب `metadata.tier_rank`. مقارنة باقتين من period مختلف بهذا الترتيب الموضعي قد لا تعكس القيمة الفعلية، خصوصًا لو تسعير الباقات بين الـ Monthly والـ Yearly غير متناسق تمامًا بين الـ Tiers.
**الحل المقترح:** فرض وجود `tier_rank` صريح ومتّسق (نفس القيمة لكل Tier بغضّ النظر عن الـ period) على كل باقة فعّالة، وإضافة هذا كفحص جديد داخل `system:billing-integrity-audit` (`plans_missing_tier_rank`).

#### 🟡 B-7 — لا قيد Unique على `subscriptions.organization_id` (Medium، نظري لكن قابل للحدوث)
**المشكلة:** Race condition بين طلبين متزامنين لمنظمة جديدة (لا يوجد لها صف اشتراك بعد) قد يُنتج صفين `Subscription::create()` بدلًا من واحد، لأن `upsertOperationalSubscription`/`updateLocalSubscriptionFromIntent` يقرآن أولًا "هل يوجد صف؟" دون قفل (`lockForUpdate`) على مستوى المنظمة.
**الحل المقترح:** إضافة Unique Index (composite أو partial) على `(organization_id)` مع منطق "آخر اشتراك فعّال فقط" أو استخدام `lockForUpdate()`/Database Advisory Lock (نفس الأسلوب المستخدم بالفعل في `MoyasarService::withPreviousPaymentLock`) عند إنشاء أول اشتراك.

#### 🟢 B-8 — غياب Foreign Keys على جداول الفوترة (Medium، Data Integrity)
تم تفصيلها في §3.1. **الحل:** إضافة FK (`restrictOnDelete` كحد أدنى لمنع حذف منظمة/باقة لها سجل فواتير، بدل `cascade` الذي يفقد الأثر المحاسبي) — أو الانتقال لمبدأ Soft Delete الإلزامي على `organizations` و`subscription_plans` (الأخيرة مطبَّقة بالفعل ✅، الأولى يجب التأكد منها).

#### 🟢 B-9 — Bug مؤكَّد: كوبون "غير محدود" (`quantity = null`) يُرفض في معاينة `/subscription/coupon/apply/{id}` (Confirmed Bug)
**الملف:** [app/Http/Requests/CouponRequest.php:39](app/Http/Requests/CouponRequest.php#L39)
```php
// الكود الحالي (خاطئ):
if ($coupon->quantity_redeemed >= $coupon->quantity) {
    $fail(__('The coupon has expired!'));
}
```
**السبب الجذري:** في PHP، مقارنة `int >= null` بالـ Loose Comparison تُحوّل كل الطرفين إلى `bool` (لأن أحدهما `null`)، و`false >= false` تكون **`true`** دائمًا (بما أن `0` و`null` كلاهما falsy). نتيجة: أي كوبون `quantity = null` (يعني "غير محدود" حسب الاتفاقية المستخدمة في باقي الكود — راجع `StoreSubscriptionPurchaseRequest.php:66` التي تتحقق بشكل صحيح بـ `$coupon->quantity !== null && ...`) **سيُرفض دائمًا** في شاشة معاينة الكوبون رغم كونه صالحًا 100%.
**الحل المقترح:**
```php
if ($coupon->quantity !== null && (int) ($coupon->quantity_redeemed ?? 0) >= (int) $coupon->quantity) {
    $fail(__('The coupon has expired!'));
}
```
**لماذا هذا الحل:** نفس الفحص المستخدم بالفعل وبشكل صحيح في `StoreSubscriptionPurchaseRequest::rules()` و`SubscriptionService::resolveCouponPreview()` — توحيد المنطق بدل وجود 3 تطبيقات مختلفة لنفس فحص "هل الكوبون انتهت كميته؟" أحدها فيه Bug.

### 6.6 أفضل الممارسات المعتمَدة في Stripe / Paddle / LemonSqueezy، وأين يقصّر هذا النظام عنها

| الممارسة | Stripe/Paddle/LemonSqueezy | هذا النظام | الفرق |
|---|---|---|---|
| Subscription كسجل تاريخي (Append-only events) | كل تغيير باقة يُسجَّل كـ Event (`customer.subscription.updated`) مع نسخة كاملة قبل/بعد | صف واحد متغيّر، بدون تاريخ تغييرات مستقل عن الفواتير | يُفقَد تتبّع "متى تغيّرت الباقة بالضبط" إلا عبر تواريخ الفواتير |
| Proration | حساب دقيق باليوم/الثانية، Line items منفصلة على نفس الفاتورة (Credit + Debit lines) | حساب باليوم لكن "Credit مُجمَّع" منفصل عن الفاتورة، يعتمد على "آخر فاتورة" فقط بدل سعر الباقة الحالية الفعلي | أقرب لكنه أبسط؛ يُفترض الفاتورة السابقة تساوي القيمة الحالية للباقة (غير صحيح لو تغيّر سعر الباقة لاحقًا أو طُبِّق كوبون مختلف) |
| Auto-renewal / Dunning | شحن البطاقة المحفوظة تلقائيًا عند التجديد، مع محاولات إعادة دفع (Retry) عند الفشل + إشعارات | **لا يوجد أي تجديد تلقائي** — Moyasar Invoice-based لا يحفظ بطاقة، فكل تجديد يتطلب فعل المستخدم اليدوي | فجوة جوهرية: المستخدمون الذين لا يعودون للموقع يفقدون اشتراكهم بصمت |
| Scheduled downgrade عند نهاية الفترة | يُطبَّق تلقائيًا عبر Webhook دوري داخلي للمزوّد | **مُسجَّل لكن غير مُطبَّق** (B-1) | فجوة جوهرية مطابقة لما سبق |
| Idempotency على عمليات الدفع | Idempotency-Key على مستوى الطلب يمنع تكرار العملية بالكامل من البداية | موجود فقط على مستوى "نفس الـ intent" (lockForUpdate + status check)، غائب على مستوى "منع إنشاء intent جديد بينما يوجد آخر معلّق" | جزئي (راجع B-3) |
| Invoice Voiding/Refund APIs | عمليات صريحة `void`/`refund` مع أثر محاسبي معكوس | لا توجد آلية Refund/Void في الكود على الإطلاق (لا Route ولا Service) | غياب كامل — أي رد أموال حاليًا يحتاج تدخل يدوي مباشر في القاعدة |

### 6.7 ملخص توصيات بحسب الأولوية

| الأولوية | التوصية | المرجع |
|---|---|---|
| 1 | تنفيذ Job دوري لتطبيق `scheduled_plan_id` عند `valid_until` | B-1 |
| 2 | تنفيذ منطق فعلي لـ `SubscriptionController::destroy()` (تحديد سياسة: فوري أم نهاية الفترة) | B-2 |
| 3 | منع إنشاء intent جديد إذا يوجد intent معلّق سارٍ لنفس المنظمة | B-3 |
| 4 | إصلاح فوري لـ `CouponRequest.php` (سطر واحد) | B-9 |
| 5 | تفعيل فحص `expires_at` داخل `completeIntent`/`completeIntentByUuid` + جدولة تنفيذ `--apply-safe-fixes` تلقائيًا (مثلاً كل ساعة) بدل التشغيل اليدوي | B-5 |
| 6 | توحيد منطق إنشاء الفاتورة في خدمة واحدة بدل مسارين | B-4 |
| 7 | إضافة Foreign Keys / `restrictOnDelete` على جداول الفوترة | B-8 |
| 8 | فرض `tier_rank` صريح على كل الباقات + فحص جديد في أمر التدقيق | B-6 |
| 9 | تقييم الحاجة لآلية Refund/Void رسمية | §6.6 |
| 10 (اختياري لاحقًا) | تشغيل `system:billing-integrity-audit --apply-safe-fixes` و `system:signup-billing-audit --apply-safe-fixes` على القاعدة الحالية لتسوية الـ 1 intent العالق فعليًا — **يتطلب موافقتكم المباشرة قبل التنفيذ** لأنه يُعدّل بيانات حقيقية | §3.3 |

---

**ملحق:** كل الأرقام والنتائج في القسم 3.3 جُمعت بتشغيل فعلي للأوامر الموجودة بالفعل في المشروع (`system:billing-integrity-audit`, `system:signup-billing-audit`) في وضع القراءة فقط، وليست افتراضات. لم يتم تنفيذ أي تعديل على قاعدة البيانات أثناء هذه المراجعة.
