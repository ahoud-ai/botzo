# الأخطاء

كل خطأ يرجع بنفس الشكل، وكود HTTP مناسب، و `code` ثابت تعتمد عليه في برنامجك:

```json
{
  "statusCode": 422,
  "code": "outside_24h_window",
  "message": "(#131047) Re-engagement message",
  "request_id": "9fb64a88-f0f1-4a36-a1e8-0ba3eef81360"
}
```

- **اعتمد على `code`** وليس على `message`، لأن نص الرسالة قد يتغير أو يُترجم.
- `request_id` يرجع أيضاً في الهيدر `X-Request-Id`. احفظه في سجلاتك وأرسله لنا عند طلب المساعدة.
- أخطاء واتساب فيها حقل إضافي `meta_error` بتفاصيل Meta الأصلية (`code` و `subcode` و `message` و `details`).
- أخطاء التحقق فيها `errors`: كائن بأسماء الحقول الخاطئة وسبب كل منها.

## متى أعيد المحاولة؟

| كود HTTP | المعنى | أعيد المحاولة؟ |
| --- | --- | --- |
| 400 | البيانات المرسلة غير صحيحة | لا. صحح البيانات |
| 401 | المفتاح ناقص أو خاطئ | لا. صحح المفتاح |
| 403 | غير مسموح (صلاحية، اشتراك، حد الباقة، حساب واتساب) | لا، حتى يتغير السبب |
| 404 | العنصر غير موجود | لا |
| 409 | طلب بنفس `Idempotency-Key` ما زال يُنفذ | نعم بعد ثانية |
| 422 | واتساب رفضت الرسالة، أو بيانات غير منطقية | لا، إلا لو تغير الوضع (مثلاً راسلك العميل فانفتحت النافذة) |
| 429 | تجاوزت حداً | نعم، بعد انتظار متزايد |
| 500 | خطأ عندنا | نعم بعد قليل، وأرسل لنا `request_id` لو تكرر |
| 502 | لم نستطع الوصول لخوادم واتساب | نعم بعد قليل |

## الأكواد العامة

| HTTP | `code` | السبب | الحل |
| --- | --- | --- | --- |
| 400 | `validation_error` | حقل ناقص أو بصيغة خاطئة. التفاصيل في `errors` | صحح الحقول المذكورة |
| 401 | `bearer_token_missing` | لا يوجد هيدر `Authorization` | أرسل `Authorization: Bearer <key>` |
| 401 | `bearer_token_invalid` | المفتاح خاطئ أو مُلغى أو دُوّر | انسخ المفتاح الصحيح |
| 403 | `api_key_scope_denied` | صلاحية المفتاح لا تسمح بهذا الطلب | استخدم مفتاحاً بصلاحية مناسبة |
| 403 | `subscription_inactive` | اشتراك المؤسسة منتهٍ | جدّد الاشتراك |
| 404 | `not_found` | `uuid` غير موجود في مؤسستك | تأكد من المعرّف |
| 409 | `idempotency_in_progress` | طلب بنفس المفتاح لم ينتهِ | انتظر وأعد |
| 422 | `idempotency_key_reused` | نفس `Idempotency-Key` مع بيانات مختلفة | استخدم مفتاحاً جديداً لكل رسالة مختلفة |
| 429 | `rate_limited` | طلبات كثيرة في الدقيقة | انتظر ثم أعد |
| 500 | `server_error` | خطأ غير متوقع | أعد المحاولة، وراسلنا بـ `request_id` |

## حدود الباقة والحساب

| HTTP | `code` | السبب |
| --- | --- | --- |
| 403 | `message_limit_reached` | وصلت لحد الرسائل في دورة الاشتراك الحالية |
| 403 | `contacts_limit_reached` | وصلت لحد جهات الاتصال (والرقم غير موجود مسبقاً) |
| 403 | `campaign_limit_reached` | وصلت لحد الحملات |
| 403 | `canned_replies_limit_reached` | وصلت لحد الردود التلقائية |
| 403 | `whatsapp_not_connected` | لم يُربط رقم واتساب بالمؤسسة |

## إرسال الرسائل (أسباب من واتساب)

| HTTP | `code` | كود Meta | السبب | الحل |
| --- | --- | --- | --- | --- |
| 422 | `outside_24h_window` | 131047 | مرت 24 ساعة على آخر رسالة من العميل | أرسل قالباً |
| 422 | `recipient_unavailable` | 131026 | الرقم ليس عليه واتساب، أو لا يستقبل | تأكد من الرقم |
| 422 | `recipient_is_sender` | 131021 | ترسل لنفس رقمك | استخدم رقماً آخر |
| 422 | `display_name_not_approved` | 131037 | اسم العرض لم توافق عليه Meta | من WhatsApp Manager |
| 422 | `meta_payment_issue` | 131042 | لا توجد وسيلة دفع في حساب Meta | أضف وسيلة دفع في Meta Business |
| 422 | `meta_ecosystem_limited` | 131049 | Meta لم ترسل الرسالة حفاظاً على تجربة العميل (رسائل تسويقية كثيرة) | خفف الرسائل التسويقية لهذا العميل |
| 422 | `unsupported_message_type` | 131051 | نوع رسالة غير مدعوم | |
| 422 | `media_download_failed` | 131052 | واتساب لم تستطع تحميل الملف من الرابط | تأكد أن الرابط عام ويفتح، والحجم والصيغة مقبولة |
| 422 | `media_upload_failed` | 131053 | فشل رفع الملف | أعد بملف آخر |
| 422 | `template_parameters_mismatch` | 132000 | عدد المتغيرات لا يطابق القالب | طابق عدد `{{n}}` في القالب |
| 422 | `template_not_found` | 132001 | القالب غير موجود بهذا الاسم أو اللغة، أو غير معتمد | راجع `GET /api/templates` |
| 422 | `template_text_too_long` | 132005 | النص بعد ملء المتغيرات طويل جداً | قصّر قيم المتغيرات |
| 422 | `template_policy_violation` | 132007 | القالب يخالف سياسات Meta | عدّل القالب |
| 422 | `template_parameters_invalid` | 132012 | صيغة متغير خاطئة (مثلاً صورة بدل نص) | طابق نوع المتغير |
| 422 | `template_paused` | 132015 | Meta أوقفت القالب مؤقتاً لجودة منخفضة | حسّن القالب أو استخدم آخر |
| 422 | `template_disabled` | 132016 | Meta عطّلت القالب نهائياً | أنشئ قالباً جديداً |
| 422 | `invalid_request` | 100 | معامل غير صحيح في الطلب | راجع `meta_error.details` |
| 422 | `meta_rejected` | أخرى | رفض لسبب آخر | التفاصيل في `meta_error` |
| 429 | `meta_rate_limited` | 130429 | تجاوزت سرعة الإرسال المسموحة للرقم | انتظر وأعد |
| 429 | `meta_spam_rate_limited` | 131048 | Meta حدّت الإرسال بسبب بلاغات أو حظر | راجع جودة رسائلك |
| 429 | `recipient_rate_limited` | 131056 | رسائل كثيرة لنفس العميل في وقت قصير | انتظر قبل الرسالة التالية لنفس العميل |
| 403 | `whatsapp_account_locked` | 131031 | حساب واتساب للأعمال مقفل | راجع Meta Business Support |
| 403 | `whatsapp_account_restricted` | 368 | الحساب مقيّد مؤقتاً لمخالفة | راجع Meta Business Support |
| 401 | `whatsapp_token_expired` | 190 | انتهت صلاحية ربط واتساب | أعد ربط الواتساب من الإعدادات |
| 502 | `meta_unreachable` | — | لم نستطع الوصول لخوادم واتساب | أعد المحاولة بعد قليل |
| 404 | `contact_not_found` | — | جهة الاتصال لا تخص مؤسستك | |

## الرسائل والحملات

| HTTP | `code` | السبب |
| --- | --- | --- |
| 404 | `message_not_found` | `message_id` غير موجود في مؤسستك |
| 404 | `template_not_found` | `uuid` القالب في الحملة غير صحيح |
| 404 | `contact_group_not_found` | `uuid` المجموعة في الحملة غير صحيح |
| 422 | `campaign_audience_empty` | المجموعة لا تحتوي جهات اتصال |

## موتشي

| HTTP | `code` | السبب |
| --- | --- | --- |
| 403 | `assistant_not_active` | المساعد الذكي مقفول في المؤسسة |
| 404 | `conversation_not_found` | المحادثة غير موجودة، أو توكن الجهاز ناقص أو خاطئ (المفتاح العام) |
| 429 | `too_many_conversations` | محادثات جديدة كثيرة من نفس الجهاز (المفتاح العام) |
| 429 | `too_many_messages` | رسائل سريعة جداً أو كثيرة في نفس المحادثة |

## مثال: معالجة الأخطاء في الكود

<div class="code-tabs">

```js
async function sendWhatsApp(payload, idempotencyKey, attempt = 1) {
  const res = await fetch('{{BASE_URL}}/api/send', {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${process.env.BOTZO_API_KEY}`,
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'Idempotency-Key': idempotencyKey,
    },
    body: JSON.stringify(payload),
  });
  const body = await res.json();
  if (res.ok) return body.message_id;

  const retryable = [409, 429, 500, 502, 503].includes(res.status);
  if (retryable && attempt < 5) {
    await new Promise((r) => setTimeout(r, 2 ** attempt * 1000));
    return sendWhatsApp(payload, idempotencyKey, attempt + 1);
  }
  if (body.code === 'outside_24h_window') {
    return sendTemplateInstead(payload.phone);
  }
  throw new Error(`Botzo ${body.code} (${body.request_id}): ${body.message}`);
}
```

```php
function sendWhatsApp(array $payload, string $idempotencyKey): ?string
{
    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $response = Http::withToken(env('BOTZO_API_KEY'))
            ->acceptJson()
            ->timeout(30)
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->post('{{BASE_URL}}/api/send', $payload);

        if ($response->successful()) {
            return $response->json('message_id');
        }
        if (! in_array($response->status(), [409, 429, 500, 502, 503], true)) {
            logger()->warning('Botzo send failed', $response->json());
            return null;
        }
        sleep(2 ** $attempt);
    }
    return null;
}
```

</div>
