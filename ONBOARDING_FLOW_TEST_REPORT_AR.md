# تقرير اختبار فلو التسجيل والتفعيل واختيار الباقة

تاريخ الاختبار: 2026-05-24  
بيئة الاختبار: Localhost  
الرابط المستخدم: `http://localhost:8000`

## الخلاصة

الفلو جاهز للاختبار اليدوي.

تم اختبار المسار التالي بنجاح:

1. فتح صفحة التسجيل.
2. إنشاء حساب جديد.
3. ظهور صفحة تفعيل البريد.
4. تفعيل البريد باستخدام رابط signed.
5. فتح Dashboard بعد التفعيل.
6. فتح صفحة الباقات.
7. اختيار باقة مجانية.
8. الانتقال إلى صفحة Billing.
9. التأكد من إنشاء اشتراك active في قاعدة البيانات.

## بيانات الحساب الجاهز للاختبار

استخدم هذه البيانات للدخول:

```text
URL: http://localhost:8000/login
Email: ahmed@gmail.com
Password: Ahmed123
```

حالة الحساب بعد الاختبار:

```text
Email verified: نعم
Organization: Ahmed QA Workspace
Subscription status: active
Plan: QA Free Starter
```

ملاحظة: الحساب موجود الآن بالفعل. لو أردت اختبار التسجيل من البداية بنفس الإيميل فلن ينفع لأنه أصبح مستخدمًا. لاختبار التسجيل من الصفر استخدم إيميل جديد، أو احذف/صفّر بيانات هذا الحساب من قاعدة البيانات.

## نتيجة الاختبار الآلي بالمتصفح

تم تشغيل فلو حقيقي عبر Playwright Chromium على `http://localhost:8000`.

النتيجة:

```json
{
  "email": "ahmed@gmail.com",
  "password": "Ahmed123",
  "base": "http://localhost:8000",
  "verificationUrlHost": "localhost:8000",
  "freeCardCount": 1,
  "db": {
    "user_exists": true,
    "email_verified": true,
    "organization": "Ahmed QA Workspace",
    "subscription_status": "active",
    "plan": "QA Free Starter"
  }
}
```

## Screenshots

تم حفظ لقطات الشاشة هنا:

- `test-artifacts/ahmed-onboarding-flow/01-signup.png`
- `test-artifacts/ahmed-onboarding-flow/02-after-signup.png`
- `test-artifacts/ahmed-onboarding-flow/03-after-verify.png`
- `test-artifacts/ahmed-onboarding-flow/04-subscription.png`
- `test-artifacts/ahmed-onboarding-flow/05-plan-selected.png`
- `test-artifacts/ahmed-onboarding-flow/06-after-plan-continue.png`

## خطوات الاختبار اليدوي

### اختبار الدخول بالحساب الجاهز

1. افتح:
   ```text
   http://localhost:8000/login
   ```
2. أدخل:
   ```text
   Email: ahmed@gmail.com
   Password: Ahmed123
   ```
3. يجب أن تدخل على Dashboard مباشرة.
4. من القائمة افتح `Billing & Subscription`.
5. يجب أن ترى:
   ```text
   Plan Details: QA Free Starter
   Subscription: active
   Invoice total: 0.00
   ```

### اختبار التسجيل من الصفر

1. افتح:
   ```text
   http://localhost:8000/signup
   ```
2. استخدم إيميل جديد غير `ahmed@gmail.com`.
3. بعد التسجيل يجب أن تظهر صفحة:
   ```text
   Verify your email
   ```
4. في بيئة local، رابط التفعيل يظهر في:
   ```text
   storage/logs/laravel.log
   ```
5. افتح رابط التفعيل.
6. بعد التفعيل يجب أن تدخل على Dashboard.
7. افتح:
   ```text
   http://localhost:8000/subscription
   ```
8. اختر `QA Free Starter`.
9. اضغط `Continue`.
10. يجب أن تنتقل إلى:
    ```text
    http://localhost:8000/billing
    ```

## ملاحظات مهمة

- استخدم `localhost` وليس `127.0.0.1` أثناء الاختبار، لأن `APP_URL` مضبوط على:
  ```text
  http://localhost:8000
  ```
- لو فتحت التسجيل على `127.0.0.1` ثم رابط التفعيل اتولد على `localhost`، قد تفقد session ويطلب منك login أو verify مرة أخرى. هذا اختلاف بيئي وليس خلل في فلو التسجيل نفسه.
- لا توجد بوابة دفع مفعلة في بيئة الاختبار الحالية، لذلك تم اختبار باقة مجانية بقيمة `0.00` حتى يكتمل الفلو بدون الخروج إلى Moyasar.

## اختبارات الكود

تمت إضافة اختبار Feature يغطي الفلو بالكامل:

```text
tests/Feature/SignupActivationPlanSelectionFlowTest.php
```

وتم تشغيل مجموعة اختبارات الفلو والباقات والدفع:

```bash
php artisan test tests/Feature/SignupActivationPlanSelectionFlowTest.php tests/Feature/AuthOrganizationFlowTest.php tests/Feature/UserBillingWorkspaceTest.php tests/Feature/BillingCheckoutIntentFlowTest.php
```

النتيجة:

```text
26 passed
339 assertions
```

## القرار

الفلو جاهز للاختبار اليدوي بالحساب:

```text
ahmed@gmail.com / Ahmed123
```

ولا توجد مشكلة حالية تمنع التسجيل أو التفعيل أو اختيار الباقة المجانية.
