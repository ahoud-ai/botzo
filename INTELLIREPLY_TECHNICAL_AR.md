# 🤖 التوثيق التقني الشامل — ميزة Smart Router AI Assistant (IntelliReply)

> **المشروع:** Botzo  
> **المسار الرئيسي:** `/automation/ai`  
> **تاريخ التحليل:** يونيو 2026  
> **مُعد التقرير:** تحليل آلي شامل بواسطة Claude Code  
> **لغة الكتابة:** عربية — لهجة مصرية بسيطة وواضحة

---

## 📋 فهرس المحتويات

1. [ما هو IntelliReply؟](#1-ما-هو-intellireply)
2. [البنية المعمارية الكاملة](#2-البنية-المعمارية-الكاملة)
3. [تتبع التدفق الكامل من الأول للآخر](#3-تتبع-التدفق-الكامل-من-الأول-للآخر)
4. [الملفات والكلاسات الرئيسية](#4-الملفات-والكلاسات-الرئيسية)
5. [قاعدة البيانات](#5-قاعدة-البيانات)
6. [نظام الـ RAG (ذاكرة المساعد)](#6-نظام-الـ-rag-ذاكرة-المساعد)
7. [نظام API Keys وإدارة المفاتيح](#7-نظام-api-keys-وإدارة-المفاتيح)
8. [نظام حساب الاستخدام والـ Billing](#8-نظام-حساب-الاستخدام-والـ-billing)
9. [دعم الصوت (Audio)](#9-دعم-الصوت-audio)
10. [الـ Prompts وآلية قرار الرد](#10-الـ-prompts-وآلية-قرار-الرد)
11. [معالجة الأخطاء والـ Fallbacks](#11-معالجة-الأخطاء-والـ-fallbacks)
12. [الصلاحيات والأمان](#12-الصلاحيات-والأمان)
13. [مخطط الـ Flow Diagram](#13-مخطط-الـ-flow-diagram)

---

## 1. ما هو IntelliReply؟

**IntelliReply** هو اسم الـ module الداخلي للميزة اللي بتظهر للمستخدم باسم **"AI Reply Assistant"** أو **"Smart Router"**.

### الفكرة الأساسية بالبساطة:
- الزبون بيبعت رسالة على WhatsApp
- النظام بياخد الرسالة دي، ويدور في قاعدة المعرفة اللي رفعتها
- يلاقي أقرب مستند بيجاوب على السؤال
- يبعت الجواب تلقائياً على WhatsApp **من غير تدخل بشري**

### أين يوجد في الكود؟
```
modules/
└── IntelliReply/
    ├── Controllers/
    │   ├── MainController.php      ← صفحة الإعدادات
    │   ├── ChatController.php      ← اقتراح الرد للأجنت
    │   └── DocumentController.php ← رفع المستندات
    ├── Models/
    │   └── Document.php
    ├── Pages/User/
    │   └── Index.vue               ← الصفحة الأمامية
    ├── Services/
    │   └── AIResponseService.php   ← المنطق الأساسي للـ AI
    ├── Providers/
    │   └── IntelliServiceProvider.php
    └── routes.php

app/Services/IntelliReply/
├── AiKeyResolver.php              ← إدارة مفاتيح OpenAI
└── AiUsageLimiterService.php      ← حساب وتحديد الاستخدام
```

---

## 2. البنية المعمارية الكاملة

### طبقات النظام (Layers):

```
┌─────────────────────────────────────────────────────────────┐
│                    LAYER 1: Frontend (Vue.js)                │
│   modules/IntelliReply/Pages/User/Index.vue                 │
│   ← صفحة الإعدادات + جدول المستندات + مؤشرات الاستخدام    │
└─────────────────────────────────────────────────────────────┘
                              ↕ HTTP (Inertia.js)
┌─────────────────────────────────────────────────────────────┐
│                    LAYER 2: Controllers                      │
│   MainController.php   → إعدادات المنظمة + تفعيل/إيقاف    │
│   DocumentController.php → رفع وحذف المستندات              │
│   ChatController.php   → اقتراح رد للأجنت البشري           │
└─────────────────────────────────────────────────────────────┘
                              ↕ 
┌─────────────────────────────────────────────────────────────┐
│                    LAYER 3: Services                         │
│   AIResponseService.php      → المنطق الأساسي              │
│   AiKeyResolver.php          → حل مفتاح OpenAI             │
│   AiUsageLimiterService.php  → حدود الاستخدام              │
│   AutoReplyService.php       → تسلسل الردود                │
└─────────────────────────────────────────────────────────────┘
                              ↕
┌─────────────────────────────────────────────────────────────┐
│                    LAYER 4: Database                         │
│   documents                    → قاعدة المعرفة + Embeddings│
│   organization_ai_usage_counters → عداد الاستخدام          │
│   contacts.ai_assistance_enabled → حالة AI لكل جهة اتصال  │
│   organizations.metadata       → إعدادات AI للمنظمة        │
│   settings                     → إعدادات الـ Platform       │
└─────────────────────────────────────────────────────────────┘
                              ↕
┌─────────────────────────────────────────────────────────────┐
│                    LAYER 5: External APIs                    │
│   OpenAI API (chat/completions)   → توليد الردود النصية    │
│   OpenAI API (embeddings)         → تحويل النص لـ vectors  │
│   OpenAI API (audio/transcriptions) → تحويل صوت لنص       │
│   WhatsApp API (Meta)             → إرسال الرد             │
└─────────────────────────────────────────────────────────────┘
```

---

## 3. تتبع التدفق الكامل من الأول للآخر

### 3.1 مسار الرسالة الواردة (Inbound Message Flow)

```
[زبون يبعت رسالة WhatsApp]
           ↓
[WhatsApp API → Webhook → POST /api/webhook/{token}]
           ↓
[ProcessWebhookJob::handle() — بيشتغل في Queue]
   الملف: app/Jobs/ProcessWebhookJob.php
           ↓
[بيحفظ الرسالة في جدول chats]
[بيعمل contact لو مكنش موجود]
           ↓
[AutoReplyService::checkAutoReply($chat, $isNewContact)]
   الملف: app/Services/AutoReplyService.php:110
           ↓
[replySequence() — بيشغل التسلسل حسب ترتيب المنظمة]
   الترتيب الافتراضي:
   1. Automation Flows (Flow Builder)
   2. Basic Replies (ردود كلمات مفتاحية)
   3. AI Reply Assistant ← هنا بييجي IntelliReply
           ↓
[handleAIReplyAssistant()]
   ↓
[AIResponseService::handleAIResponse($chat, $receivedMessage)]
   الملف: modules/IntelliReply/Services/AIResponseService.php:79
```

### 3.2 ما الذي يحدث داخل handleAIResponse؟

```
[دخل handleAIResponse($chat, $receivedMessage)]
       ↓
[تحقق: هل module "AI Assistant" مفعّل للمنظمة؟]
   لأ → return false (مفيش رد)
   ↓
[جيب إعدادات المنظمة من organizations.metadata]
       ↓
[تحقق: هل ai.active = true؟]
   لأ → return false
       ↓
[جيب آخر رسالة وردت من الزبون]
   extractLastMessage($organizationId, $contactId)
   أنواع مدعومة: text / button / interactive / audio
   لو صوت → يتحول لنص عن طريق OpenAI Whisper
       ↓
[ابحث عن أقرب مستند في قاعدة المعرفة]
   findClosestDocumentByQuery($organizationId, $receivedMessage)
   ← بيستخدم OpenAI Embeddings + Cosine Similarity
   لو ملقاش مستند → return false (مفيش رد)
       ↓
[جيب آخر 10 رسائل للمحادثة]
   conversationHistory ← للـ context
       ↓
[تحقق من Stop Keywords]
   لو الرسالة فيها كلمة إيقاف → أوقف AI للجهة دي وreturn false
       ↓
[تحقق من Start Keywords]
   لو الرسالة فيها كلمة تشغيل → فعّل AI للجهة دي
       ↓
[تحقق: هل AI مفعّل لهذه الجهة؟ (ai_assistance_enabled)]
   + تحقق من enable_automatic_responses
       ↓
[لو enable_automatic_responses=true AND ai_assistance_enabled=false]
   مع Ticketing: شغّل AI لو مفيش رد من فريق من آخر فتح تذكرة
   بدون Ticketing: شغّل AI لو الزبون رسل ≤1 رسالة في آخر 24 ساعة
       ↓
[التحقق: هل is_ai_active=true AND ai_assistance_enabled=true؟]
   لأ → return false
       ↓
[تحقق من Usage Limits]
   AiUsageLimiterService::canUseText() / canUseAudio()
   لو وصل الحد → return false + log warning
       ↓
[ابعت الـ Context لـ OpenAI Chat Completions API]
   chat($organizationId, $type, $context, $apiKeyBundle)
       ↓
[لو نجح → ابعت الرد على WhatsApp]
   نص: WhatsappService::sendMessage()
   صوت: WhatsappService::sendMedia()
       ↓
[سجّل الاستخدام في قاعدة البيانات]
   AiUsageLimiterService::consumeText() / consumeAudio()
       ↓
[return true ← اتبعت رد]
```

---

## 4. الملفات والكلاسات الرئيسية

### 4.1 MainController.php
**المسار:** `modules/IntelliReply/Controllers/MainController.php`

| الدالة | الوظيفة |
|--------|---------|
| `index()` | تعرض صفحة `/automation/ai` وتجيب إعدادات المنظمة والمستندات |
| `activate()` | تفعيل/إيقاف AI للمنظمة (Toggle) |
| `setup()` | حفظ إعدادات OpenAI (model, key, temperature, etc.) |
| `assistant_setup()` | حفظ الكلمات المفتاحية وإعداد الردود التلقائية |
| `enable_ai_assistant()` | تفعيل/إيقاف AI لجهة اتصال معينة |

**الثوابت الافتراضية:**
```php
DEFAULT_MODEL = 'gpt-4o-mini'
DEFAULT_MAX_TOKENS = 512
DEFAULT_TEMPERATURE = 0.7
DEFAULT_EMBEDDING_MODEL = 'text-embedding-3-small'
```

---

### 4.2 AIResponseService.php
**المسار:** `modules/IntelliReply/Services/AIResponseService.php`

هذا هو **قلب النظام**. أهم دالتين:

#### `handleAIResponse($chat, $receivedMessage)` — الرد التلقائي
- بيتشغل لما يجي webhook من WhatsApp
- بيقرر هل يرد تلقائياً أم لأ
- بيعمل كل التحققات المذكورة في القسم 3.2

#### `suggestReply(int $organizationId, Contact $contact, ?array $apiKeyBundle)` — اقتراح للأجنت
- بيتشغل لما الأجنت البشري يضغط زرار "AI Suggest" في صفحة المحادثة
- بيجيب اقتراح رد بدون إرساله تلقائياً
- بيعمل نفس خطوات RAG بس بـ Prompt مختلف

#### `chat($organizationId, $type, $context, $apiKeyBundle)` — استدعاء OpenAI
- بيبعت الـ context لـ OpenAI API
- بيدعم نوعين:
  - **Text Models:** POST `https://api.openai.com/v1/chat/completions`
  - **Audio Models:** نفس الـ endpoint بس بـ modalities: ["text", "audio"]

#### `findClosestDocumentByQuery($organizationId, $query)` — البحث بالـ Embeddings
- بيحول السؤال لـ vector رقمي (Embedding)
- بيقارنه بكل مستندات المنظمة
- بيرجع أقرب مستند باستخدام **Cosine Similarity**

#### `cosineSimilarity($vecA, $vecB)` — حساب التشابه
```php
// المعادلة:
distance = 1 - (dot_product(A, B) / (norm(A) * norm(B)))
// كلما قل الرقم كلما كان التشابه أكبر
```

---

### 4.3 DocumentController.php
**المسار:** `modules/IntelliReply/Controllers/DocumentController.php`

#### `store()` — رفع مستند جديد
```
رفع ملف (PDF/DOCX/TXT)
    ↓
استخراج النص من الملف
    ↓
تقطيع النص إلى chunks (1600 حرف لكل chunk)
    ↓
توليد Embedding لكل chunk عبر OpenAI
    ↓
حفظ المستند + الـ Embeddings في قاعدة البيانات
```

**أنواع الملفات المدعومة:**
- `.txt` ← بـ `file_get_contents()`
- `.pdf` ← بـ `Smalot\PdfParser`
- `.doc` / `.docx` ← بـ `PhpOffice\PhpWord`

---

### 4.4 AiKeyResolver.php
**المسار:** `app/Services/IntelliReply/AiKeyResolver.php`

بيحدد **أي مفتاح OpenAI يُستخدم** حسب السياسة المحددة.

#### 3 سياسات للمفتاح:
| السياسة | المعنى |
|---------|--------|
| `global_only` | يستخدم مفتاح الـ Platform فقط |
| `organization_only` | يستخدم مفتاح المنظمة فقط |
| `hybrid` | يحاول المفتاح المفضّل ثم يرجع للثاني |

---

### 4.5 AiUsageLimiterService.php
**المسار:** `app/Services/IntelliReply/AiUsageLimiterService.php`

بيتحكم في **عدد الردود المسموح بيها** في كل دورة فوترة.

**الحدود دي بتتخزن في:**
- `subscription_plans.metadata['ai_text_response_limit']` ← حد الردود النصية
- `subscription_plans.metadata['ai_audio_response_limit']` ← حد الردود الصوتية
- `settings['ai_system_key_monthly_quota']` ← الحد الشهري للمفتاح العالمي

---

### 4.6 ChatController.php
**المسار:** `modules/IntelliReply/Controllers/ChatController.php`

بيخدم endpoint واحد: `GET /automation/chat/suggestion`

**ده بيستخدمه الأجنت البشري لما يضغط على "AI Suggest"** — مش بيرسل أي حاجة تلقائياً.

---

## 5. قاعدة البيانات

### 5.1 جدول `documents` — قاعدة المعرفة
```sql
id              -- رقم تلقائي
uuid            -- معرف فريد للمستند
organization_id -- المنظمة المالكة
source          -- 'File' (مصدر المستند)
title           -- عنوان المستند
content         -- النص الكامل للمستند
embeddings      -- JSON: مصفوفة vectors رقمية (مولّدة من OpenAI)
status          -- 'Pending' أثناء المعالجة / 'Complete' بعدها
created_at
updated_at
```

### 5.2 جدول `organization_ai_usage_counters` — عداد الاستخدام
```sql
id              -- رقم تلقائي
organization_id -- المنظمة
subscription_id -- الاشتراك (NULL = مفتاح عالمي)
period_start    -- بداية الفترة
period_end      -- نهاية الفترة
text_count      -- عدد الردود النصية
audio_count     -- عدد الردود الصوتية
```

### 5.3 عمود `contacts.ai_assistance_enabled`
- `0` = AI مطفي لهذه الجهة
- `1` = AI شغّال لهذه الجهة

### 5.4 إعدادات AI في `organizations.metadata` (JSON)
```json
{
  "ai": {
    "active": true,
    "platform": "OpenAI",
    "api_key_encrypted": "eyJ...",
    "key_source": "auto",
    "model": "gpt-4o-mini",
    "embedding_model": "text-embedding-3-small",
    "max_tokens": 512,
    "temperature": 0.7,
    "allow_audio_response": false,
    "voice": null,
    "enable_automatic_responses": false,
    "start_keywords": "مرحبا,hi,start",
    "stop_keywords": "agent,وكيل,stop"
  }
}
```

### 5.5 إعدادات AI في جدول `settings`
```
ai_key_policy              ← hybrid / global_only / organization_only
ai_allow_org_override      ← 1 / 0
ai_global_api_key_encrypted ← مفتاح OpenAI العالمي (مشفّر)
ai_global_api_key          ← [قديم - تم إيقافه]
enable_ai_billing          ← 1 / 0 (هل يُطبق حد الاستخدام؟)
```

---

## 6. نظام الـ RAG (ذاكرة المساعد)

**RAG = Retrieval Augmented Generation** — يعني المساعد مش بيرد من الفراغ، بيدور في مستنداتك الأول.

### كيف يعمل؟

#### خطوة 1: وقت رفع المستند (Indexing)
```
[ملف PDF/DOCX/TXT]
       ↓
[استخراج النص]
       ↓
[تقطيع إلى chunks بحد أقصى 1600 حرف]
       ↓
لكل chunk:
   [OpenAI Embeddings API]
   model: text-embedding-3-small
       ↓
   [vector رقمي: مصفوفة ~1536 رقم]
       ↓
[حفظ الـ vectors في documents.embeddings (JSON)]
```

#### خطوة 2: وقت ورود سؤال (Retrieval)
```
[رسالة الزبون: "كيف أعيد الطلب؟"]
       ↓
[OpenAI Embeddings API → vector للسؤال]
       ↓
[مقارنة vector السؤال بكل vectors المستندات]
   باستخدام Cosine Similarity
       ↓
[اختيار المستند الأقرب]
       ↓
[دمج المستند + السؤال + تاريخ المحادثة في Prompt]
       ↓
[OpenAI Chat Completions → رد ذكي]
```

### تنبيه مهم في الكود:
الكود بيستخدم **أدنى Cosine Distance** (مش أعلى similarity مباشرةً). المعادلة:
```php
$distance = 1 - ($dotProduct / (sqrt($normA) * sqrt($normB)));
// أصغر distance = أعلى تشابه = أفضل مستند
```

---

## 7. نظام API Keys وإدارة المفاتيح

### المنطق الكامل لـ AiKeyResolver:

```
resolveForOrganization($metadata, $preferredSource, $organizationId)
          ↓
[جيب السياسة من settings.ai_key_policy]
          ↓
┌─────────────────────────────────────────────┐
│ سياسة global_only:                          │
│   ← استخدم settings.ai_global_api_key فقط  │
└─────────────────────────────────────────────┘
          أو
┌─────────────────────────────────────────────┐
│ سياسة organization_only:                    │
│   ← استخدم organizations.metadata.ai_key   │
└─────────────────────────────────────────────┘
          أو
┌─────────────────────────────────────────────┐
│ سياسة hybrid:                               │
│   preferred = organization:                 │
│     → جرّب org key أولاً                   │
│     → لو مفيش: جرّب global key             │
│   preferred = global:                       │
│     → جرّب global key أولاً                │
│     → لو مفيش: جرّب org key               │
│   preferred = auto:                         │
│     → جرّب org key أولاً                   │
│     → لو مفيش: جرّب global key             │
└─────────────────────────────────────────────┘
```

### تشفير المفاتيح:
- مفاتيح المنظمات: مشفّرة بـ `Crypt::encryptString()` في `organizations.metadata.ai.api_key_encrypted`
- المفتاح العالمي: مشفّر في `settings.ai_global_api_key_encrypted`
- **لا يُخزن أي مفتاح plain text** في قاعدة البيانات (بعد التحديث الأمني)

---

## 8. نظام حساب الاستخدام والـ Billing

### المنطق الكامل:

```
canUseText($organizationId, $keySource) / canUseAudio(...)
          ↓
[هل enable_ai_billing = 1 في settings؟]
   لأ → return true (مفيش حد)
          ↓
[هل المنظمة عندها subscription فعّال؟]
   لأ → return false
          ↓
[هل المفتاح من المنظمة نفسها (organization key)?]
   آه → مفيش حد على الـ plan
          ↓
[جيب limit من subscription_plans.metadata]
   ai_text_response_limit
   ai_audio_response_limit
          ↓
[جيب العداد الحالي من organization_ai_usage_counters]
          ↓
[لو count >= limit → return false (حد مكتمل)]
          ↓
[لو المفتاح عالمي global: تحقق من الحد الشهري الإضافي]
   ai_system_key_monthly_quota
          ↓
[return true/false]
```

### consume() — تسجيل الاستخدام:
بيتشغل داخل **Database Transaction** عشان يمنع race conditions.

---

## 9. دعم الصوت (Audio)

### تحويل الصوت الوارد لنص (Speech-to-Text):
```
[رسالة صوتية من الزبون]
       ↓
[جيب ملف الصوت من Local/S3]
       ↓
[OpenAI Audio Transcriptions API]
   model: gpt-4o-mini-transcribe (أو whisper-1 كـ fallback)
       ↓
[النص المحوّل → يستخدم في البحث والـ Prompt]
```

### تحويل رد AI لصوت (Text-to-Speech):
```
[OpenAI Chat Completions بـ modalities: ["text", "audio"]]
   model: gpt-audio-1.5 (أو أي audio-capable model)
   audio.format: "mp3"
   audio.voice: الصوت المختار
       ↓
[الرد: base64 encoded audio]
       ↓
[حفظ في Local/S3]
       ↓
[WhatsApp sendMedia() ← ملف صوت]
```

**تحويل الصيغ:**
بيستخدم `FFmpeg` لتحويل صيغ الصوت الغير مدعومة لـ MP3.

---

## 10. الـ Prompts وآلية قرار الرد

### System Prompt للرد التلقائي:
```
"You are a customer support service AI Chatbot. 
You only provide answers that can be strictly found in context or documentation. 
If the user asks a question that lacks sufficient information or if it is not covered in the documentation, 
reply with 'Sorry, I don't have information about this. Could you specify what you'd like more information about?'. 
Here is the documentation: [المستند الأقرب]"
```

### System Prompt لاقتراح الرد للأجنت:
```
"You draft WhatsApp replies for a human support agent. 
Use only the provided documentation and conversation context. 
Match the customer language. 
Return one concise editable draft only, with no greetings unless appropriate and no explanations."
```

### بناء الـ Context المرسل لـ OpenAI:

```json
[
  {
    "role": "system",
    "content": "أنت مساعد دعم... وهذه الوثائق: [المستند]"
  },
  {
    "role": "user",
    "content": "أول رسالة للزبون"
  },
  {
    "role": "assistant",
    "content": "أول رد من النظام"
  },
  {
    "role": "user",
    "content": "الرسالة الجديدة"
  }
]
```

---

## 11. معالجة الأخطاء والـ Fallbacks

### Fallback Embedding Models:
```
text-embedding-3-small (الأول)
    → لو فشل: text-embedding-3-large
    → لو فشل: throw Exception
```

### Fallback Transcription Models:
```
gpt-4o-mini-transcribe (الأول)
    → لو فشل: whisper-1
    → لو كلهم فشلوا: return false (مفيش رد)
```

### Error Codes المستخدمة في اللوج:
| الكود | المعنى |
|-------|--------|
| `AI_KEY_MISSING` | مفيش مفتاح OpenAI مضبوط |
| `OPENAI_REQUEST_FAILED` | OpenAI API رجعت خطأ |
| `EMBEDDING_MODEL_UNAVAILABLE` | الـ Embedding model مش متاح للمفتاح ده |
| `RETRIEVAL_FAILED` | فشل البحث في قاعدة المعرفة |
| `AI_LIMIT_REACHED` | الزبون وصل حد الاستخدام |
| `AUDIO_PATH_UNREADABLE` | ملف الصوت مش موجود |
| `OPENAI_TRANSCRIPTION_FAILED` | فشل تحويل الصوت لنص |

### Retry Policy:
```php
->retry(1, 250) // محاولة واحدة إضافية بعد 250ms
->timeout(30)   // 30 ثانية للـ text
->timeout(45)   // 45 ثانية للـ audio
```

---

## 12. الصلاحيات والأمان

### الصلاحيات المطلوبة:

| العملية | الصلاحية |
|---------|---------|
| مشاهدة صفحة AI | `automations.view_all` |
| تفعيل/إيقاف AI | `automations.edit` |
| حفظ إعدادات AI | `automations.edit` |
| رفع مستند | `automations.add` |
| حذف مستند | `automations.delete` |
| اقتراح رد (للأجنت) | `chats.view_all` |

### تحقق من Module Activation:
```php
private function ensureAiAssistantEnabled(int $organizationId): void
{
    if (!CustomHelper::isModuleEnabled('AI Assistant', $organizationId)) {
        abort(404); // إخفاء الصفحة لو الـ module مش مفعّل
    }
}
```

### حماية مفاتيح API:
- المفاتيح لا تظهر أبداً في الـ response
- `unset($ai['api_key'], $ai['api_key_encrypted'])` قبل إرسال البيانات للـ frontend
- المفاتيح محفوظة مشفّرة في قاعدة البيانات

---

## 13. مخطط الـ Flow Diagram

```
WhatsApp Message
      │
      ▼
ProcessWebhookJob (Queue)
      │
      ├─ حفظ الرسالة في chats
      │
      ▼
AutoReplyService::checkAutoReply()
      │
      ▼
replySequence() ─── الترتيب:
      │             1. Automation Flows
      │             2. Basic Replies  
      │             3. AI Reply Assistant ◄──┐
      │                                      │
      ▼                                      │
handleAIReplyAssistant()                     │
      │                                      │
      ▼                                      │
AIResponseService::handleAIResponse()        │
      │                                      │
      ├─ [تحقق: AI مفعّل؟] ─── لأ ──────────┤
      ├─ [جيب آخر رسالة] ─── فارغة ──────────┤
      ├─ [RAG: دور مستند] ─── مش لاقي ────────┤
      ├─ [تحقق: Stop Keywords] ─ موجود ──────┤
      ├─ [تحقق: الجهة مفعّلة؟]               │
      ├─ [تحقق: Billing Limit]               │
      │                                      │
      ▼                                      │
[استدعاء OpenAI API]                         │
      │                                      │
      ├─ نجح ──────────────────────────────┐  │
      │                                   │  │
      ▼                                   ▼  ▼
[إرسال رد WhatsApp]              [return false]
[تسجيل الاستخدام]
[return true]
```

---

## 🔧 ملفات الإعداد

### config/intellireply.php
```php
'default_embedding_model' => env('INTELLIREPLY_DEFAULT_EMBEDDING_MODEL', 'text-embedding-3-small'),
'embedding_models' => ['text-embedding-3-small', 'text-embedding-3-large'],
'audio_response_models' => ['gpt-audio-1.5', 'gpt-audio', 'gpt-audio-mini', 'gpt-4o-audio-preview'],
'default_transcription_model' => 'gpt-4o-mini-transcribe',
'transcription_model_fallbacks' => ['gpt-4o-mini-transcribe', 'whisper-1'],
```

### config/models.php — النماذج المتاحة للاختيار
```
gpt-5.4, gpt-5.4-mini, gpt-5.4-nano
gpt-4.1, gpt-4.1-mini, gpt-4.1-nano
gpt-4o, gpt-4o-mini
o3-mini
gpt-audio-1.5, gpt-audio, gpt-audio-mini
```

---

*آخر تحديث: يونيو 2026*
