# دليل Embedded Signup — كل ما تحتاجه من Meta
## + الإيميل الرسمي الجاهز للإرسال

---

## أولاً: فهم الصورة الكاملة

### ما هي Embedded Signup بالضبط؟

هي ميزة تسمح لعملاء Botzo بربط WhatsApp Business Account بتاعهم بنقرة واحدة من داخل Botzo — بدلاً من الإجراءات التقنية الطويلة.

```
عميل جديد في Botzo
        ↓
يضغط "ربط WhatsApp"
        ↓
نافذة Meta تفتح تلقائياً
        ↓
يسجل دخول بحساب Meta بتاعه
        ↓
يختار WhatsApp Business Account
        ↓
Botzo يربطه تلقائياً ✅
```

### لماذا تحتاج موافقة Meta؟

لأن Botzo هنا يعمل كـ **Tech Provider** — يدير حسابات WhatsApp لشركات أخرى. Meta تشترط مراجعة رسمية للتطبيقات التي تقدم هذه الخدمة.

---

## ثانياً: ما تحتاجه بالتفصيل

### القسم الأول — تحضيره أنت من البورتال (Self-Service)

هذه بيانات تسحبها بنفسك من حسابك على Meta Developer — لا تحتاج إذناً من Meta.

---

#### 1 — App ID و App Secret

**ما هما؟**
معرف تطبيق Botzo على منصة Meta + مفتاحه السري.

**من أين؟**
```
developers.facebook.com
    ↓
My Apps ← اختر تطبيق Botzo (أو أنشئ واحداً جديداً)
    ↓
App ID : يظهر في الأعلى مباشرة (رقم مثل 1234567890)
    ↓
Settings ← Basic
    ↓
App Secret ← اضغط Show ← انسخه
```

**شروط التطبيق:**
- نوعه: Business
- وضعه: Live (مش Development)
- مضاف إليه منتج WhatsApp

---

#### 2 — Config ID (Embedded Signup Configuration)

**ما هو؟**
معرف إعداد Embedded Signup الذي تنشئه داخل تطبيقك على Meta.

**من أين؟**
```
developers.facebook.com ← تطبيقك
    ↓
WhatsApp ← Quickstart أو Configuration
    ↓
Embedded Signup
    ↓
أنشئ Configuration جديدة
    ↓
انسخ الـ Configuration ID
```

---

#### 3 — System User Token (Access Token)

**ما هو؟**
Token دائم لا ينتهي يستخدمه Botzo لإدارة ربط العملاء.

**من أين؟**
```
business.facebook.com
    ↓
Settings ← Business Settings
    ↓
Users ← System Users
    ↓
أنشئ System User من نوع Admin
    ↓
Generate New Token
    ↓
اختر تطبيق Botzo
    ↓
اختر الصلاحيات:
    ✅ whatsapp_business_management
    ✅ whatsapp_business_messaging
    ✅ business_management
    ↓
انسخ الـ Token
```

> **مهم جداً:** هذا Token لا ينتهي (Never Expires) — احفظه في مكان آمن.

---

#### 4 — Webhook Setup في Meta

بعد حفظ البيانات في Botzo، ستظهر في صفحة الإعداد:
- **Callback URL** — رابط Botzo لاستقبال الأحداث
- **Verify Token** — رمز مولّد تلقائياً من النظام

اذهب لـ Meta وضعهما:
```
developers.facebook.com ← تطبيقك
    ↓
WhatsApp ← Configuration
    ↓
Webhook ← Edit
    ↓
Callback URL = [ما يظهر في صفحة Botzo]
Verify Token = [ما يظهر في صفحة Botzo]
    ↓
Verify and Save
    ↓
اشترك في الأحداث: messages, message_template_status_update, account_update
```

---

### القسم الثاني — يحتاج موافقة رسمية من Meta (App Review)

هذا هو الجزء الذي لا تقدر تفعله بنفسك — يحتاج تقديم طلب لـ Meta.

#### الصلاحيات المطلوب اعتمادها

| الصلاحية | لماذا؟ |
|---------|--------|
| `whatsapp_business_management` | لإدارة حسابات WhatsApp للعملاء |
| `business_management` | للوصول لبيانات Business Manager للعميل |
| `whatsapp_embedded_signup` | للسماح بتدفق Embedded Signup |

#### كيف تتقدم؟
```
developers.facebook.com ← تطبيقك
    ↓
App Review ← Permissions and Features
    ↓
ابحث عن كل صلاحية وطلب Request
    ↓
اشرح حالة الاستخدام (Use Case)
    ↓
أرفق فيديو أو screenshots يوضح الـ Flow
    ↓
أرسل للمراجعة
```

**المدة:** من 5 أيام عمل حتى 4 أسابيع.

---

## ثالثاً: الإيميل الرسمي الجاهز

### إيميل لدعم Meta Business (إنجليزي — رسمي)

> **إرسال إلى:** `developers@facebook.com` أو عبر `developers.facebook.com/support`
> **الموضوع:** WhatsApp Embedded Signup — App Review Request for Tech Provider Platform

---

**Subject:** WhatsApp Embedded Signup — App Review & Tech Provider Access Request

---

Dear Meta Developer Support Team,

I am writing on behalf of **Botzo**, a WhatsApp Business SaaS platform that serves businesses in Saudi Arabia and the GCC region.

**About Botzo:**
Botzo is a multi-tenant SaaS platform that provides businesses with:
- WhatsApp Business API integration and inbox management
- Bulk messaging and campaign management
- Conversation automation (Flow Builder)
- CRM and contact management
- Subscription-based billing

We are a direct Meta Cloud API partner, and each of our customers connects their own WhatsApp Business Account (WABA) to our platform.

---

**Purpose of This Request:**

We are requesting approval for the following permissions and features to enable the **WhatsApp Embedded Signup** flow within our platform:

1. `whatsapp_business_management` — To manage customer WhatsApp Business Accounts through our platform
2. `business_management` — To access customer Business Manager data during the onboarding flow
3. `whatsapp_embedded_signup` — To implement the Embedded Signup UI flow that allows our customers to connect their WhatsApp Business Account to Botzo in a single click

---

**How We Use These Permissions:**

When a new business subscribes to Botzo and wants to connect their WhatsApp Business Account, we currently require them to manually configure API credentials. With Embedded Signup, the flow would be:

1. Customer clicks "Connect WhatsApp" inside Botzo dashboard
2. A Meta-hosted popup appears (Facebook Login for Business)
3. Customer authenticates with their Meta Business account
4. Customer selects their WhatsApp Business Account (WABA)
5. Botzo receives the authorization code and exchanges it for an access token
6. The WABA is connected to Botzo automatically

**No customer data is stored beyond what is necessary for the WhatsApp integration.**
**We never use customer WhatsApp accounts for purposes other than what the customer explicitly authorizes.**

---

**Technical Details:**

- **App Name:** Botzo
- **App ID:** [YOUR APP ID]
- **App Type:** Business
- **Platform URL:** https://botzo.net
- **Webhook Callback URL:** https://botzo.net/webhook/waba
- **Business Verification Status:** [Verified / In Progress]
- **Meta Business ID:** [YOUR BUSINESS ID]

---

**Compliance Commitment:**

We confirm that our implementation complies with:
- Meta's Platform Terms and Developer Policies
- WhatsApp Business Policy
- All applicable data protection regulations in our operating regions

---

We are happy to provide additional documentation, screen recordings, or schedule a technical review call if required.

Please let us know if there is any additional information needed to process this request.

Best regards,

[Your Full Name]
[Your Title]
Botzo Platform
Email: [your@email.com]
Website: https://botzo.net

---

### إيميل بالعربي — لمن يدير حساب Meta Business بتاعك

إذا كان شخص آخر هو المسؤول عن حساب Meta Developer / Meta Business، أرسل له هذا:

---

**الموضوع:** بيانات مطلوبة من Meta Developer لتفعيل Embedded Signup في Botzo

السلام عليكم،

نحتاج منك مساعدة في إعداد ميزة **Embedded Signup** في منصة Botzo، وهي تتيح للعملاء الجدد ربط حسابات WhatsApp Business بتاعتهم بنقرة واحدة.

**ما نحتاجه منك:**

**1 — App ID و App Secret:**
من `developers.facebook.com` ← التطبيق ← Settings ← Basic

**2 — Embedded Signup Config ID:**
من داخل التطبيق ← WhatsApp ← Embedded Signup ← أنشئ Configuration وأرسل لي الـ ID

**3 — System User Token:**
من `business.facebook.com` ← Business Settings ← System Users
- أنشئ System User جديد من نوع Admin
- اعمل Generate Token للتطبيق
- الصلاحيات المطلوبة: `whatsapp_business_management` + `whatsapp_business_messaging` + `business_management`
- أرسل لي الـ Token

**4 — App Review:**
داخل التطبيق ← App Review ← قدّم طلب للصلاحيات:
- `whatsapp_business_management`
- `business_management`
- `whatsapp_embedded_signup`

**5 — الـ Webhook:**
بعد ما أرسل لك الـ Callback URL و Verify Token من النظام، تضيفهم في:
التطبيق ← WhatsApp ← Configuration ← Webhook

أرجو إرسال البيانات بشكل آمن (لا ترسلها على واتساب).

شكراً،
[اسمك]

---

## رابعاً: ملخص الخطوات بالترتيب

```
الخطوة 1 — الآن (Self-Service)
    أنشئ/افتح Meta Developer App
    أضف WhatsApp Product
    انسخ App ID + App Secret

الخطوة 2 — الآن (Self-Service)
    أنشئ Embedded Signup Configuration
    انسخ Config ID

الخطوة 3 — الآن (Self-Service)
    أنشئ System User في Business Manager
    اعمل Generate Token
    انسخ الـ Token

الخطوة 4 — الآن (Self-Service)
    ضع البيانات في Botzo
    احصل على Callback URL + Verify Token من Botzo
    ضعهم في Meta Webhook

الخطوة 5 — يحتاج انتظار Meta
    قدّم App Review للصلاحيات الثلاث
    انتظر الموافقة (5 أيام - 4 أسابيع)

الخطوة 6 — بعد الموافقة
    اختبر الـ Flow كاملاً
    فعّل الميزة للعملاء
```

---

## خامساً: حالة المتطلبات

| المتطلب | من يفعله | الوقت |
|---------|---------|-------|
| App ID + Secret | أنت — من البورتال | دقائق |
| Config ID | أنت — من البورتال | دقائق |
| System User Token | أنت — من Business Manager | 15 دقيقة |
| Webhook Setup | أنت — بعد Botzo يعطيك القيم | 10 دقائق |
| App Review للصلاحيات | تقديم طلب لـ Meta | 5 أيام - 4 أسابيع |
| Business Verification | تقديم مستندات لـ Meta | 3 - 14 يوم |

---

*آخر تحديث: مايو 2026*
