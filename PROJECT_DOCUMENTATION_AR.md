# توثيق مشروع Botzo SA Client

## 1. فكرة المشروع والخدمة

المشروع هو منصة SaaS لإدارة تواصل الشركات مع العملاء عبر WhatsApp Cloud API. اسمه في `composer.json` هو `botzo/sa-client` ووصفه: `Botzo Saudi client WhatsApp SaaS workspace`.

الخدمة التي يقدمها المشروع:

- إدارة حسابات/مساحات عمل للشركات والمؤسسات.
- ربط كل مؤسسة بحساب WhatsApp Business/WABA ورقم WhatsApp.
- استقبال وإرسال رسائل WhatsApp من لوحة محادثات داخلية.
- إدارة جهات الاتصال والمجموعات والحقول المخصصة.
- إرسال حملات WhatsApp Templates لمجموعات عملاء.
- إدارة قوالب WhatsApp ومزامنتها مع Meta.
- أتمتة الردود بثلاث طبقات: Automation Flows، Basic Replies، AI Reply Assistant.
- إدارة تذاكر المحادثات وتوزيعها على أعضاء الفريق.
- اشتراكات وخطط وحدود استخدام وفواتير ودفع عبر Moyasar.
- API للمطورين باستخدام Bearer Token لإرسال رسائل وإدارة contacts/campaigns/templates.
- لوحة Admin لإدارة المستخدمين والمؤسسات والخطط والإعدادات واللغات والفواتير.
- واجهة عامة للموقع: home، pricing، product، contact، FAQs، docs، صفحات قانونية.

باختصار: المشروع CRM + WhatsApp inbox + campaign sender + automation builder + billing SaaS.

## 2. التقنيات المستخدمة

- Backend: Laravel 12 / PHP 8.2.
- Frontend: Vue 3 + Inertia.js + Vite + Tailwind.
- Realtime: Laravel broadcasting، غالبا Pusher حسب إعدادات `settings`.
- Queues: Laravel Queue jobs لحملات WhatsApp والويبهوكات والـ automation delays.
- WhatsApp: `netflie/whatsapp-cloud-api` مع HTTP calls مباشرة داخل `WhatsappService`.
- AI: `openai-php/client` وموديول `IntelliReply`.
- Payments: Moyasar.
- Storage: Local أو AWS S3.
- Tests: PHPUnit + Playwright لبعض أجزاء Flow Builder.

## 3. خريطة المجلدات

- `routes/`: تعريف كل مسارات النظام.
- `app/Http/Controllers/`: طبقة استقبال الطلبات Web/API/Admin/User.
- `app/Services/`: منطق الدومين الأساسي، مثل WhatsApp، Chat، Billing، Automation.
- `app/Jobs/`: أعمال غير متزامنة للويبهوك والحملات والأتمتة.
- `app/Models/`: Eloquent models لجداول النظام.
- `app/Http/Middleware/`: الحماية، اختيار المؤسسة، الاشتراك، الصلاحيات، اللغة.
- `resources/js/`: صفحات Vue ومكونات الواجهة.
- `resources/views/`: Blade root/errors/invoice.
- `database/migrations/`: بناء قاعدة البيانات وتطورها.
- `database/seeders/`: بيانات أولية مثل الخطط والإعدادات واللغات والصلاحيات.
- `modules/IntelliReply/`: موديول AI Assistant منفصل جزئيا.
- `config/`: إعدادات Laravel وإعدادات مخصصة مثل `automation_flows`, `platform`, `queue_profile`.
- `tests/`: اختبارات Feature و Unit.

## 4. دورة حياة الطلب داخل Laravel

1. الطلب يدخل من `index.php` ثم Laravel bootstrap.
2. `bootstrap/app.php` يحدد الراوتس: `routes/web.php`, `routes/api.php`, `routes/console.php`.
3. يتم تطبيق Middleware:
   - `Localization`: تحديد اللغة.
   - `SetOrganizationFromSession`: تحميل المؤسسة الحالية من session.
   - `HandleInertiaRequests`: تجهيز props المشتركة للـ Vue/Inertia.
   - `CheckAppStatus`: حالة التطبيق.
   - Middleware حسب المسار مثل auth/subscription/organization/permissions.
4. Route يوجه الطلب إلى Controller.
5. Controller غالبا يستدعي Service.
6. Service يتعامل مع Models/DB/APIs/Jobs.
7. الرد يرجع كـ Inertia page أو JSON أو redirect.

## 5. تقسيم الراوتس

الملف الرئيسي `routes/web.php` يستدعي:

- `routes/web/public.php`: الموقع العام، health endpoints، language، webhooks، payment callbacks.
- `routes/web/auth.php`: login/signup/social login/password reset/invite/logout/profile.
- `routes/web/user.php`: مساحة العميل بعد تسجيل الدخول والتحقق من البريد والمؤسسة والاشتراك.
- `routes/web/admin.php`: لوحة الإدارة.

الملف `routes/api.php` يستدعي public/utilities، ثم يفتح API محمي بـ:

- `AuthenticateBearerToken`
- `throttle:developer-api`

أهم API endpoints:

- `POST /api/send`
- `POST /api/send/template`
- `POST /api/send/media`
- `POST /api/campaigns`
- `GET/POST/PUT/DELETE /api/contacts`
- `GET/POST/PUT/DELETE /api/contact-groups`
- `GET /api/templates`
- `GET /api/verify`

## 6. أنواع المستخدمين والمساحات

يوجد نوعان رئيسيان من الدخول:

- User: العميل أو عضو الفريق داخل مؤسسة.
- Admin: مدير النظام.

المؤسسة `Organization` هي محور العزل بين العملاء. كل بيانات مهمة تقريبا مربوطة بـ `organization_id`.

المشروع يدعم:

- مؤسسة رئيسية `main`.
- فروع `branch` عبر `parent_organization_id`.
- فريق المؤسسة عبر `teams`.
- أدوار مخصصة عبر `organization_roles`.
- صلاحيات تفصيلية عبر `PermissionService`.

اختيار المؤسسة يتم عبر:

- `/select-organization`
- Session key: `current_organization`
- Middleware: `CheckOrganizationId` و `SetOrganizationFromSession`.

## 7. الاشتراكات وحدود الاستخدام

الاشتراك مرتبط بالمؤسسة:

- `organizations`
- `subscriptions`
- `subscription_plans`

الخطة تحدد حدودا مثل:

- عدد جهات الاتصال.
- عدد الرسائل.
- عدد أعضاء الفريق.
- ميزات AI Assistant.
- ميزات Flow Builder.
- حدود الـ active flows والـ monthly runs.

الخدمات المهمة:

- `SubscriptionService`
- `SubscriptionPlanLimitService`
- `SubscriptionFeatureUsageService`
- `OrganizationUsageSummaryService`
- `TrialAddonEntitlementService`

قبل عمليات حساسة، النظام يفحص الحدود. أمثلة:

- قبل إرسال رسالة من الشات: فحص `message_limit`.
- عند استقبال contact جديد من WhatsApp: فحص `contacts_limit`.
- عند نشر Flow: فحص حد `flow_builder_active_flows_limit`.
- Middleware `CheckSubscriptionStatus` يمنع استخدام أجزاء من النظام إذا الاشتراك غير صالح.

## 8. فلو تسجيل العميل وإنشاء المؤسسة

المسارات:

- `GET /signup`
- `POST /signup`
- `GET /email/verify`
- `POST /organization`
- `POST /select-organization`

الفلو:

1. المستخدم يسجل من `AuthController`.
2. يتم إنشاء User وربما إرسال تحقق البريد.
3. بعد التحقق، المستخدم يختار أو ينشئ مؤسسة.
4. `OrganizationService` ينشئ المؤسسة ويربط المستخدم كمالك داخل team.
5. يتم تخزين المؤسسة الحالية في session.
6. بعد ذلك يدخل المستخدم إلى dashboard وباقي وظائف workspace.

## 9. ربط WhatsApp

إعدادات WhatsApp تحفظ غالبا داخل `organizations.metadata` تحت مفتاح `whatsapp`.

أهم البيانات:

- `access_token` أو encrypted token.
- `app_id`
- `app_secret`
- `phone_number_id`
- `waba_id`
- `business_id`
- `verified_name`
- `account_review_status`
- `messaging_limit_tier`

الخدمات المهمة:

- `WhatsappService`: إرسال الرسائل والقوالب والميديا وإدارة templates/profile/subscriptions.
- `WhatsappTokenVault`: تشفير وفك تشفير التوكنات والأسرار.
- `WhatsappAccessTokenRefreshService`: تحديث/حل التوكنات طويلة المدى.
- `WhatsappTemplateReadinessService`: readiness/hints للقوالب.
- `WhatsappTemplateRequestGuardService`: تجهيز والتحقق من طلبات إنشاء القوالب.
- `EmbeddedSignupService`: فلو الربط عبر embedded signup.

المسارات المهمة:

- `POST /whatsapp/exchange-code`
- `POST /settings/whatsapp`
- `POST /settings/whatsapp/refresh`
- `POST /settings/whatsapp/token`
- `POST /settings/whatsapp/business-profile`
- `DELETE /settings/whatsapp/business-profile`

## 10. فلو استقبال رسائل WhatsApp

المسارات:

- `GET|POST /webhook/whatsapp/{identifier?}`
- `GET|POST /webhook/waba`

الفلو:

1. Meta ترسل GET للتحقق من webhook.
2. النظام يطابق `hub_verify_token`.
3. عند POST، `WebhookController` يحدد المؤسسة:
   - من route identifier.
   - أو من `waba_id` / `phone_number_id` في payload للـ embedded webhook.
4. يتم التحقق من التوقيع عبر `WebhookVerificationContract` والتنفيذ الفعلي `WhatsappWebhookVerificationService`.
5. يتم التأكد أن payload يخص نفس المؤسسة.
6. يتم Dispatch لـ `ProcessWebhookJob` على queue `webhook-media`.
7. `ProcessWebhookJob` يتعامل مع:
   - `messages`: رسائل واردة.
   - `statuses`: تحديثات حالة الرسائل.
   - `message_template_status_update`: تحديث حالة template.
   - account/phone/business capability updates.

عند استقبال رسالة:

1. يتم فحص حد inbound/messages.
2. يتم تطبيع رقم الهاتف إلى E164.
3. يتم البحث عن Contact داخل نفس المؤسسة.
4. إذا غير موجود، يتم إنشاؤه إذا حد contacts يسمح.
5. يتم إنشاء Chat inbound.
6. يتم إنشاء ChatLog.
7. لو الرسالة ميديا يتم Dispatch لـ `ProcessWebhookMediaJob`.
8. يتم بث `NewChatEvent` لتحديث الواجهة realtime.
9. يتم تشغيل `AutoReplyService` حسب تسلسل الردود.

## 11. فلو إرسال رسالة من الشات

المسارات:

- `GET /chats/{uuid?}`
- `POST /chats`
- `POST /chat/{uuid}/send/template`
- `GET /chats/{contactId}/messages`

الفلو:

1. `User\ChatController` يستقبل الطلب.
2. يستدعي `ChatService`.
3. `ChatService` يتأكد من:
   - المؤسسة الحالية.
   - صلاحية المستخدم لعرض/الرد على contact.
   - عدم تخطي حد الرسائل.
   - وجود رقم WhatsApp للعميل.
4. يتم تجهيز `WhatsappService` باستخدام بيانات المؤسسة.
5. حسب نوع الرسالة:
   - text: `WhatsappService::sendMessage`.
   - media: حفظ الملف local/S3 ثم `sendMedia`.
   - template: بناء template parameters ثم `sendTemplateMessage`.
6. `WhatsappService` ينشئ سجل Chat outbound ويربطه بـ ChatLog ويرجع response.
7. تحديثات status لاحقا تأتي من WhatsApp webhook.

## 12. فلو الردود التلقائية

الخدمة الأساسية: `AutoReplyService`.

عند وصول inbound chat، يتم تنفيذ `replySequence` حسب إعدادات المؤسسة:

1. Automation Flows.
2. Basic Replies.
3. AI Reply Assistant.

الترتيب قابل للتغيير عبر `metadata.automation.response_sequence`.

Basic Replies:

- تعتمد على `auto_replies`.
- trigger يمكن أن يكون كلمة أو كلمات مفصولة بفواصل.
- match criteria: exact match أو contains.
- الرد قد يكون text أو image أو audio أو template.

AI Reply Assistant:

- موجود في `modules/IntelliReply`.
- يعتمد على documents/embeddings و OpenAI API key.
- يشتغل فقط لو الموديول مفعل، ومفتاح AI موجود، والحد يسمح.

Automation Flows:

- يشتغل فقط إذا Flow Builder متاح للمؤسسة.
- يأخذ الرسالة ويبدأ أو يستكمل run.

## 13. Flow Builder والأتمتة المتقدمة

المسارات:

- `GET /automation/flows`
- `POST /automation/flows`
- `GET /automation/flows/{uuid}`
- `PUT /automation/flows/{uuid}`
- `POST /automation/flows/{uuid}/autosave`
- `POST /automation/flows/{uuid}/validate`
- `POST /automation/flows/{uuid}/publish`
- `POST /automation/flows/{uuid}/preview`
- `POST /automation/flows/{uuid}/pause`
- `POST /automation/flows/{uuid}/duplicate`
- assets upload/delete/show.

الخدمات:

- `AutomationFlowBuilderService`: إنشاء وتعديل ونشر وتكرار flows.
- `AutomationFlowGraphValidator`: التحقق من صحة الرسم.
- `AutomationFlowGraphCompiler`: تحويل graph إلى compiled JSON للتنفيذ.
- `AutomationFlowRuntimeService`: تشغيل flow عند وصول رسالة.
- `AutomationFlowRuntimeSupportService`: أدوات مساعدة للتنفيذ.
- `AutomationFlowNodeCatalog`: تعريف أنواع العقد.
- `AutomationFlowAssetService`: إدارة ملفات flow.
- `AutomationFlowNodeSecretService`: حماية secrets داخل nodes.

دورة حياة Flow:

1. المستخدم ينشئ Flow draft.
2. النظام يضع starter graph.
3. الواجهة تحفظ `graph_json` و `ui_json`.
4. عند publish:
   - يتم فحص ownership.
   - يتم فحص plan limits.
   - يتم فحص policy والـ graph.
   - يتم compile.
   - يتم إنشاء `automation_flow_versions`.
   - يتم ربط `current_version_id`.
5. عند inbound message:
   - Runtime يبحث عن active/waiting run لنفس contact.
   - لو يوجد run ينتظر input يتم استكماله.
   - لو لا يوجد، يبحث عن published flow مطابق للـ trigger.
   - يتم إنشاء `automation_flow_runs`.
   - التنفيذ يتحرك بين nodes ويكتب `automation_flow_run_steps`.

أنواع nodes الموجودة في runtime تشمل:

- trigger
- send_text
- send_media
- send_buttons
- send_list
- save_reply_to_field
- condition
- add_to_group
- remove_from_group
- update_contact_field
- assign_to_agent
- human_handoff
- handoff_to_ai_assistant
- send_email
- delay
- end

حالات run:

- `active`
- `waiting_input`
- `waiting_handoff`
- `waiting_delay`
- `completed`
- `failed`
- `cancelled`

## 14. فلو الحملات

المسارات:

- `GET /campaigns/{uuid?}`
- `POST /campaigns`
- `GET /campaigns/export/{uuid?}`
- `DELETE /campaigns/{uuid?}`
- `POST /api/campaigns`

الفكرة:

- الحملة تستخدم Template مع Contact Group.
- لكل مستلم يتم إنشاء `campaign_logs`.
- كل log يمثل رسالة واحدة وحالتها.

الفلو:

1. المستخدم ينشئ حملة من UI أو API.
2. `CampaignController` أو `ApiController::storeCampaign` يحفظ الحملة.
3. يتم إنشاء CampaignLog لكل contact.
4. يتم Dispatch لـ `SendCampaignMessageJob` لكل log أو جدولة حسب الوقت.
5. `SendCampaignMessageJob`:
   - يتأكد أن log حالته pending/retrying.
   - يتأكد أن وقت الإرسال حان.
   - يتأكد أن الحملة ongoing.
   - يطبق rate limit عبر `WhatsappRateLimiter`.
   - يغير log إلى ongoing داخل transaction.
   - يبني template حسب بيانات contact.
   - يرسل عبر `WhatsappService::sendTemplateMessageAsync`.
   - عند النجاح يحفظ `chat_id` ويجعل log success.
   - عند الفشل يحفظ failed وقد يجدول retry.
6. `ProcessCampaignMessagesJob` يعمل كل دقيقة:
   - يفعّل الحملات scheduled عندما يحين وقتها.
   - يعيد dispatch للـ pending logs العالقة.
   - يغلق الحملات إلى completed عند عدم وجود pending/ongoing/retrying logs.

## 15. فلو القوالب Templates

المسارات:

- `GET /templates/{uuid?}`
- `POST /templates`
- `POST /templates/{uuid}`
- `DELETE /templates/{uuid}`
- `GET /api/templates`

الخدمات:

- `TemplateService`
- `WhatsappService::createTemplate`
- `WhatsappService::updateTemplate`
- `WhatsappService::syncTemplates`
- `WhatsappService::deleteTemplate`

الفلو:

1. المستخدم ينشئ template.
2. النظام يتحقق من payload ومعايير WhatsApp.
3. يرسل الطلب إلى WhatsApp/Meta.
4. يحفظ template محليا في جدول `templates`.
5. عندما Meta ترسل webhook لحالة template، `ProcessWebhookJob` يحدث status.

## 16. جهات الاتصال والمجموعات

المسارات:

- `GET/POST/POST update/DELETE /contacts`
- `POST /contacts/import`
- `POST /contacts/assign-group`
- `GET/POST/POST update/DELETE /contact-groups`
- `POST /contact-groups/import`
- API equivalents تحت `/api`.

النماذج:

- `Contact`
- `ContactGroup`
- `ContactContactGroup`
- `ContactField`

المنطق:

- كل contact مربوط بـ organization.
- contact قد ينتمي لأكثر من group.
- يمكن إضافة حقول مخصصة.
- عند وصول رسالة جديدة من رقم غير موجود، ينشأ contact تلقائيا إذا حدود الخطة تسمح.
- `latest_chat_created_at` يحدث تلقائيا عند إنشاء Chat لتسريع ترتيب صندوق المحادثات.

## 17. التذاكر وتوزيع المحادثات

النماذج:

- `ChatTicket`
- `ChatTicketLog`
- `ChatNote`

الخدمات:

- `ChatTicketProvisioningService`
- `ChatService`
- `ChatAccessService`
- `AutomationFlowConversationHandoffService`

الفكرة:

- كل contact يمكن أن يكون له ticket.
- يمكن فتح/إغلاق/تعيين ticket لعضو فريق.
- صلاحية رؤية المحادثات قد تكون:
  - رؤية كل المحادثات.
  - رؤية المحادثات المعينة فقط.
- Automation Flow يمكنه تعيين المحادثة لوكيل أو عمل human handoff.

## 18. Billing والدفع

المسارات:

- User:
  - `/billing`
  - `/billing/usage`
  - `/billing/invoices/{invoice}`
  - `/pay`
  - `/subscription`
  - coupon apply/remove.
- Admin:
  - `/admin/billing`
  - `/admin/payment-logs`
  - `/admin/plans`
  - `/admin/payment-gateways`
- Public callbacks:
  - `/payment/moyasar`
  - `/payment/moyasar/webhook`

النماذج:

- `BillingCheckoutIntent`
- `BillingInvoice`
- `BillingPayment`
- `BillingTransaction`
- `BillingCredit`
- `BillingDebit`
- `BillingTaxRate`
- `Subscription`
- `SubscriptionPlan`
- `Coupon`

الخدمات:

- `BillingCheckoutIntentService`
- `BillingInvoiceService`
- `BillingService`
- `MoyasarService`
- `CouponService`
- `PaymentProcessorAvailabilityService`

الفلو:

1. العميل يختار خطة أو top-up/دفع.
2. يتم إنشاء Checkout Intent.
3. يتم بدء الدفع عبر Moyasar.
4. callback/webhook يكمل intent.
5. يتم تحديث subscription/payment/invoice.
6. النظام يستخدم subscription كـ source of truth لحدود التشغيل.

## 19. Admin Panel

لوحة الإدارة تحت `/admin` وبحماية:

- `auth:admin`
- `admin.permission:*`

تدير:

- Dashboard.
- Users/customers.
- Organizations.
- Plans/subscriptions.
- Billing/payment logs.
- Settings.
- Payment gateways.
- Languages/translations.
- Frontend content: pages, FAQs, testimonials, SEO, premium home.
- Addons/features: embedded signup, AI assistant, flow builder.
- Support tickets.
- Admin team and roles.

## 20. Developer API

المستخدم يستطيع إنشاء access tokens من:

- `/developer-tools/access-tokens`

API يستخدم `AuthenticateBearerToken`:

- يقرأ Bearer token.
- يطابقه مع `organization_api_keys`.
- يحدد المؤسسة.
- يطبق throttle.

استخداماته:

- إرسال رسائل.
- إرسال templates.
- إرسال media.
- إدارة contacts/groups/canned replies.
- إنشاء campaign.
- فحص صحة API key.

## 21. Frontend/Inertia

الواجهة مبنية Vue pages داخل:

- `resources/js/Pages/Auth`
- `resources/js/Pages/User`
- `resources/js/Pages/Admin`
- `resources/js/Pages/Frontend`
- `resources/js/Pages/FrontendPremium`

الـ Blade الرئيسي:

- `resources/views/app.blade.php`

`HandleInertiaRequests` يحقن props مشتركة مثل:

- user/admin.
- organization.
- permissions.
- unread counts.
- settings.
- locale/translations.
- subscription/feature states.

المكونات المشتركة في:

- `resources/js/Components`

Flow Builder UI في:

- `resources/js/Pages/User/Automation/Flows`
- `resources/js/Components/AutomationFlows`

## 22. Realtime Events

أهم event:

- `NewChatEvent`

يتم إطلاقه عند:

- وصول رسالة جديدة.
- تحديث status لرسالة.
- إنشاء ChatLog.

الهدف:

- تحديث Inbox مباشرة بدون refresh.

إعدادات Pusher تقرأ من جدول `settings` داخل `WebhookController` و Inertia props.

## 23. Queues والجدولة

Jobs:

- `ProcessWebhookJob`: معالجة WhatsApp webhook.
- `ProcessWebhookMediaJob`: تحميل/حفظ ميديا الرسائل الواردة.
- `SendCampaignMessageJob`: إرسال رسالة حملة واحدة.
- `ProcessCampaignMessagesJob`: إدارة الحملات المجدولة والعالقة والمكتملة.
- `ResumeAutomationFlowRunJob`: استكمال flow بعد delay.

الجدولة موجودة في `bootstrap/app.php`:

- `ProcessCampaignMessagesJob` كل دقيقة.
- `queue:restart` كل ساعة.
- `queue:prune-failed` يوميا.
- `queue:prune-batches` يوميا.
- `whatsapp:refresh-tokens` كل ساعة.
- `model:prune` لـ `CampaignLog` يوميا.

`app/Console/Kernel.php` فارغ عمدا لأن الجدولة مركزية في `bootstrap/app.php`.

## 24. قاعدة البيانات: أهم الجداول

المستخدمون والمؤسسات:

- `users`
- `organizations`
- `teams`
- `team_invites`
- `organization_roles`
- `organization_employees`
- `organization_employee_assignments`
- `organization_api_keys`

WhatsApp/CRM:

- `contacts`
- `contact_groups`
- `contact_contact_group`
- `contact_fields`
- `chats`
- `chat_logs`
- `chat_media`
- `chat_status_logs`
- `chat_notes`
- `chat_tickets`
- `chat_ticket_logs`
- `templates`
- `auto_replies`

الحملات:

- `campaigns`
- `campaign_logs`
- `campaign_log_retries`

الأتمتة:

- `automation_flows`
- `automation_flow_versions`
- `automation_flow_runs`
- `automation_flow_run_steps`
- `automation_flow_assets`
- `automation_flow_node_secrets`

الاشتراكات والفواتير:

- `subscription_plans`
- `subscriptions`
- `coupons`
- `billing_checkout_intents`
- `billing_invoices`
- `billing_payments`
- `billing_transactions`
- `billing_credits`
- `billing_debits`
- `billing_tax_rates`

النظام والمحتوى:

- `settings`
- `modules`
- `addons`
- `languages`
- `pages`
- `faqs`
- `reviews`
- `email_templates`
- `email_logs`
- `tickets`
- `ticket_comments`
- `ticket_categories`

## 25. الأمان والعزل

أهم آليات الحماية:

- كل بيانات العميل مربوطة بـ `organization_id`.
- Middleware يتحقق من المؤسسة الحالية والاشتراك.
- صلاحيات داخل المؤسسة عبر `PermissionService` و `organization_roles`.
- Admin permissions عبر `CheckAdminPermission`.
- API tokens مخزنة كـ hashes بعد hardening.
- WhatsApp tokens/app secrets مشفرة عبر `WhatsappTokenVault`.
- Webhooks مستثناة من CSRF لكن يتم التحقق منها بالتوقيع.
- تحميل ملفات flow/assets مرتبط بملكية المؤسسة.
- Tests كثيرة لفحص isolation/security/route contracts.

## 26. الإعدادات المهمة في `.env`

أمثلة مفاهيمية، وليست قائمة كاملة:

- `APP_URL`
- `APP_ENV`
- `APP_KEY`
- DB settings.
- Queue connection.
- Cache/session drivers.
- Mail provider.
- Pusher/broadcast settings.
- Storage local/S3.
- Moyasar credentials.
- OpenAI/AI settings، غالبا بعضها من settings أو organization metadata.

ملاحظة: المشروع يعتمد كثيرا على جدول `settings` و `organizations.metadata`، وليس فقط `.env`.

## 27. أوامر التشغيل والفحص

من `composer.json`:

- `composer setup`: تثبيت dependencies، إنشاء env، migrate، npm install، build.
- `composer dev`: تشغيل Laravel server + queue listener + logs + Vite.
- `composer test`: clear config ثم `php artisan test`.
- `composer check:architecture`: اختبارات معمارية مهمة.
- `composer lint:php`: lint foundation.
- `composer analyse:php`: PHPStan.
- `npm run dev`: Vite dev server.
- `npm run build`: build frontend.
- `npm run lint`: lint لأجزاء Automation.
- `npm run test:flow-builder:e2e`: Playwright test للـ Flow Builder.

## 28. أهم ملفات تقرأها عند التعديل

لو ستعدل WhatsApp:

- `app/Services/WhatsappService.php`
- `app/Http/Controllers/WebhookController.php`
- `app/Jobs/ProcessWebhookJob.php`
- `app/Services/Whatsapp/*`
- `app/Http/Controllers/User/SettingController.php`

لو ستعدل الشات:

- `app/Services/ChatService.php`
- `app/Http/Controllers/User/ChatController.php`
- `resources/js/Pages/User/Chat/Index.vue`
- `resources/js/Components/ChatComponents/*`

لو ستعدل الحملات:

- `app/Http/Controllers/User/CampaignController.php`
- `app/Jobs/SendCampaignMessageJob.php`
- `app/Jobs/ProcessCampaignMessagesJob.php`
- `app/Models/Campaign.php`
- `app/Models/CampaignLog.php`

لو ستعدل Flow Builder:

- `app/Http/Controllers/User/AutomationFlowController.php`
- `app/Services/AutomationFlows/*`
- `resources/js/Pages/User/Automation/Flows/*`
- `resources/js/Components/AutomationFlows/*`
- `config/automation_flows.php`

لو ستعدل الاشتراكات:

- `app/Services/SubscriptionService.php`
- `app/Services/SubscriptionPlanLimitService.php`
- `app/Http/Controllers/User/SubscriptionController.php`
- `app/Http/Controllers/User/BillingController.php`
- `app/Services/BillingCheckoutIntentService.php`

## 29. ملخص الفلو الكامل من أول عميل حتى رسالة

1. العميل يسجل حساب.
2. يحقق البريد.
3. ينشئ أو يختار مؤسسة.
4. يختار/يدفع خطة اشتراك.
5. يربط WhatsApp Business.
6. يضيف contacts أو يستقبلهم تلقائيا من inbound WhatsApp.
7. يبدأ المحادثات من Inbox أو يرسل campaigns.
8. WhatsApp webhook يرجع statuses/messages.
9. النظام يحدث chats/logs/statuses.
10. Realtime event يحدث الواجهة.
11. Automation/Basic/AI قد ترد تلقائيا.
12. Billing/usage limits تراقب الاستخدام وتمنع التجاوز.

## 30. ملاحظات على حالة المشروع

- يوجد تعديل محلي حالي في `config/database.php` لم أغيره.
- يوجد ملف غير متتبع `DEPLOY_FREE.md` كان موجودا قبل هذا التوثيق.
- هذا الملف نفسه توثيق مضاف جديد.
- المشروع كبير ومتشعب، لكن مركز الثقل واضح: `Organization` + `WhatsApp` + `Chat` + `Campaign` + `Automation` + `Billing`.
