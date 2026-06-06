# دليل تفعيل ميزات Botzo
## Flow Builder — AI Assistant — Embedded Signup

---

## المحتويات

1. [Flow Builder](#-flow-builder)
2. [AI Assistant](#-ai-assistant)
3. [Embedded Signup](#-embedded-signup)
4. [ملخص: ماذا تفعل الآن](#-ملخص-ماذا-تفعل-الآن)

---

## 🔧 Flow Builder

### ما يحتاجه
**لا شيء على الإطلاق.**

### كيف تفعّله
```
http://localhost:8000/admin/settings/features/flow-builder
    ↓
فعّل الـ Toggle
    ↓
احفظ
    ↓
انتهى ✅
```

### ماذا يفعل للعميل بعد التفعيل؟
العميل يقدر يبني محادثات أتمتة متقدمة — شجرة قرارات تعمل تلقائياً على WhatsApp بدون موظف.

---

## 🤖 AI Assistant

### الفهم الأساسي — 3 أوضاع مختلفة

النظام عنده 3 أوضاع لسياسة مفتاح OpenAI. كل وضع يشتغل بطريقة مختلفة:

---

#### الوضع الأول — Hybrid (الافتراضي والموصى به)

| البند | التفصيل |
|-------|---------|
| **كيف يعمل؟** | لو العميل عنده مفتاح OpenAI خاص به → يستخدمه. لو ما عنده → يستخدم مفتاح Botzo من حصته في الباقة |
| **يحتاج مفتاح OpenAI الآن؟** | **لا — تقدر تفعّله فوراً** |
| **متى تحتاج المفتاح؟** | فقط لو أردت منح عملاء حصة من مفتاح Botzo (ai_system_key_monthly_quota في الباقة) |
| **التوصية** | ✅ ابدأ بهذا الوضع |

---

#### الوضع الثاني — Organization Only

| البند | التفصيل |
|-------|---------|
| **كيف يعمل؟** | كل عميل يستخدم مفتاح OpenAI الخاص به فقط — Botzo لا يوفر مفتاحاً |
| **يحتاج مفتاح OpenAI الآن؟** | **لا — تقدر تفعّله فوراً** |
| **العميل بدون مفتاح؟** | لن يعمل AI عنده |
| **التوصية** | مناسب لو ما تريد تتحمل تكلفة OpenAI نهائياً |

---

#### الوضع الثالث — Global Only

| البند | التفصيل |
|-------|---------|
| **كيف يعمل؟** | كل العملاء يستخدمون مفتاح Botzo فقط — لا أحد يضيف مفتاحه الخاص |
| **يحتاج مفتاح OpenAI الآن؟** | **نعم — لازم تضع المفتاح قبل التفعيل** |
| **لو فعّلته بدون مفتاح؟** | النظام يرفض التفعيل ويعطي خطأ |
| **التوصية** | اتركه لما يكون عندك مفتاح OpenAI جاهز |

---

### حقول صفحة AI Assistant

| الحقل | شرحه | ملاحظة |
|-------|-------|--------|
| **Global OpenAI API Key** | مفتاح OpenAI الخاص بـ Botzo — يبدأ بـ `sk-...` | مشفّر تلقائياً عند الحفظ |
| **AI Key Policy** | الوضع المختار: Hybrid / Global Only / Organization Only | اختار Hybrid للبداية |
| **Allow org override** | هل يقدر العميل يضيف مفتاح OpenAI الخاص به؟ | فعّله دائماً في Hybrid |

---

### خطوات التفعيل الآن (بدون مفتاح)

```
http://localhost:8000/admin/settings/features/ai-assistant
    ↓
اختار AI Key Policy = Hybrid
    ↓
فعّل "Allow org override"
    ↓
اتركّ حقل المفتاح فارغاً
    ↓
فعّل الـ Toggle
    ↓
احفظ ✅
```

---

### لما تجيب مفتاح OpenAI لاحقاً

```
ارجع لنفس الصفحة
    ↓
ضع المفتاح في "Global OpenAI API Key"
    ↓
احفظ
    ↓
المفتاح يُشفَّر ويُحفَظ تلقائياً
    ↓
العملاء في باقاتهم يبدأون يستهلكون من الحصة (ai_system_key_monthly_quota)
```

---

### من أين تحصل على مفتاح OpenAI؟

```
platform.openai.com
    ↓
API Keys
    ↓
Create new secret key
    ↓
انسخ المفتاح (يظهر مرة واحدة فقط)
```

> **تنبيه:** حدد ميزانية شهرية لتجنب فواتير مفاجئة من لوحة تحكم OpenAI.

---

## 🔗 Embedded Signup

### ما هي؟
ميزة تسمح للعميل الجديد بربط WhatsApp Business Account بتاعه بـ Botzo بنقرة واحدة — بدلاً من العملية التقنية الطويلة.

### البيانات المطلوبة

| الحقل | مطلوب للتفعيل؟ | من أين؟ |
|-------|:------------:|--------|
| **App ID** | ✅ لازم | `developers.facebook.com` ← التطبيق ← App ID في الأعلى |
| **App Secret** | ✅ لازم | التطبيق ← Settings ← Basic ← App Secret ← Show |
| **Config ID** | ✅ لازم | داخل التطبيق ← WhatsApp ← Embedded Signup ← Configuration ID |
| **Access Token** | ⚠️ مهم جداً | Business Manager ← System Users ← Generate Token |

> **ملاحظة:** النظام يرفض التفعيل لو App ID أو App Secret أو Config ID فارغة.
> Access Token لا يمنع التفعيل لكنه ضروري لعمل الميزة فعلياً.

---

### الـ Webhook — إعداد إضافي مطلوب في Meta

بعد ما تضع البيانات، الصفحة ستعرض لك قيمتين لازم تضيفهم في تطبيق Meta Developer:

| القيمة | مكانها في Meta |
|--------|--------------|
| **Callback URL** | `http://localhost:8000/webhook/waba` (محلياً) أو رابط الإنتاج |
| **Verify Token** | يظهر تلقائياً في الصفحة — انسخه كما هو |

```
Meta Developer App
    ↓
WhatsApp ← Configuration ← Webhooks
    ↓
Callback URL = رابط Botzo/webhook/waba
Verify Token = من صفحة Botzo
    ↓
Verify and Save
```

---

### اختبار الجاهزية بعد الإعداد

الصفحة عندها زر **"Run Meta Review Tests"** — اضغطه بعد حفظ البيانات.

سيظهر تقرير يوضح:
- ✅ Passed — كل شيء صحيح
- ⚠️ Warning — يعمل لكن فيه تنبيه
- ❌ Failed — في خطأ يجب إصلاحه

---

### خطوات التفعيل الكاملة

```
1. احصل على App ID + App Secret + Config ID + Token من Meta
    ↓
2. افتح: http://localhost:8000/admin/settings/features/embedded-signup
    ↓
3. ضع البيانات الأربعة في حقولها
    ↓
4. اذهب لـ Meta Developer → ضع الـ Callback URL والـ Verify Token
    ↓
5. فعّل الـ Toggle
    ↓
6. احفظ
    ↓
7. اضغط "Run Meta Review Tests" وتحقق من النتيجة
```

---

## ✅ ملخص: ماذا تفعل الآن

| الميزة | تفعّلها الآن؟ | ما ينقصك | الأولوية |
|--------|:------------:|---------|---------|
| **Flow Builder** | ✅ افعلها الآن | لا شيء | 🔴 افعل الآن |
| **AI Assistant** | ✅ افعلها الآن بـ Hybrid | مفتاح OpenAI لاحقاً | 🔴 افعل الآن |
| **Embedded Signup** | ❌ تنتظر Meta | App ID + Secret + Config ID + Token | 🟡 تنتظر البيانات |

---

## ماذا تطلب من Meta؟

للحصول على بيانات Embedded Signup، تحتاج:

1. **حساب Meta Developer** مفعّل على `developers.facebook.com`
2. **تطبيق Meta** مربوط بـ WhatsApp Business
3. **Embedded Signup Configuration** منشأ داخل التطبيق
4. **System User** في Business Manager بصلاحيات WhatsApp

> هذه الخطوات تتم من داخل حساب Meta Business الخاص بـ Botzo.
> إذا لم يكن لديك الوصول، تواصل مع صاحب حساب Meta Business للمنصة.

---

*آخر تحديث: مايو 2026*
