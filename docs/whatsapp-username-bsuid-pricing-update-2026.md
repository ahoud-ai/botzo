# تقرير: تحديثات WhatsApp Business Platform (Usernames / BSUID / Meta Business Agent / التسعير) — يوليو 2026

المصدر: إيميلين من Meta/WhatsApp Partner وصلوا في 2 يوليو 2026، بالإضافة لمحاولة فحص الرابط:
`https://developers.facebook.com/documentation/business-messaging/whatsapp/pricing/non-template-messages`
(الرابط رجّع بس الـ navigation menu بتاع الصفحة من غير محتوى تفصيلي — الصفحة على الأغلب محمية بـ auth أو JS-rendered، فالتقرير مبني بالكامل على نص الإيميلين).

---

## 1) ميزة الـ Usernames + BSUID (الأهم من الناحية التقنية)

### إيه اللي هيحصل
- WhatsApp هيسمح للـ **businesses** والمستخدمين العاديين إنهم يعملوا **username** فريد (مثال: `@JasperMarket`) بدل ما يظهروا برقم التليفون.
- بالنسبة للمستخدمين العاديين، الميزة دي بتدّيهم **خصوصية لرقم التليفون** — يقدروا يظهروا بـ username بدل الرقم.
- الـ businesses تقدر تحجز الـ username بتاعها دلوقتي من خلال:
  - WhatsApp Manager
  - Meta Business Suite
  - أو الـ **Username API**
- أي username متحجز وموافق عليه هيبقى **نشط وقابل للوصول** بس لما الميزة تتفعل رسميًا في المنطقة بتاعتكم.
- الإطلاق الفعلي هيبدأ **تدريجيًا خلال الأسابيع الجاية**.

### قدرات جديدة جاية في بداية يوليو
1. **Send to BSUID (Business-Scoped User ID)**
   - تقدر تبعت رسالة business-initiated لعميل باستخدام الـ BSUID بتاعه **حتى لو معندكش رقم تليفونه**.
2. **Phone Number Request CTA**
   - زرار جديد داخل المحادثة تطلب فيه من العميل يشارك رقم تليفونه.
   - لو العميل وافق، الرقم بييجي **جوه نفس الـ thread** وبيتضاف تلقائي لـ **Contact Book**.

### التأثير على الـ integration بتاعتكم
> "Once available, when a customer adopts a username and messages a business for the first time, the Cloud API will return the **BSUID with no phone number**."

- **التأثير الأولي محدود**: بيأثر بس على رسائل customer-initiated **جديدة** من عملاء عندهم username **ولسه ما بعتوش لكم قبل كده**.
- **Business as usual**: العملاء والمحادثات الحالية مش هيتأثروا — الرقم هيفضل بييجي طالما الـ Contact Book مفعّل (مفعّل افتراضيًا) وفيه تفاعل حديث. تقدروا تكملوا تبعتوا رسائل ومكالمات على الأرقام الموجودة عندكم، أو تطلبوا رقم العميل جوه المحادثة بالـ CTA الجديدة.
- **توصية Meta**: تكملوا الـ BSUID integration في أقرب وقت عشان تتجنبوا أي انقطاع في معالجة الرسائل الواردة (فيه Dummy API/Sandbox و webhook endpoints متاحين للتجربة دلوقتي).

### فحص فني على الكودبيز الحالي (project-current)

فحصت إزاي مشروعكم بيتعامل مع الرسائل الواردة من WhatsApp Cloud API، ولقيت المكان اللي هيتأثر مباشرة:

**الملف:** `app/Jobs/ProcessWebhookJob.php` (سطر 128-140 تقريبًا)

```php
$phone = $response['from'];

if (substr($phone, 0, 1) !== '+') {
    $phone = '+' . $phone;
}

$phone = PhoneService::getE164Format($phone);

// Check if contact exists in organization
$contact = Contact::where('organization_id', $organization->id)
    ->where('phone', $phone)
    ->whereNull('deleted_at')
    ->first();
```

- الكود ده بيفترض إن `$response['from']` **دايمًا رقم تليفون**.
- لما تبدأ تجيلكم رسائل من مستخدمين بـ username لأول مرة، الحقل `from` ممكن يبقى **BSUID مش رقم تليفون** → `PhoneService::getE164Format()` هيفشل أو يطلع قيمة غلط، والـ Contact هيتعمل بعمود `phone` فيه فعليًا BSUID مش رقم حقيقي.
- جدول `contacts` (migration: `database/migrations/2024_03_20_051414_create_contacts_table.php`) فيه بس الأعمدة: `first_name`, `last_name`, `phone`, `email`, `avatar`, `address`, `contact_group_id`... **مفيش عمود مخصص لتخزين BSUID**.

### الأكشن المطلوب (لسه مش عاجل، بس لازم يتحط في الخطة)
1. إضافة عمود `bsuid` (nullable, indexed) لجدول `contacts`.
2. تعديل `ProcessWebhookJob.php` عشان يفرّق بين رقم تليفون حقيقي وBSUID (مثلاً لو الفورمات مش رقم صالح، يتخزن كـ `bsuid` بدل `phone`).
3. لو هنستخدم ميزة **Send to BSUID**، هنحتاج endpoint/service جديد لبعت الرسائل بالـ BSUID بدل الرقم.
4. تفعيل أو دعم الـ **Phone Number Request CTA** كنوع رسالة تفاعلية جديدة (interactive message type) في نظام الردود بتاعكم لو حبيتوا تطلبوا رقم العميل بشكل رسمي.
5. متابعة تفعيل الميزة فعليًا في المنطقة بتاعتكم قبل التنفيذ (لسه محددوش تاريخ دقيق، بس "الأسابيع الجاية").

---

## 2) Meta Business Agent Platform (منافس مباشر لـ IntelliReply)

- Meta أطلقت رسميًا **Meta Business Agent Platform** — يسمح لأي business تبني "Meta Business Agent" خاص بيها.
- الـ Agent ده بيقدر يدير المحادثة **بالكامل**: من الرد على أسئلة المنتج لحد تأهيل العملاء (lead qualification) وإتمام عمليات الشراء، كله جوه واتساب.
- الشركات دلوقتي عندها مرونة في اختيار: **Meta Business Agent**، أو **third-party AI agents** (زي IntelliReply بتاعكم)، أو **agents بشريين**، أو مزيج بينهم مع handoff سلس بين الاتنين.

**ملاحظة تنافسية:** طالما عندكم موديول **IntelliReply** (AI Reply Assistant / Smart Router في `/automation/ai`) بيعمل نفس فكرة الرد الذكي، فـ Meta بقت منافس مباشر على نفس المساحة — وده يستاهل يتوصل لأصحاب المشروع كـ heads-up استراتيجي، مش بس تقني.

---

## 3) تحديثات التسعير الجديدة

### تصنيف الرسائل غير الـ template (non-template) بقى نوعين:
1. **Service messages** (زي الموجود حاليًا)
2. **Meta Business Agent messages** (جديد)

### الأسعار
- **من 1 أغسطس 2026**: فوترة **Meta Business Agent** بنظام **per-token**:
  - سعر عالمي موحّد: **$2.00 لكل مليون token**.
  - الرسالة الواحدة بتستهلك تقريبًا 20-25 ألف token → يعني حوالي **4-5 سنت أمريكي للرسالة**.
  - الفوترة شهرية.
2. **من 1 أكتوبر 2026**: رجوع الفوترة على **service messages** بنظام **per-message** (بالرسالة الواحدة) — بنفس أسعار utility وauthentication templates حسب كل دولة.
3. **من 1 أكتوبر 2026**: رجوع الفوترة على **utility templates** لو اتبعتت جوه نافذة الـ customer service window المفتوحة (24 ساعة).

> ملاحظة: أي رسالة non-template في نافذة الـ customer service window — سواء utility، service، أو Meta Business Agent — هتترتب عليها فوترة.

### جدول التواريخ المهمة

| التاريخ | التحديث |
|---|---|
| 15 مايو 2026 | ميزة Max price لـ Marketing Messages API في Limited Beta |
| 1 يونيو 2026 | إتاحة WhatsApp Business account Currency Migration APIs |
| 1 يوليو 2026 | إتاحة BRL وبدء الفوترة المحلية في البرازيل |
| 1 يوليو 2026 | أسعار جديدة لرسائل marketing وutility وauthentication |
| **1 يوليو 2026 (جديد)** | إطلاق Meta Business Agent Platform |
| **1 أغسطس 2026 (جديد)** | بدء فوترة Meta Business Agent ($2 / مليون token) |
| **1 أكتوبر 2026 (جديد)** | رجوع فوترة service messages (بالرسالة) |
| **1 أكتوبر 2026 (جديد)** | رجوع فوترة utility templates جوه الـ open window |

---

## 4) مصادر ومراجع (من الإيميل الأصلي)

- Developer Documentation (BSUID / Usernames)
- Help Center — How to reserve and manage a business username on WhatsApp Business Platform
- Conversations 2026 blog post — تقديم Meta Business Agent
- توثيق التسعير: `https://developers.facebook.com/documentation/business-messaging/whatsapp/pricing/non-template-messages`
- شرح Max-price feature لرسائل التسويق
- مقالة Business Help Center + توثيق APIs الخاصة بفوترة البرازيل (BRL)

---

## 5) خلاصة الأكشن items المقترحة على المشروع

- [ ] إضافة عمود `bsuid` لجدول `contacts` (migration جديدة)
- [ ] تعديل `ProcessWebhookJob.php` عشان يفرّق بين phone وBSUID عند استقبال رسالة جديدة
- [ ] (اختياري) دعم إرسال رسائل بالـ BSUID (Send to BSUID)
- [ ] (اختياري) دعم Phone Number Request CTA كنوع رسالة تفاعلية
- [ ] مراجعة تأثير رجوع فوترة service messages (أكتوبر 2026) على تكلفة تشغيل IntelliReply للعملاء
- [ ] متابعة إعلان تفعيل ميزة الـ Usernames في المنطقة الجغرافية المستهدفة قبل البدء في التنفيذ الفعلي
