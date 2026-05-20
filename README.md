# Botzo

هذه هي الوثيقة الوحيدة المعتمدة للمشروع. أي توثيق قديم داخل `docs/` أو ملفات ملاحظات منفصلة تم إزالته حتى تبقى حالة المشروع واضحة من مكان واحد.

## ملخص المشروع

Botzo منصة SaaS خفيفة لإدارة محادثات WhatsApp للمؤسسات. النسخة الحالية مخصصة للتشغيل الإنتاجي على الدومين الأساسي `https://botzo.net`، وتدعم إدارة المؤسسات، الفرق، جهات الاتصال، المحادثات، الحملات، الفوترة، وAutomation/Flow Builder ضمن نطاق تشغيلي مضبوط.

## التقنية

- Backend: Laravel 12 وMySQL.
- Frontend: Inertia وVue 3 وVite.
- Realtime/queues: Laravel Queue jobs وPusher عند الحاجة.
- Integrations: WhatsApp Cloud API وMoyasar وOpenAI/AI Assistant.
- Runtime: VPS فقط، والمسار الإنتاجي الحالي هو `/home/botzo/apps/sa/current`.

## الدومين والتشغيل

- الدومين الأساسي: `https://botzo.net`.
- لا يتم تحويل `botzo.net` إلى دومين آخر.
- تحويل HTTP المسموح: من `http://botzo.net` إلى `https://botzo.net` فقط.
- رابط GitHub: `https://github.com/a7medel3dawy/botzo-sa`.
- الفرع المعتمد: `main`.

## الإعدادات المحلية للنسخة

- الدولة: Saudi Arabia.
- المنطقة الزمنية: `Asia/Riyadh`.
- رمز الدولة: `SA/+966`.
- العملة: `SAR`.
- بوابة الدفع: Moyasar فقط.
- الواجهات العامة المسموحة: `classic` و`premium`.

## الموديولات النشطة

- التسجيل، تسجيل الدخول، وإدارة الهوية.
- إدارة المؤسسات والفروع والفرق والأدوار والصلاحيات.
- إعداد WhatsApp Cloud API واستقبال Webhooks.
- جهات الاتصال والمحادثات والتذاكر الداخلية.
- الحملات والجدولة وإعادة المحاولة.
- Automation وFlow Builder بدون outbound webhooks.
- الاشتراكات والفواتير والمدفوعات عبر Moyasar.
- إعدادات الإدارة العامة ومميزات Embedded Signup وAI Assistant وFlow Builder.

## خارج النطاق

- تطبيق الموبايل وFirebase/FCM.
- Marketing CMS ومركز إدارة الموقع التسويقي القديم.
- قنوات Instagram/Messenger وMeta Messaging غير WhatsApp.
- Developer outbound webhooks.
- بوابات الدفع غير Moyasar.
- العملات غير SAR.
- إضافات Google Analytics وreCAPTCHA وAuthenticator وMaps وWooCommerce.

## Webhooks التشغيلية

- WhatsApp inbound: `/webhook/whatsapp/{identifier?}`.
- WABA webhook: `/webhook/waba`.
- Moyasar payment webhook: `/payment/moyasar/webhook`.

## عقود السلامة

- قبل أي migration أو تنظيف بيانات على السيرفر يجب أخذ نسخة احتياطية كاملة من قاعدة البيانات وملف `.env`.
- لا يتم حذف أو تعديل بيانات إنتاجية واسعة بدون طلب صريح.
- لا يتم إدخال موديولات خارج النطاق إلا بطلب صريح.
- لا يتم تعديل `vendor` أو `node_modules` أو `public/build` أو `public/prebuilt-build` إلا للفحص أو البناء.
- `.env` لا يدخل Git أبدًا.

## أوامر التشغيل المحلية

```bash
composer install
npm install
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
npm run build
php artisan optimize:clear
php artisan serve
```

## أوامر الفحص المعتمدة

```bash
composer dump-autoload
php artisan test --env=testing
npm run build
php scripts/refresh-prebuilt-build.php --format=json
php scripts/check-build-budgets.php --format=json --strict
php scripts/check-prebuilt-parity.php --format=json --strict
php artisan route:list --json --no-ansi
php artisan system:health-check --strict --no-ansi
php artisan system:docs-consistency-check --strict --no-ansi
```

## Snapshot تشغيلي

- route snapshot المحلي: `350` route، مع قبول `349` عند اختلاف بيئة التشغيل في مسار بيانات dummy المحلي.
- Flow Builder routes: `14` routes مستقلة تحت `/automation/flows`.
- Critical health endpoints: `/health/live` و`/health/ready`.


```
project-current
├─ .editorconfig
├─ .htaccess
├─ .release_commit
├─ app
│  ├─ Console
│  │  ├─ Commands
│  │  │  ├─ BootstrapCoreDataCommand.php
│  │  │  ├─ GenerateCompleteDummyData.php
│  │  │  ├─ ProvisionRealEstateFlowTemplatesCommand.php
│  │  │  ├─ RepairChatTicketsCommand.php
│  │  │  ├─ RunModuleMigrations.php
│  │  │  ├─ RunModuleSeeders.php
│  │  │  ├─ SyncTemplateData.php
│  │  │  ├─ SyncTranslations.php
│  │  │  ├─ SystemBillingIntegrityAuditCommand.php
│  │  │  ├─ SystemDocsConsistencyCheckCommand.php
│  │  │  ├─ SystemHealthCheckCommand.php
│  │  │  ├─ SystemMetaReviewTestCommand.php
│  │  │  ├─ SystemPrepareTestingDatabaseCommand.php
│  │  │  ├─ SystemQueueProfileCommand.php
│  │  │  ├─ SystemReadinessScoreCommand.php
│  │  │  ├─ SystemRiskReportCommand.php
│  │  │  ├─ SystemSignupBillingAuditCommand.php
│  │  │  ├─ SystemTestSafetyCheckCommand.php
│  │  │  ├─ UpdateLatestChatCreatedAt.php
│  │  │  └─ WhatsappRefreshTokensCommand.php
│  │  └─ Kernel.php
│  ├─ Contracts
│  │  ├─ FeatureGateContract.php
│  │  ├─ MessagingGatewayContract.php
│  │  ├─ PaymentGatewayContract.php
│  │  ├─ QueueProfileContract.php
│  │  └─ WebhookVerificationContract.php
│  ├─ Events
│  │  ├─ NewChatEvent.php
│  │  └─ NewPaymentEvent.php
│  ├─ Exceptions
│  │  └─ Handler.php
│  ├─ Exports
│  │  ├─ CampaignDetailsExport.php
│  │  ├─ ContactGroupsExport.php
│  │  ├─ ContactsExport.php
│  │  └─ LanguageJsonExport.php
│  ├─ Helpers
│  │  ├─ CurrencyHelper.php
│  │  ├─ CustomHelper.php
│  │  ├─ DateTimeHelper.php
│  │  ├─ Email.php
│  │  └─ SubscriptionHelper.php
│  ├─ Http
│  │  ├─ Controllers
│  │  │  ├─ Admin
│  │  │  │  ├─ AddonController.php
│  │  │  │  ├─ BillingController.php
│  │  │  │  ├─ CouponController.php
│  │  │  │  ├─ DashboardController.php
│  │  │  │  ├─ EmailLogController.php
│  │  │  │  ├─ EmailTemplateController.php
│  │  │  │  ├─ FaqController.php
│  │  │  │  ├─ LanguageController.php
│  │  │  │  ├─ OrganizationController.php
│  │  │  │  ├─ PagesController.php
│  │  │  │  ├─ PaymentController.php
│  │  │  │  ├─ PaymentGatewayController.php
│  │  │  │  ├─ RoleController.php
│  │  │  │  ├─ SettingController.php
│  │  │  │  ├─ SubscriptionPlanController.php
│  │  │  │  ├─ TaxController.php
│  │  │  │  ├─ TeamController.php
│  │  │  │  ├─ TestimonialController.php
│  │  │  │  ├─ TicketController.php
│  │  │  │  ├─ TranslationController.php
│  │  │  │  ├─ UserController.php
│  │  │  │  ├─ UserLogController.php
│  │  │  │  └─ UtilityController.php
│  │  │  ├─ ApiController.php
│  │  │  ├─ AuthController.php
│  │  │  ├─ Controller.php
│  │  │  ├─ FileController.php
│  │  │  ├─ FrontendController.php
│  │  │  ├─ FrontendSeoController.php
│  │  │  ├─ PaymentController.php
│  │  │  ├─ ProfileController.php
│  │  │  ├─ User
│  │  │  │  ├─ AutomationFlowController.php
│  │  │  │  ├─ BillingController.php
│  │  │  │  ├─ CampaignController.php
│  │  │  │  ├─ CannedReplyController.php
│  │  │  │  ├─ ChatController.php
│  │  │  │  ├─ ChatNoteController.php
│  │  │  │  ├─ ChatTicketController.php
│  │  │  │  ├─ CompanyTeamController.php
│  │  │  │  ├─ ContactController.php
│  │  │  │  ├─ ContactFieldController.php
│  │  │  │  ├─ ContactGroupController.php
│  │  │  │  ├─ DashboardController.php
│  │  │  │  ├─ DeveloperController.php
│  │  │  │  ├─ InstanceController.php
│  │  │  │  ├─ MessageController.php
│  │  │  │  ├─ OrganizationController.php
│  │  │  │  ├─ ProfileController.php
│  │  │  │  ├─ RoleController.php
│  │  │  │  ├─ SettingController.php
│  │  │  │  ├─ SubscriptionController.php
│  │  │  │  ├─ TeamController.php
│  │  │  │  ├─ TemplateController.php
│  │  │  │  └─ TicketController.php
│  │  │  └─ WebhookController.php
│  │  ├─ Kernel.php
│  │  ├─ Middleware
│  │  │  ├─ Authenticate.php
│  │  │  ├─ AuthenticateBearerToken.php
│  │  │  ├─ AuthorizeCampaignDispatch.php
│  │  │  ├─ CheckAdminPermission.php
│  │  │  ├─ CheckAppStatus.php
│  │  │  ├─ CheckClientRole.php
│  │  │  ├─ CheckEmailVerification.php
│  │  │  ├─ CheckOrganizationId.php
│  │  │  ├─ CheckSubscriptionStatus.php
│  │  │  ├─ EncryptCookies.php
│  │  │  ├─ HandleInertiaRequests.php
│  │  │  ├─ Localization.php
│  │  │  ├─ PreventRequestsDuringMaintenance.php
│  │  │  ├─ RedirectIfAuthenticated.php
│  │  │  ├─ SetOrganizationFromSession.php
│  │  │  ├─ TrimStrings.php
│  │  │  ├─ TrustHosts.php
│  │  │  ├─ TrustProxies.php
│  │  │  ├─ ValidateSignature.php
│  │  │  └─ VerifyCsrfToken.php
│  │  ├─ Requests
│  │  │  ├─ AutomationFlows
│  │  │  │  ├─ Concerns
│  │  │  │  │  └─ ValidatesAutomationFlowGraphPayload.php
│  │  │  │  ├─ PreviewAutomationFlowRequest.php
│  │  │  │  ├─ SaveAutomationFlowRequest.php
│  │  │  │  ├─ StoreAutomationFlowRequest.php
│  │  │  │  ├─ UploadAutomationFlowAssetRequest.php
│  │  │  │  └─ ValidateAutomationFlowRequest.php
│  │  │  ├─ CouponRequest.php
│  │  │  ├─ ExchangeEmbeddedSignupCodeRequest.php
│  │  │  ├─ LoginRequest.php
│  │  │  ├─ PasswordResetRequest.php
│  │  │  ├─ PasswordValidateResetRequest.php
│  │  │  ├─ PaymentRequest.php
│  │  │  ├─ SignupRequest.php
│  │  │  ├─ StoreAutoReply.php
│  │  │  ├─ StoreBillingTransaction.php
│  │  │  ├─ StoreCampaign.php
│  │  │  ├─ StoreChatNote.php
│  │  │  ├─ StoreCompanyEmployee.php
│  │  │  ├─ StoreConfig.php
│  │  │  ├─ StoreContact.php
│  │  │  ├─ StoreContactField.php
│  │  │  ├─ StoreContactGroup.php
│  │  │  ├─ StoreCoupon.php
│  │  │  ├─ StoreEmailTemplate.php
│  │  │  ├─ StoreFaq.php
│  │  │  ├─ StoreLanguage.php
│  │  │  ├─ StoreOrganization.php
│  │  │  ├─ StorePage.php
│  │  │  ├─ StorePaymentGateway.php
│  │  │  ├─ StoreProfile.php
│  │  │  ├─ StoreProfileAddress.php
│  │  │  ├─ StoreProfilePassword.php
│  │  │  ├─ StoreRole.php
│  │  │  ├─ StoreRoleUuid.php
│  │  │  ├─ StoreSubscriptionPlan.php
│  │  │  ├─ StoreSubscriptionPurchaseRequest.php
│  │  │  ├─ StoreTax.php
│  │  │  ├─ StoreTeam.php
│  │  │  ├─ StoreTestimonial.php
│  │  │  ├─ StoreTicket.php
│  │  │  ├─ StoreTicketComment.php
│  │  │  ├─ StoreTicketPriority.php
│  │  │  ├─ StoreTicketStatus.php
│  │  │  ├─ StoreUser.php
│  │  │  ├─ StoreUserAdmin.php
│  │  │  ├─ StoreUserInvite.php
│  │  │  ├─ StoreUserOrganization.php
│  │  │  ├─ StoreWhatsappProfile.php
│  │  │  ├─ StoreWhatsappSettings.php
│  │  │  ├─ UpdateCompanyEmployee.php
│  │  │  ├─ ValidateResetToken.php
│  │  │  └─ ValidateUserEmail.php
│  │  ├─ Resources
│  │  │  ├─ AddonResource.php
│  │  │  ├─ AutoReplyResource.php
│  │  │  ├─ BillingInvoiceResource.php
│  │  │  ├─ BillingResource.php
│  │  │  ├─ BillingSummaryResource.php
│  │  │  ├─ BlogResource.php
│  │  │  ├─ CampaignLogResource.php
│  │  │  ├─ CampaignResource.php
│  │  │  ├─ ChatLogResource.php
│  │  │  ├─ ContactFieldResource.php
│  │  │  ├─ ContactGroupResource.php
│  │  │  ├─ ContactResource.php
│  │  │  ├─ CouponResource.php
│  │  │  ├─ DeveloperResource.php
│  │  │  ├─ EmailLogsResource.php
│  │  │  ├─ FaqResource.php
│  │  │  ├─ LangResource.php
│  │  │  ├─ OrganizationsResource.php
│  │  │  ├─ PageResource.php
│  │  │  ├─ PaymentGatewayResource.php
│  │  │  ├─ ReviewResource.php
│  │  │  ├─ RoleResource.php
│  │  │  ├─ SubscriptionPlanResource.php
│  │  │  ├─ SubscriptionResource.php
│  │  │  ├─ TaxRateResource.php
│  │  │  ├─ TeamResource.php
│  │  │  ├─ TemplateResource.php
│  │  │  ├─ TicketResource.php
│  │  │  └─ UserResource.php
│  │  └─ Traits
│  │     └─ HasUuid.php
│  ├─ Imports
│  │  ├─ ContactGroupsImport.php
│  │  └─ ContactsImport.php
│  ├─ Jobs
│  │  ├─ ProcessCampaignMessagesJob.php
│  │  ├─ ProcessWebhookJob.php
│  │  ├─ ProcessWebhookMediaJob.php
│  │  ├─ ResumeAutomationFlowRunJob.php
│  │  └─ SendCampaignMessageJob.php
│  ├─ Mail
│  │  ├─ CustomEmail.php
│  │  └─ CustomEmailVerification.php
│  ├─ Models
│  │  ├─ Addon.php
│  │  ├─ AutomationFlow.php
│  │  ├─ AutomationFlowAsset.php
│  │  ├─ AutomationFlowNodeSecret.php
│  │  ├─ AutomationFlowRun.php
│  │  ├─ AutomationFlowRunStep.php
│  │  ├─ AutomationFlowVersion.php
│  │  ├─ AutoReply.php
│  │  ├─ BillingCheckoutIntent.php
│  │  ├─ BillingCredit.php
│  │  ├─ BillingDebit.php
│  │  ├─ BillingInvoice.php
│  │  ├─ BillingPayment.php
│  │  ├─ BillingTaxRate.php
│  │  ├─ BillingTransaction.php
│  │  ├─ Campaign.php
│  │  ├─ CampaignLog.php
│  │  ├─ CampaignLogRetry.php
│  │  ├─ Chat.php
│  │  ├─ ChatLog.php
│  │  ├─ ChatMedia.php
│  │  ├─ ChatNote.php
│  │  ├─ ChatStatusLog.php
│  │  ├─ ChatTicket.php
│  │  ├─ ChatTicketLog.php
│  │  ├─ Contact.php
│  │  ├─ ContactContactGroup.php
│  │  ├─ ContactField.php
│  │  ├─ ContactGroup.php
│  │  ├─ Coupon.php
│  │  ├─ EmailLog.php
│  │  ├─ EmailTemplate.php
│  │  ├─ EmbeddedSignupAudit.php
│  │  ├─ Faq.php
│  │  ├─ Language.php
│  │  ├─ Module.php
│  │  ├─ Organization.php
│  │  ├─ OrganizationAiUsageCounter.php
│  │  ├─ OrganizationApiKey.php
│  │  ├─ OrganizationEmployee.php
│  │  ├─ OrganizationEmployeeAssignment.php
│  │  ├─ OrganizationRole.php
│  │  ├─ Page.php
│  │  ├─ PasswordResetToken.php
│  │  ├─ PaymentGateway.php
│  │  ├─ Review.php
│  │  ├─ Role.php
│  │  ├─ RolePermission.php
│  │  ├─ SeederHistory.php
│  │  ├─ Setting.php
│  │  ├─ Subscription.php
│  │  ├─ SubscriptionPlan.php
│  │  ├─ TaxRate.php
│  │  ├─ Team.php
│  │  ├─ TeamInvite.php
│  │  ├─ Template.php
│  │  ├─ Ticket.php
│  │  ├─ TicketCategory.php
│  │  ├─ TicketComment.php
│  │  ├─ User.php
│  │  └─ UserAdmin.php
│  ├─ Modules
│  │  ├─ Platform
│  │  │  ├─ Application
│  │  │  │  ├─ Context
│  │  │  │  │  └─ OrganizationContextResolver.php
│  │  │  │  ├─ Environment
│  │  │  │  │  ├─ DatabaseConfigMode.php
│  │  │  │  │  └─ DemoModeService.php
│  │  │  │  └─ Support
│  │  │  │     └─ UseCaseResult.php
│  │  │  ├─ Domain
│  │  │  │  └─ Exceptions
│  │  │  │     └─ DomainException.php
│  │  │  └─ Http
│  │  │     └─ Support
│  │  │        └─ DomainExceptionResponder.php
│  │  └─ WhatsApp
│  │     └─ Infrastructure
│  │        ├─ CloudApi
│  │        │  └─ WhatsappAccountInspectionService.php
│  │        └─ Contracts
│  │           └─ WhatsappTransportContract.php
│  ├─ Providers
│  │  ├─ AppServiceProvider.php
│  │  ├─ AuthServiceProvider.php
│  │  ├─ BroadcastConfigServiceProvider.php
│  │  ├─ BroadcastServiceProvider.php
│  │  ├─ EventServiceProvider.php
│  │  ├─ MailConfigServiceProvider.php
│  │  ├─ RouteServiceProvider.php
│  │  └─ SubscriptionServiceProvider.php
│  ├─ Resolvers
│  │  └─ PaymentPlatformResolver.php
│  ├─ Rules
│  │  ├─ AllowedPhoneCountryValidation.php
│  │  ├─ CampaignLimit.php
│  │  ├─ CannedReplyLimit.php
│  │  ├─ ConfirmOldPassword.php
│  │  ├─ ContactLimit.php
│  │  ├─ NotRoleUser.php
│  │  ├─ NotUniqueEmail.php
│  │  ├─ RoleExists.php
│  │  ├─ TeamLimit.php
│  │  ├─ UniqueEmail.php
│  │  ├─ UniquePhone.php
│  │  └─ UniqueRole.php
│  ├─ Services
│  │  ├─ AddonStateService.php
│  │  ├─ AdminUserDirectoryService.php
│  │  ├─ AuthService.php
│  │  ├─ AutomationFlows
│  │  │  ├─ AutomationFlowAccessService.php
│  │  │  ├─ AutomationFlowActionDispatchService.php
│  │  │  ├─ AutomationFlowAssetService.php
│  │  │  ├─ AutomationFlowBuilderPolicyService.php
│  │  │  ├─ AutomationFlowBuilderService.php
│  │  │  ├─ AutomationFlowContactMutationService.php
│  │  │  ├─ AutomationFlowConversationHandoffService.php
│  │  │  ├─ AutomationFlowGraphCompiler.php
│  │  │  ├─ AutomationFlowGraphValidator.php
│  │  │  ├─ AutomationFlowNodeCatalog.php
│  │  │  ├─ AutomationFlowNodeSecretService.php
│  │  │  ├─ AutomationFlowPersonalizationService.php
│  │  │  ├─ AutomationFlowPreviewService.php
│  │  │  ├─ AutomationFlowRealEstateTemplateProvisioner.php
│  │  │  ├─ AutomationFlowRunQuotaService.php
│  │  │  ├─ AutomationFlowRuntimeService.php
│  │  │  ├─ AutomationFlowRuntimeSupportService.php
│  │  │  ├─ AutomationFlowSessionVariableService.php
│  │  │  ├─ AutomationFlowStarterTemplateService.php
│  │  │  └─ AutomationFlowWhatsappComplianceService.php
│  │  ├─ AutomationResponseSequenceService.php
│  │  ├─ AutoReplyService.php
│  │  ├─ BillingCheckoutIntentService.php
│  │  ├─ BillingInvoiceService.php
│  │  ├─ BillingService.php
│  │  ├─ CampaignService.php
│  │  ├─ Chat
│  │  │  └─ ChatAccessService.php
│  │  ├─ ChatNoteService.php
│  │  ├─ ChatService.php
│  │  ├─ ChatTicketProvisioningService.php
│  │  ├─ CompanyWorkforceService.php
│  │  ├─ Concerns
│  │  │  └─ InteractsWithWhatsappServiceSupport.php
│  │  ├─ ContactFieldService.php
│  │  ├─ ContactService.php
│  │  ├─ CouponService.php
│  │  ├─ EmailService.php
│  │  ├─ EmbeddedSignup
│  │  │  ├─ EmbeddedSignupAuditService.php
│  │  │  ├─ EmbeddedSignupGate.php
│  │  │  ├─ EmbeddedSignupReviewTestService.php
│  │  │  └─ EmbeddedSignupService.php
│  │  ├─ FaqService.php
│  │  ├─ FeatureSetupPolicyService.php
│  │  ├─ IntelliReply
│  │  │  ├─ AiKeyResolver.php
│  │  │  └─ AiUsageLimiterService.php
│  │  ├─ LangService.php
│  │  ├─ MediaService.php
│  │  ├─ MoyasarService.php
│  │  ├─ OrganizationApiService.php
│  │  ├─ OrganizationApiTokenHasher.php
│  │  ├─ OrganizationDefaultRoleService.php
│  │  ├─ OrganizationHierarchyService.php
│  │  ├─ OrganizationRoleService.php
│  │  ├─ OrganizationService.php
│  │  ├─ OrganizationSessionService.php
│  │  ├─ OrganizationUsageSummaryService.php
│  │  ├─ OrganizationUserSeatUsageService.php
│  │  ├─ OutboundMessageLimitGuardService.php
│  │  ├─ PageService.php
│  │  ├─ PasswordResetService.php
│  │  ├─ PaymentProcessorAvailabilityService.php
│  │  ├─ PermissionService.php
│  │  ├─ PhoneService.php
│  │  ├─ QueueProfileService.php
│  │  ├─ RoleService.php
│  │  ├─ SettingService.php
│  │  ├─ SettingValueService.php
│  │  ├─ SocialIdentityResolverService.php
│  │  ├─ SocialLoginService.php
│  │  ├─ SubscriptionFeatureUsageService.php
│  │  ├─ SubscriptionPlanLimitService.php
│  │  ├─ SubscriptionPlanService.php
│  │  ├─ SubscriptionService.php
│  │  ├─ System
│  │  │  ├─ BillingIntegrityAuditService.php
│  │  │  ├─ DocsConsistencyService.php
│  │  │  ├─ OnboardingBillingAuditService.php
│  │  │  ├─ ReadinessAssessmentService.php
│  │  │  ├─ RiskReportService.php
│  │  │  ├─ RuntimeReadinessService.php
│  │  │  └─ TestingDatabasePreparationService.php
│  │  ├─ TaxService.php
│  │  ├─ TeamService.php
│  │  ├─ TemplateService.php
│  │  ├─ TestimonialService.php
│  │  ├─ TicketService.php
│  │  ├─ TrialAddonEntitlementService.php
│  │  ├─ UserService.php
│  │  ├─ Webhooks
│  │  │  └─ WhatsappWebhookVerificationService.php
│  │  ├─ Whatsapp
│  │  │  ├─ WhatsappAccessTokenRefreshService.php
│  │  │  ├─ WhatsappTemplateReadinessService.php
│  │  │  ├─ WhatsappTemplateRequestGuardService.php
│  │  │  └─ WhatsappTokenVault.php
│  │  ├─ WhatsappRateLimiter.php
│  │  └─ WhatsappService.php
│  ├─ Support
│  │  ├─ BillingPaymentMethodResolver.php
│  │  ├─ DeveloperApiResponse.php
│  │  ├─ EmailTemplateCatalog.php
│  │  ├─ EmailTemplateRenderer.php
│  │  ├─ OrganizationPermissions.php
│  │  ├─ OrganizationProfileContext.php
│  │  ├─ OrganizationRolePresetCatalog.php
│  │  ├─ OrganizationSettingsViewData.php
│  │  ├─ SaClientPlanProfile.php
│  │  └─ SupportedPaymentProcessors.php
│  └─ Traits
│     └─ TemplateTrait.php
├─ artisan
├─ bootstrap
│  ├─ app.php
│  └─ cache
│     ├─ packages.php
│     └─ services.php
├─ cmd
│  └─ main.go
├─ composer.json
├─ composer.lock
├─ config
│  ├─ apiguide.php
│  ├─ app.php
│  ├─ architecture.php
│  ├─ auth.php
│  ├─ automation_flows.php
│  ├─ broadcasting.php
│  ├─ cache.php
│  ├─ cors.php
│  ├─ currencies.php
│  ├─ database.php
│  ├─ excel.php
│  ├─ ffmpeg.php
│  ├─ filesystems.php
│  ├─ formats.php
│  ├─ frontend.php
│  ├─ graph.php
│  ├─ hashing.php
│  ├─ i18n.php
│  ├─ intellireply.php
│  ├─ languages.php
│  ├─ logging.php
│  ├─ mail.php
│  ├─ models.php
│  ├─ platform.php
│  ├─ purifier.php
│  ├─ queue.php
│  ├─ queue_profile.php
│  ├─ services.php
│  ├─ session.php
│  ├─ sounds.php
│  ├─ utility.php
│  ├─ view.php
│  └─ voices.php
├─ configs
│  └─ config.yaml
├─ database
│  ├─ factories
│  │  └─ UserFactory.php
│  ├─ migrations
│  │  ├─ 2024_03_20_050200_create_auto_replies_table.php
│  │  ├─ 2024_03_20_050311_create_billing_credits_table.php
│  │  ├─ 2024_03_20_050348_create_billing_debits_table.php
│  │  ├─ 2024_03_20_050430_create_billing_invoices_table.php
│  │  ├─ 2024_03_20_050508_create_billing_items_table.php
│  │  ├─ 2024_03_20_050600_create_billing_payments_table.php
│  │  ├─ 2024_03_20_050635_create_billing_tax_rates_table.php
│  │  ├─ 2024_03_20_050711_create_billing_transactions_table.php
│  │  ├─ 2024_03_20_050751_create_blog_authors_table.php
│  │  ├─ 2024_03_20_050826_create_blog_categories_table.php
│  │  ├─ 2024_03_20_050912_create_blog_posts_table.php
│  │  ├─ 2024_03_20_050959_create_blog_tags_table.php
│  │  ├─ 2024_03_20_051036_create_campaigns_table.php
│  │  ├─ 2024_03_20_051111_create_campaign_logs_table.php
│  │  ├─ 2024_03_20_051154_create_chats_table.php
│  │  ├─ 2024_03_20_051253_create_chat_logs_table.php
│  │  ├─ 2024_03_20_051336_create_chat_media_table.php
│  │  ├─ 2024_03_20_051414_create_contacts_table.php
│  │  ├─ 2024_03_20_051449_create_contact_groups_table.php
│  │  ├─ 2024_03_20_051537_create_coupons_table.php
│  │  ├─ 2024_03_20_051613_create_email_logs_table.php
│  │  ├─ 2024_03_20_051655_create_email_templates_table.php
│  │  ├─ 2024_03_20_051739_create_failed_jobs_table.php
│  │  ├─ 2024_03_20_051807_create_faqs_table.php
│  │  ├─ 2024_03_20_051847_create_jobs_table.php
│  │  ├─ 2024_03_20_051919_create_modules_table.php
│  │  ├─ 2024_03_20_052034_create_organizations_table.php
│  │  ├─ 2024_03_20_052107_create_pages_table.php
│  │  ├─ 2024_03_20_052141_create_password_reset_tokens_table.php
│  │  ├─ 2024_03_20_052223_create_payment_gateways_table.php
│  │  ├─ 2024_03_20_052338_create_reviews_table.php
│  │  ├─ 2024_03_20_052401_create_users_table.php
│  │  ├─ 2024_03_20_052430_create_roles_table.php
│  │  ├─ 2024_03_20_052513_create_role_permissions_table.php
│  │  ├─ 2024_03_20_052620_create_settings_table.php
│  │  ├─ 2024_03_20_052654_create_subscriptions_table.php
│  │  ├─ 2024_03_20_052731_create_subscription_plans_table.php
│  │  ├─ 2024_03_20_052808_create_tax_rates_table.php
│  │  ├─ 2024_03_20_052839_create_teams_table.php
│  │  ├─ 2024_03_20_052914_create_team_invites_table.php
│  │  ├─ 2024_03_20_052920_create_ticket_categories_table.php
│  │  ├─ 2024_03_20_052956_create_templates_table.php
│  │  ├─ 2024_03_20_053038_create_tickets_table.php
│  │  ├─ 2024_03_20_053205_create_ticket_comments_table.php
│  │  ├─ 2024_04_08_133150_create_organization_api_keys_table.php
│  │  ├─ 2024_04_24_211852_create_languages.php
│  │  ├─ 2024_04_27_155643_create_contact_fields_table.php
│  │  ├─ 2024_04_27_160152_add_metadata_to_contacts_table.php
│  │  ├─ 2024_05_11_052902_create_chat_notes_table.php
│  │  ├─ 2024_05_11_052925_create_chat_tickets_table.php
│  │  ├─ 2024_05_11_052940_create_chat_ticket_logs_table.php
│  │  ├─ 2024_05_11_053846_rename_chat_logs_table.php
│  │  ├─ 2024_05_11_054010_create_chat_logs_2_table.php
│  │  ├─ 2024_05_11_063255_add_user_id_to_chats_table.php
│  │  ├─ 2024_05_11_063540_add_role_to_team_invites_table.php
│  │  ├─ 2024_05_11_063819_update_agent_role_to_teams_table.php
│  │  ├─ 2024_05_11_064650_add_deleted_by_to_organization_api_keys_table.php
│  │  ├─ 2024_05_11_065031_add_organization_id_to_tickets_table.php
│  │  ├─ 2024_05_28_080331_make_password_nullable_in_users_table.php
│  │  ├─ 2024_05_30_125859_modify_campaigns_table.php
│  │  ├─ 2024_06_03_124254_create_addons_table.php
│  │  ├─ 2024_06_07_040536_update_users_table_for_facebook_login.php
│  │  ├─ 2024_06_07_040843_update_chat_media_table.php
│  │  ├─ 2024_06_07_074903_add_soft_delete_to_teams_and_organizations.php
│  │  ├─ 2024_06_09_155053_modify_billing_payments_table.php
│  │  ├─ 2024_06_12_070820_modify_faqs_table.php
│  │  ├─ 2024_07_04_053236_modify_amount_columns_in_billing_tables.php
│  │  ├─ 2024_07_04_054143_modify_contacts_table_encoding.php
│  │  ├─ 2024_07_09_011419_drop_seo_from_pages_table.php
│  │  ├─ 2024_07_17_062442_allow_null_content_in_pages_table.php
│  │  ├─ 2024_07_24_080535_add_latest_chat_created_at_to_contacts_table.php
│  │  ├─ 2024_08_01_050752_add_ongoing_to_status_enum_in_campaign_logs_table.php
│  │  ├─ 2024_08_08_130306_add_is_read_to_chats_table.php
│  │  ├─ 2024_08_10_071237_create_documents_table.php
│  │  ├─ 2024_10_16_201832_change_metadata_column_in_organizations_table.php
│  │  ├─ 2024_11_25_114450_add_version_and_update_needed_to_addons_table.php
│  │  ├─ 2024_11_29_070806_create_seeder_histories_table.php
│  │  ├─ 2024_12_20_081118_add_is_plan_restricted_to_addons_table.php
│  │  ├─ 2024_12_20_130829_add_is_active_table.php
│  │  ├─ 2025_01_24_090926_add_index_to_chats_table.php
│  │  ├─ 2025_01_24_091012_add_index_to_chat_tickets_table.php
│  │  ├─ 2025_01_24_091043_add_index_to_contacts_first_name.php
│  │  ├─ 2025_01_24_091115_add_fulltext_index_to_contacts_table.php
│  │  ├─ 2025_01_29_071445_modify_status_column_in_chats_table.php
│  │  ├─ 2025_02_21_084110_create_job_batches_table.php
│  │  ├─ 2025_02_21_093829_add_queue_indexes.php
│  │  ├─ 2025_04_02_085132_create_contact_contact_group_table.php
│  │  ├─ 2025_05_01_045837_create_campaign_log_retries_table.php
│  │  ├─ 2025_05_01_053318_add_retry_count_to_campaign_logs_table.php
│  │  ├─ 2025_05_23_101200_add_rtl_to_languages_table.php
│  │  ├─ 2025_07_30_152720_add_chat_performance_indexes.php
│  │  ├─ 2025_10_15_100841_add_language_column_to_users_table.php
│  │  ├─ 2025_10_15_110433_set_default_language_for_existing_users.php
│  │  ├─ 2025_11_04_101500_add_campaign_performance_indexes.php
│  │  ├─ 2025_12_10_165757_add_composite_index_for_campaign_logs_lock_queries.php
│  │  ├─ 2025_12_11_072433_add_retrying_status_to_campaign_logs_table.php
│  │  ├─ 2025_12_11_072919_add_scheduled_at_to_campaign_logs_table.php
│  │  ├─ 2025_12_13_141726_add_index_to_campaign_logs_scheduled_at.php
│  │  ├─ 2025_12_15_070351_add_indexes_to_contacts_table.php
│  │  ├─ 2026_01_15_162002_create_organization_roles_table.php
│  │  ├─ 2026_01_15_162011_migrate_existing_team_roles_to_organization_roles.php
│  │  ├─ 2026_01_15_162016_modify_teams_table_use_organization_roles.php
│  │  ├─ 2026_01_15_162017_add_organization_role_id_to_team_invites_table.php
│  │  ├─ 2026_01_26_115258_optimize_contacts_latest_chat_index.php
│  │  ├─ 2026_02_26_100000_create_embedded_signup_audits_table.php
│  │  ├─ 2026_02_26_100100_encrypt_whatsapp_tokens_in_organization_metadata.php
│  │  ├─ 2026_02_26_120000_backfill_embedded_signup_in_subscription_plans.php
│  │  ├─ 2026_02_27_000000_remove_embedded_signup_input_fields_from_addon_metadata.php
│  │  ├─ 2026_03_03_130000_backfill_ai_assistant_in_subscription_plans.php
│  │  ├─ 2026_03_03_130100_encrypt_ai_api_keys_in_organization_metadata.php
│  │  ├─ 2026_03_03_160200_create_organization_ai_usage_counters_table.php
│  │  ├─ 2026_03_03_160300_seed_ai_assistant_local_setup_defaults.php
│  │  ├─ 2026_03_03_160400_encrypt_global_ai_key_setting.php
│  │  ├─ 2026_03_05_000000_backfill_ai_assistant_input_fields_if_missing.php
│  │  ├─ 2026_03_06_000500_add_indexes_to_campaign_log_retries_table.php
│  │  ├─ 2026_03_06_020400_backfill_flow_builder_addon_install_policy_defaults.php
│  │  ├─ 2026_03_06_020500_backfill_flow_builder_entitlements_in_subscription_plans.php
│  │  ├─ 2026_03_12_090000_harden_organization_api_keys.php
│  │  ├─ 2026_03_12_090100_encrypt_whatsapp_app_secrets_in_organization_metadata.php
│  │  ├─ 2026_03_12_090200_normalize_organization_role_permissions.php
│  │  ├─ 2026_03_12_180000_drop_flow_builder_tables_and_cleanup_metadata.php
│  │  ├─ 2026_03_13_010000_create_automation_flow_tables.php
│  │  ├─ 2026_03_13_020000_reconnect_flow_builder_v2_addon.php
│  │  ├─ 2026_03_13_030000_create_automation_flow_assets_and_node_secrets_tables.php
│  │  ├─ 2026_03_18_120000_add_bilingual_fields_to_reviews_table.php
│  │  ├─ 2026_03_18_190000_add_bilingual_fields_to_faqs_table.php
│  │  ├─ 2026_03_18_220000_add_bilingual_fields_to_pages_table.php
│  │  ├─ 2026_03_21_210000_add_type_and_parent_to_organizations_table.php
│  │  ├─ 2026_03_23_080000_add_bilingual_names_to_subscription_plans_table.php
│  │  ├─ 2026_03_24_190000_add_moyasar_gateway_to_payment_gateways_table.php
│  │  ├─ 2026_03_25_120000_add_scheduled_plan_fields_to_subscriptions_table.php
│  │  ├─ 2026_03_25_121000_backfill_tier_rank_in_subscription_plans.php
│  │  ├─ 2026_03_25_190000_add_invoice_metadata_to_billing_tables.php
│  │  ├─ 2026_04_02_000000_add_admin_modules_and_backfill_permissions.php
│  │  ├─ 2026_04_02_100000_create_billing_checkout_intents_table.php
│  │  ├─ 2026_04_02_210000_add_google_id_to_users_table.php
│  │  ├─ 2026_04_08_120000_create_organization_employees_tables.php
│  │  ├─ 2026_04_08_150000_backfill_default_organization_roles.php
│  │  ├─ 2026_04_30_120000_add_system_owner_flag_to_users_table.php
│  │  ├─ 2026_04_30_130000_add_last_used_at_to_organization_api_keys_table.php
│  │  ├─ 2026_05_01_121500_backfill_chat_reply_permission.php
│  │  ├─ 2026_05_13_120000_prepare_sa_client_system_profile.php
│  │  ├─ 2026_05_13_150000_refine_sa_client_plan_profile.php
│  │  ├─ 2026_05_13_170000_refine_sa_client_extension_profile.php
│  │  ├─ 2026_05_13_180000_refine_sa_client_admin_settings_surface.php
│  │  ├─ 2026_05_13_190000_refine_sa_client_runtime_surface.php
│  │  ├─ 2026_05_13_203000_refine_sa_client_identity_surface.php
│  │  ├─ 2026_05_13_213000_refine_sa_client_feature_columns.php
│  │  ├─ 2026_05_14_010000_refine_sa_client_media_surface.php
│  │  └─ 2026_05_14_011000_refine_sa_client_runtime_trace_surface.php
│  └─ seeders
│     ├─ AddonsTableSeeder.php
│     ├─ AddonsTableSeeder3.php
│     ├─ AddonsTableSeeder4.php
│     ├─ AddonsTableSeeder6.php
│     ├─ ArabicLanguageSeeder.php
│     ├─ DatabaseSeeder.php
│     ├─ EmailTemplateSeeder.php
│     ├─ LanguageTableSeeder.php
│     ├─ ModulesTableSeeder.php
│     ├─ PageSeeder.php
│     ├─ PaymentGatewaysTableSeeder.php
│     ├─ RolesTableSeeder.php
│     ├─ SettingsTableSeeder.php
│     ├─ TicketCategoriesTableSeeder.php
│     └─ TransferContactGroupSeeder.php
├─ eslint.config.js
├─ go.mod
├─ index.php
├─ internal
│  ├─ model
│  ├─ repository
│  └─ service
├─ lang
│  ├─ ar
│  │  ├─ auth.php
│  │  ├─ pagination.php
│  │  ├─ passwords.php
│  │  └─ validation.php
│  ├─ ar.json
│  ├─ default_en.json
│  ├─ en
│  │  ├─ auth.php
│  │  ├─ pagination.php
│  │  ├─ passwords.php
│  │  └─ validation.php
│  ├─ en.json
│  ├─ sw.json
│  └─ tr.json
├─ modules
│  └─ IntelliReply
│     ├─ Controllers
│     │  ├─ ChatController.php
│     │  ├─ DocumentController.php
│     │  └─ MainController.php
│     ├─ Models
│     │  └─ Document.php
│     ├─ Pages
│     │  └─ User
│     │     └─ Index.vue
│     ├─ Providers
│     │  ├─ IntelliServiceProvider.php
│     │  └─ RouteServiceProvider.php
│     ├─ Requests
│     │  └─ StoreDocuments.php
│     ├─ Resources
│     │  └─ DocumentResource.php
│     ├─ routes.php
│     └─ Services
│        └─ AIResponseService.php
├─ package-lock.json
├─ package.json
├─ phpstan.neon.dist
├─ phpunit.xml
├─ pkg
│  └─ utils
├─ playwright.config.js
├─ postcss.config.js
├─ public
│  ├─ .htaccess
│  ├─ .well-known
│  │  └─ acme-challenge
│  ├─ bimi
│  │  └─ botzo-logo.svg
│  ├─ contact-groups.csv
│  ├─ contact-groups.xlsx
│  ├─ contacts.csv
│  ├─ contacts.xlsx
│  ├─ css
│  │  └─ error.css
│  ├─ demo-assets
│  │  └─ chatzo-showcase-offer.txt
│  ├─ favicon.ico
│  ├─ fonts
│  │  ├─ Outfit
│  │  │  ├─ Outfit-Black.ttf
│  │  │  ├─ Outfit-Bold.ttf
│  │  │  ├─ Outfit-ExtraBold.ttf
│  │  │  ├─ Outfit-ExtraLight.ttf
│  │  │  ├─ Outfit-Light.ttf
│  │  │  ├─ Outfit-Medium.ttf
│  │  │  ├─ Outfit-Regular.ttf
│  │  │  ├─ Outfit-SemiBold.ttf
│  │  │  └─ Outfit-Thin.ttf
│  │  └─ Tajawal
│  │     ├─ Tajawal-Bold.ttf
│  │     └─ Tajawal-Regular.ttf
│  ├─ images
│  │  ├─ ai.png
│  │  ├─ defaults
│  │  │  ├─ payment-methods-default.svg
│  │  │  └─ review-avatar.svg
│  │  ├─ document-placeholder.png
│  │  ├─ favicon.png
│  │  ├─ flow_icon.png
│  │  ├─ hero
│  │  │  ├─ dashboard.png
│  │  │  ├─ dashboard2.png
│  │  │  ├─ dashboard3.png
│  │  │  ├─ half-dash.png
│  │  │  ├─ hero-background-default.svg
│  │  │  ├─ user-2.png
│  │  │  ├─ user-3.png
│  │  │  ├─ user-4.png
│  │  │  └─ user-6.png
│  │  ├─ icons
│  │  │  ├─ link.png
│  │  │  └─ reply.png
│  │  ├─ image-placeholder.png
│  │  ├─ logo.png
│  │  ├─ shapes
│  │  │  ├─ stepArrow1.png
│  │  │  └─ stepArrow2.png
│  │  ├─ video-placeholder.png
│  │  └─ whatsapp.png
│  ├─ index.php
│  ├─ prebuilt-build
│  │  ├─ assets
│  │  │  ├─ Add-edd51ebf.js
│  │  │  ├─ AiAssistant-bcb1cf6d.js
│  │  │  ├─ AlertModal-aa2d85e7.js
│  │  │  ├─ ApiDocumentation-4a63104e.js
│  │  │  ├─ ApiDocumentation-e7b8632f.js
│  │  │  ├─ apiDocumentationExamples-6434da33.js
│  │  │  ├─ App-12c42f6b.js
│  │  │  ├─ app-237f75bb.js
│  │  │  ├─ App-70084c2a.js
│  │  │  ├─ app-a31a7b52.css
│  │  │  ├─ App-baf3bb8e.css
│  │  │  ├─ App-c7cc1d37.js
│  │  │  ├─ app-core-312cfb58.js
│  │  │  ├─ Automation-8c64d7d1.js
│  │  │  ├─ Billing-832b8981.js
│  │  │  ├─ BillingInvoiceTable-b849c744.js
│  │  │  ├─ Broadcast-b6ddc084.js
│  │  │  ├─ Builder-224ce94d.css
│  │  │  ├─ Builder-be46f410.js
│  │  │  ├─ CampaignForm-e7d8c891.js
│  │  │  ├─ charting-20351e32.js
│  │  │  ├─ CompanyIndex-2a7a7e01.js
│  │  │  ├─ Contact-160970e5.js
│  │  │  ├─ Contact-41569dcd.js
│  │  │  ├─ Contact-f282116c.js
│  │  │  ├─ ContactDetails-67ffde87.js
│  │  │  ├─ ContactInfo-d905362c.js
│  │  │  ├─ ContactTable-f946086a.js
│  │  │  ├─ CookieConsentBanner-c8f81145.css
│  │  │  ├─ CookieConsentBanner-ee8acc99.js
│  │  │  ├─ CookiePolicy-0833a4bd.js
│  │  │  ├─ CookiePolicy-2cf51023.js
│  │  │  ├─ CookiePolicy-697e596a.css
│  │  │  ├─ CookiePolicy-f81c47e1.css
│  │  │  ├─ Coupon-745e6d20.js
│  │  │  ├─ Create-034fe40a.js
│  │  │  ├─ Create-2ea64e0d.js
│  │  │  ├─ Create-463e7597.js
│  │  │  ├─ Create-49a7cb9d.js
│  │  │  ├─ Create-8790da68.js
│  │  │  ├─ Create-e26b9dc8.js
│  │  │  ├─ Dashboard-3ce77dff.css
│  │  │  ├─ Dashboard-8038a30f.css
│  │  │  ├─ Dashboard-8ca549c8.js
│  │  │  ├─ Dashboard-92c7f4fd.js
│  │  │  ├─ Directory-58a357bd.js
│  │  │  ├─ Documentation-688358c6.js
│  │  │  ├─ Dropdown-d85a5db5.js
│  │  │  ├─ DropdownItem-abf18fe4.js
│  │  │  ├─ Dynamic-3a7f3bd6.css
│  │  │  ├─ Dynamic-62c4a1be.js
│  │  │  ├─ Dynamic-646227d4.css
│  │  │  ├─ Dynamic-9234bf65.js
│  │  │  ├─ Edit-8798f7b0.js
│  │  │  ├─ Edit-d17871f4.js
│  │  │  ├─ editor-libs-896bd2ae.js
│  │  │  ├─ editor-libs-c005f632.css
│  │  │  ├─ Email-5ed8b1a0.js
│  │  │  ├─ Email-e66b43da.js
│  │  │  ├─ EmbeddedSignup-f422bafa.js
│  │  │  ├─ Error-9efae590.js
│  │  │  ├─ Faqs-16923e2b.js
│  │  │  ├─ Faqs-2167ec9e.css
│  │  │  ├─ Faqs-75ca159a.css
│  │  │  ├─ Faqs-b60a1cbe.js
│  │  │  ├─ flow-builder-core-e1207c5b.css
│  │  │  ├─ flow-builder-core-fce026dc.js
│  │  │  ├─ FlowBuilder-5694163e.js
│  │  │  ├─ flowBuilderRouting-7f741bd8.js
│  │  │  ├─ Forgot-556f038e.js
│  │  │  ├─ FormCheckbox-ea51f5d1.js
│  │  │  ├─ FormImage-3946d82a.js
│  │  │  ├─ FormImageAsset-0c617fcb.js
│  │  │  ├─ FormImageLogo-c047ff63.js
│  │  │  ├─ FormInput-d0ca7e8c.js
│  │  │  ├─ FormModalModified-a933315d.js
│  │  │  ├─ FormPhoneInput-9baf5723.js
│  │  │  ├─ FormTextArea-dab388fc.js
│  │  │  ├─ FormToggleSwitch-332c526d.js
│  │  │  ├─ FrontendLayout-16a1572e.js
│  │  │  ├─ FrontendLayout-93177520.css
│  │  │  ├─ FrontendLayout-be308b64.css
│  │  │  ├─ FrontendLayout-f966488c.js
│  │  │  ├─ General-7cab32df.js
│  │  │  ├─ General-f48afd53.js
│  │  │  ├─ Group-ee1c69fb.js
│  │  │  ├─ HeaderTextArea-0343e393.js
│  │  │  ├─ icon-libs-8c6641f9.js
│  │  │  ├─ Index-100f20c8.js
│  │  │  ├─ Index-131748ad.js
│  │  │  ├─ Index-18e71cf0.js
│  │  │  ├─ Index-1c2ccd53.js
│  │  │  ├─ Index-30a96744.js
│  │  │  ├─ Index-3288742d.js
│  │  │  ├─ Index-354c8285.js
│  │  │  ├─ Index-3ef69cfa.js
│  │  │  ├─ Index-4bba745f.js
│  │  │  ├─ Index-4f9c13bf.js
│  │  │  ├─ Index-52c8bb0f.js
│  │  │  ├─ Index-5421a6ca.js
│  │  │  ├─ Index-61e933d1.js
│  │  │  ├─ Index-62cd7b16.js
│  │  │  ├─ Index-77e8d7c5.js
│  │  │  ├─ Index-803d292e.js
│  │  │  ├─ Index-928b3e03.js
│  │  │  ├─ Index-a1c13ac6.js
│  │  │  ├─ Index-aa876574.js
│  │  │  ├─ Index-ae97e9c1.js
│  │  │  ├─ Index-bba34639.js
│  │  │  ├─ Index-bbb2f9f1.js
│  │  │  ├─ Index-c77f3cbf.js
│  │  │  ├─ Index-d591a19e.js
│  │  │  ├─ Index-e3a77d0e.js
│  │  │  ├─ Index-ee74285f.js
│  │  │  ├─ Index-f805d9cd.js
│  │  │  ├─ Invite-7cd6e3fa.js
│  │  │  ├─ InvoiceDetailsShell-83319252.js
│  │  │  ├─ InvoiceShow-5ec10919.js
│  │  │  ├─ InvoiceShow-a50ee2a3.js
│  │  │  ├─ LangToggle-fd1503e0.js
│  │  │  ├─ Layout-3aa23817.js
│  │  │  ├─ Layout-412db63e.js
│  │  │  ├─ Layout-93e09cf9.js
│  │  │  ├─ Layout-9604f6c4.js
│  │  │  ├─ Login-2c5dd4a5.js
│  │  │  ├─ Main-f3cd3b58.js
│  │  │  ├─ media-tools-d1499fe4.js
│  │  │  ├─ Menu-3496d8f2.js
│  │  │  ├─ Menu-97ddccd6.css
│  │  │  ├─ Menu-bc7b6d09.js
│  │  │  ├─ Menu-c80312de.js
│  │  │  ├─ MobileSidebar-17ffcace.js
│  │  │  ├─ MobileSidebar-c3dad448.js
│  │  │  ├─ Modal-5c2cb6dd.js
│  │  │  ├─ OrganizationModal-d381f5b8.js
│  │  │  ├─ OrganizationSelect-c439b0f5.js
│  │  │  ├─ OrganizationTable-86a9a46a.js
│  │  │  ├─ Outfit-Black-3cabab9a.ttf
│  │  │  ├─ Outfit-Bold-3ee4507c.ttf
│  │  │  ├─ Outfit-ExtraBold-7f2a95dc.ttf
│  │  │  ├─ Outfit-ExtraLight-93ead84d.ttf
│  │  │  ├─ Outfit-Light-9518ce3d.ttf
│  │  │  ├─ Outfit-Medium-33e6b2d4.ttf
│  │  │  ├─ Outfit-Regular-7dd6d797.ttf
│  │  │  ├─ Outfit-SemiBold-c67e289e.ttf
│  │  │  ├─ Outfit-Thin-11847921.ttf
│  │  │  ├─ overlay-libs-a86d49c2.js
│  │  │  ├─ Pagination-e5900b00.js
│  │  │  ├─ PaymentGateway-d76ff1ad.js
│  │  │  ├─ PaymentGatewayMoyasar-3b0178d8.js
│  │  │  ├─ phone-input-b1e3d193.js
│  │  │  ├─ ping-ar-lt-black-52fe8c16.otf
│  │  │  ├─ ping-ar-lt-bold-fd52328b.otf
│  │  │  ├─ ping-ar-lt-extralight-7d84570a.otf
│  │  │  ├─ ping-ar-lt-hairline-ded51956.otf
│  │  │  ├─ ping-ar-lt-heavy-87dbc39f.otf
│  │  │  ├─ ping-ar-lt-light-30eff624.otf
│  │  │  ├─ ping-ar-lt-medium-3ccb6de4.otf
│  │  │  ├─ ping-ar-lt-regular-dada9ce8.otf
│  │  │  ├─ ping-ar-lt-thin-27816620.otf
│  │  │  ├─ Plan-dba3e741.js
│  │  │  ├─ PremiumHome-1da0acbb.js
│  │  │  ├─ Pricing-9c51e185.js
│  │  │  ├─ Pricing-b9fa0c82.js
│  │  │  ├─ Product-1cd867bf.js
│  │  │  ├─ Product-e324119f.js
│  │  │  ├─ ProfileModal-6bc366d4.js
│  │  │  ├─ realtime-ff291f12.js
│  │  │  ├─ Register-3403a6d8.js
│  │  │  ├─ Reset-045eb88f.js
│  │  │  ├─ Seo-f68d5780.js
│  │  │  ├─ Show-000fe081.js
│  │  │  ├─ Show-0d89c5b9.js
│  │  │  ├─ Show-20c0a36f.js
│  │  │  ├─ Show-66aac42f.js
│  │  │  ├─ Show-697892fc.js
│  │  │  ├─ Show-7f1869b9.js
│  │  │  ├─ Show-97ab55d6.js
│  │  │  ├─ Show-a64ab8c6.js
│  │  │  ├─ Show-c30ff6fe.js
│  │  │  ├─ Show-f229392d.js
│  │  │  ├─ Show-f8bf4637.js
│  │  │  ├─ ShowDetails-a0969d6d.js
│  │  │  ├─ Sidebar-28e3a512.js
│  │  │  ├─ Sidebar-531e4fbc.js
│  │  │  ├─ Sidebar-90ee4ca4.js
│  │  │  ├─ Socials-4be727fd.js
│  │  │  ├─ Storage-412c03d5.js
│  │  │  ├─ Subscription-7436eafb.js
│  │  │  ├─ Table-6b0960b2.js
│  │  │  ├─ TableBodyRowItem-f3531d64.js
│  │  │  ├─ TableHeaderRowItem-cff80beb.js
│  │  │  ├─ Tax-a442d1f9.js
│  │  │  ├─ Ticket-63edea3d.js
│  │  │  ├─ TicketTable-97b3777e.js
│  │  │  ├─ Timezone-1acd4240.js
│  │  │  ├─ ui-vue-libs-505066b7.js
│  │  │  ├─ ui-vue-libs-f4606fa9.css
│  │  │  ├─ UiEmptyState-a096d283.js
│  │  │  ├─ UiPageHeader-efefeb25.js
│  │  │  ├─ UiSectionCard-2dc8ac92.css
│  │  │  ├─ UiSectionCard-6e4d2f39.js
│  │  │  ├─ UiStatCard-ec71da9d.js
│  │  │  ├─ Usage-ba4154a9.js
│  │  │  ├─ useAdminPermission-8a0679a3.js
│  │  │  ├─ useAlertModal-b57719b2.js
│  │  │  ├─ UserTable-3b64af30.js
│  │  │  ├─ useRtl-9abe8a93.js
│  │  │  ├─ utility-libs-cd5017bc.js
│  │  │  ├─ vendor-axios-853f16bf.js
│  │  │  ├─ vendor-call-bind-apply-helpers-5c7e5704.js
│  │  │  ├─ vendor-call-bound-b3742bf7.js
│  │  │  ├─ vendor-deepmerge-6fd6c168.js
│  │  │  ├─ vendor-dunder-proto-977a0485.js
│  │  │  ├─ vendor-es-define-property-27915812.js
│  │  │  ├─ vendor-es-errors-c75f5a96.js
│  │  │  ├─ vendor-es-object-atoms-11ea1ab9.js
│  │  │  ├─ vendor-fast-diff-0f6b691d.js
│  │  │  ├─ vendor-function-bind-61637ca6.js
│  │  │  ├─ vendor-get-intrinsic-62030f7f.js
│  │  │  ├─ vendor-get-proto-f2708e4a.js
│  │  │  ├─ vendor-gopd-30775706.js
│  │  │  ├─ vendor-has-symbols-76fb15e9.js
│  │  │  ├─ vendor-hasown-5c371438.js
│  │  │  ├─ vendor-lodash.clonedeep-7b11fce4.js
│  │  │  ├─ vendor-lodash.isequal-94d5f2c1.js
│  │  │  ├─ vendor-math-intrinsics-9389030e.js
│  │  │  ├─ vendor-nprogress-f5bbfecf.js
│  │  │  ├─ vendor-object-inspect-e1a5fb39.js
│  │  │  ├─ vendor-qs-d0ac7f0f.js
│  │  │  ├─ vendor-side-channel-8dbd4fbd.js
│  │  │  ├─ vendor-side-channel-list-a806fb2e.js
│  │  │  ├─ vendor-side-channel-map-a3e5937a.js
│  │  │  ├─ vendor-side-channel-weakmap-1f6fa6a3.js
│  │  │  ├─ vendor-sortablejs-b5ab7109.js
│  │  │  ├─ VerifyEmail-d0325082.js
│  │  │  ├─ View-1a2e68c8.js
│  │  │  ├─ View-2ed782b9.js
│  │  │  ├─ View-f61820ad.js
│  │  │  ├─ vue-tel-input-c90391ff.css
│  │  │  ├─ Whatsapp-a9cd3675.js
│  │  │  ├─ whatsapp-bg-02-b96c2a6c.png
│  │  │  ├─ WhatsappTemplate-0dfc0ad3.js
│  │  │  └─ _plugin-vue_export-helper-c27b6911.js
│  │  └─ manifest.json
│  ├─ robots.txt
│  └─ sounds
│     ├─ chime-pop.wav
│     ├─ digital-quick-tone.wav
│     ├─ double-beep-tone.wav
│     ├─ long-pop.wav
│     └─ message-pop-alert.mp3
├─ README.md
├─ resources
│  ├─ css
│  │  └─ app.css
│  ├─ fonts
│  │  ├─ Outfit
│  │  │  ├─ Outfit-Black.ttf
│  │  │  ├─ Outfit-Bold.ttf
│  │  │  ├─ Outfit-ExtraBold.ttf
│  │  │  ├─ Outfit-ExtraLight.ttf
│  │  │  ├─ Outfit-Light.ttf
│  │  │  ├─ Outfit-Medium.ttf
│  │  │  ├─ Outfit-Regular.ttf
│  │  │  ├─ Outfit-SemiBold.ttf
│  │  │  └─ Outfit-Thin.ttf
│  │  └─ ping-ar-lt
│  │     ├─ ping-ar-lt-black.otf
│  │     ├─ ping-ar-lt-bold.otf
│  │     ├─ ping-ar-lt-extralight.otf
│  │     ├─ ping-ar-lt-hairline.otf
│  │     ├─ ping-ar-lt-heavy.otf
│  │     ├─ ping-ar-lt-light.otf
│  │     ├─ ping-ar-lt-medium.otf
│  │     ├─ ping-ar-lt-regular.otf
│  │     └─ ping-ar-lt-thin.otf
│  ├─ images
│  │  └─ whatsapp-bg-02.png
│  ├─ js
│  │  ├─ app.js
│  │  ├─ bootstrap.js
│  │  ├─ Components
│  │  │  ├─ AdminUser
│  │  │  │  ├─ ShowAccessTab.vue
│  │  │  │  ├─ ShowEditTab.vue
│  │  │  │  └─ ShowOverviewTab.vue
│  │  │  ├─ AlertModal.vue
│  │  │  ├─ AutomationFlows
│  │  │  │  ├─ FlowAutosizeTextarea.vue
│  │  │  │  ├─ flowBuilderCanvas.css
│  │  │  │  ├─ flowBuilderCopy.js
│  │  │  │  ├─ flowBuilderDanger.js
│  │  │  │  ├─ FlowBuilderDangerModals.vue
│  │  │  │  ├─ flowBuilderDraft.js
│  │  │  │  ├─ flowBuilderGoalPresets.js
│  │  │  │  ├─ flowBuilderGraph.js
│  │  │  │  ├─ FlowBuilderHeaderCard.vue
│  │  │  │  ├─ flowBuilderInsights.js
│  │  │  │  ├─ flowBuilderMeta.js
│  │  │  │  ├─ flowBuilderRouting.js
│  │  │  │  ├─ flowBuilderStudio.js
│  │  │  │  ├─ flowBuilderValidation.js
│  │  │  │  ├─ FlowCanvasCompactNode.vue
│  │  │  │  ├─ FlowCanvasEdge.vue
│  │  │  │  ├─ flowCanvasLayout.js
│  │  │  │  ├─ FlowCanvasNode.vue
│  │  │  │  ├─ FlowCanvasNodeHeader.vue
│  │  │  │  ├─ FlowCanvasNodeMenu.vue
│  │  │  │  ├─ FlowCanvasNodeRoutingHealth.vue
│  │  │  │  ├─ flowCanvasRuntime.js
│  │  │  │  ├─ flowCanvasRuntime.test.js
│  │  │  │  ├─ FlowCreateModal.vue
│  │  │  │  ├─ FlowDangerConfirmModal.vue
│  │  │  │  ├─ FlowExitConfirmModal.vue
│  │  │  │  ├─ FlowInspectorPanel.vue
│  │  │  │  ├─ FlowListRowMenu.vue
│  │  │  │  ├─ FlowMetaEditModal.vue
│  │  │  │  ├─ FlowNodeInspectorConditionForm.vue
│  │  │  │  ├─ FlowNodeInspectorContactActionForm.vue
│  │  │  │  ├─ FlowNodeInspectorExternalActionForm.vue
│  │  │  │  ├─ FlowNodeInspectorHandoffForm.vue
│  │  │  │  ├─ FlowNodeInspectorInteractiveForm.vue
│  │  │  │  ├─ FlowNodeInspectorRenderer.vue
│  │  │  │  ├─ FlowNodeInspectorTextMediaForm.vue
│  │  │  │  ├─ FlowNodeInspectorTriggerForm.vue
│  │  │  │  ├─ flowNodePresenter.js
│  │  │  │  ├─ FlowPreviewDrawer.vue
│  │  │  │  ├─ FlowPreviewModal.vue
│  │  │  │  ├─ FlowReadinessPanel.vue
│  │  │  │  ├─ FlowStepGuidePanel.vue
│  │  │  │  ├─ Ui
│  │  │  │  │  ├─ AutomationButton.vue
│  │  │  │  │  ├─ AutomationField.vue
│  │  │  │  │  └─ AutomationStatusBadge.vue
│  │  │  │  ├─ useFlowCanvasNode.js
│  │  │  │  ├─ useFlowCanvasSurfaceDrag.js
│  │  │  │  └─ useFlowNodeInspector.js
│  │  │  ├─ BaseListbox.vue
│  │  │  ├─ Billing
│  │  │  │  └─ InvoiceDetailsShell.vue
│  │  │  ├─ CampaignForm.vue
│  │  │  ├─ ChatComponents
│  │  │  │  ├─ ChatBubble.vue
│  │  │  │  ├─ ChatContact.vue
│  │  │  │  ├─ ChatForm.vue
│  │  │  │  ├─ ChatHeader.vue
│  │  │  │  ├─ chatMessagePreview.js
│  │  │  │  ├─ chatMessagePreview.test.js
│  │  │  │  ├─ ChatTable.vue
│  │  │  │  ├─ ChatTemplateForm.vue
│  │  │  │  └─ ChatThread.vue
│  │  │  ├─ CompanyTeamEmployeeModal.vue
│  │  │  ├─ ContactComponents
│  │  │  │  └─ CreateForm.vue
│  │  │  ├─ ContactGroupInfo.vue
│  │  │  ├─ ContactImportModal.vue
│  │  │  ├─ ContactInfo.vue
│  │  │  ├─ CookieConsentBanner.vue
│  │  │  ├─ DocumentUploadModal.vue
│  │  │  ├─ Dropdown.vue
│  │  │  ├─ DropdownItem.vue
│  │  │  ├─ DropdownItemGroup.vue
│  │  │  ├─ EmbeddedSignupBtn.vue
│  │  │  ├─ ExportModal.vue
│  │  │  ├─ FormCheckbox.vue
│  │  │  ├─ FormImage.vue
│  │  │  ├─ FormImageAsset.vue
│  │  │  ├─ FormImageFavicon.vue
│  │  │  ├─ FormImageLogo.vue
│  │  │  ├─ FormInput.vue
│  │  │  ├─ FormModal.vue
│  │  │  ├─ FormModalModified.vue
│  │  │  ├─ FormPhoneInput.vue
│  │  │  ├─ FormSelect.vue
│  │  │  ├─ FormSelectCombo.vue
│  │  │  ├─ FormTemplateTextArea.vue
│  │  │  ├─ FormTextArea.vue
│  │  │  ├─ FormToggleSwitch.vue
│  │  │  ├─ LangToggle.vue
│  │  │  ├─ Modal.vue
│  │  │  ├─ Modals
│  │  │  │  ├─ PlanTransferModal.vue
│  │  │  │  └─ RoleTransferModal.vue
│  │  │  ├─ OrganizationModal.vue
│  │  │  ├─ Pagination.vue
│  │  │  ├─ PaymentConfigModals
│  │  │  │  └─ MoyasarModal.vue
│  │  │  ├─ ProfileModal.vue
│  │  │  ├─ SortDirectionToggle.vue
│  │  │  ├─ Table.vue
│  │  │  ├─ TableBody.vue
│  │  │  ├─ TableBodyRow.vue
│  │  │  ├─ TableBodyRowItem.vue
│  │  │  ├─ TableHeader.vue
│  │  │  ├─ TableHeaderRow.vue
│  │  │  ├─ TableHeaderRowItem.vue
│  │  │  ├─ Tables
│  │  │  │  ├─ AdminRoleTable.vue
│  │  │  │  ├─ AutoReplyTable.vue
│  │  │  │  ├─ BillingInvoiceTable.vue
│  │  │  │  ├─ BillingTable.vue
│  │  │  │  ├─ CampaignLogTable.vue
│  │  │  │  ├─ CampaignTable.vue
│  │  │  │  ├─ ContactFieldTable.vue
│  │  │  │  ├─ ContactTable.vue
│  │  │  │  ├─ CouponTable.vue
│  │  │  │  ├─ DocumentTable.vue
│  │  │  │  ├─ EmailTemplateTable.vue
│  │  │  │  ├─ FaqTable.vue
│  │  │  │  ├─ LangTable.vue
│  │  │  │  ├─ LangTranslationsTable.vue
│  │  │  │  ├─ OrganizationTable.vue
│  │  │  │  ├─ PageTable.vue
│  │  │  │  ├─ PaymentGatewayTable.vue
│  │  │  │  ├─ RoleTable.vue
│  │  │  │  ├─ SubscriptionPlanTable.vue
│  │  │  │  ├─ TaxTable.vue
│  │  │  │  ├─ TeamTable.vue
│  │  │  │  ├─ TemplateTable.vue
│  │  │  │  ├─ TestimonialTable.vue
│  │  │  │  ├─ TicketTable.vue
│  │  │  │  ├─ TokenTable.vue
│  │  │  │  └─ UserTable.vue
│  │  │  ├─ Template
│  │  │  │  ├─ BodyTextArea.vue
│  │  │  │  └─ HeaderTextArea.vue
│  │  │  ├─ TicketStatusToggle.vue
│  │  │  ├─ UI
│  │  │  │  ├─ UiActionBar.vue
│  │  │  │  ├─ UiDataTableShell.vue
│  │  │  │  ├─ UiEmptyState.vue
│  │  │  │  ├─ UiFormSection.vue
│  │  │  │  ├─ UiPageHeader.vue
│  │  │  │  ├─ UiSectionCard.vue
│  │  │  │  └─ UiStatCard.vue
│  │  │  └─ WhatsappTemplate.vue
│  │  ├─ Composables
│  │  │  ├─ useAdminPermission.js
│  │  │  ├─ useAlertModal.js
│  │  │  ├─ useFrontendContactInfo.js
│  │  │  ├─ useRtl.js
│  │  │  ├─ useTableFormData.js
│  │  │  └─ useWorkspaceAccess.js
│  │  ├─ echo.js
│  │  ├─ initial-elements.js
│  │  ├─ lib
│  │  │  ├─ constant.ts
│  │  │  └─ utils.ts
│  │  ├─ Pages
│  │  │  ├─ Admin
│  │  │  │  ├─ Billing
│  │  │  │  │  └─ InvoiceShow.vue
│  │  │  │  ├─ Customer
│  │  │  │  │  ├─ Index.vue
│  │  │  │  │  └─ Show.vue
│  │  │  │  ├─ Dashboard.vue
│  │  │  │  ├─ Faq
│  │  │  │  │  ├─ Index.vue
│  │  │  │  │  └─ Show.vue
│  │  │  │  ├─ Layout
│  │  │  │  │  ├─ App.vue
│  │  │  │  │  ├─ Menu.vue
│  │  │  │  │  ├─ MobileSidebar.vue
│  │  │  │  │  └─ Sidebar.vue
│  │  │  │  ├─ Organization
│  │  │  │  │  ├─ Create.vue
│  │  │  │  │  ├─ Index.vue
│  │  │  │  │  └─ Show.vue
│  │  │  │  ├─ Payment
│  │  │  │  │  └─ Index.vue
│  │  │  │  ├─ Role
│  │  │  │  │  ├─ Index.vue
│  │  │  │  │  └─ Show.vue
│  │  │  │  ├─ Setting
│  │  │  │  │  ├─ Billing.vue
│  │  │  │  │  ├─ Broadcast.vue
│  │  │  │  │  ├─ Coupon.vue
│  │  │  │  │  ├─ Email.vue
│  │  │  │  │  ├─ EmailTemplate
│  │  │  │  │  │  ├─ Index.vue
│  │  │  │  │  │  └─ Show.vue
│  │  │  │  │  ├─ EmbeddedSignup.vue
│  │  │  │  │  ├─ Features
│  │  │  │  │  │  ├─ AiAssistant.vue
│  │  │  │  │  │  └─ FlowBuilder.vue
│  │  │  │  │  ├─ Frontend
│  │  │  │  │  │  ├─ ContactDetails.vue
│  │  │  │  │  │  ├─ Index.vue
│  │  │  │  │  │  ├─ PremiumHome.vue
│  │  │  │  │  │  └─ Seo.vue
│  │  │  │  │  ├─ General.vue
│  │  │  │  │  ├─ Language
│  │  │  │  │  │  ├─ Index.vue
│  │  │  │  │  │  └─ Show.vue
│  │  │  │  │  ├─ Layout
│  │  │  │  │  │  ├─ App.vue
│  │  │  │  │  │  └─ Sidebar.vue
│  │  │  │  │  ├─ Page
│  │  │  │  │  │  ├─ Index.vue
│  │  │  │  │  │  └─ Show.vue
│  │  │  │  │  ├─ PaymentGateway.vue
│  │  │  │  │  ├─ PaymentGatewayMoyasar.vue
│  │  │  │  │  ├─ Socials.vue
│  │  │  │  │  ├─ Storage.vue
│  │  │  │  │  ├─ Subscription.vue
│  │  │  │  │  ├─ Tax.vue
│  │  │  │  │  └─ Timezone.vue
│  │  │  │  ├─ SubscriptionPlan
│  │  │  │  │  ├─ Index.vue
│  │  │  │  │  └─ Show.vue
│  │  │  │  ├─ Team
│  │  │  │  │  ├─ Index.vue
│  │  │  │  │  └─ Show.vue
│  │  │  │  ├─ Testimonial
│  │  │  │  │  ├─ Index.vue
│  │  │  │  │  └─ Show.vue
│  │  │  │  ├─ Ticket
│  │  │  │  │  ├─ Create.vue
│  │  │  │  │  ├─ Index.vue
│  │  │  │  │  └─ View.vue
│  │  │  │  ├─ User
│  │  │  │  │  ├─ Create.vue
│  │  │  │  │  ├─ Directory.vue
│  │  │  │  │  └─ ShowDetails.vue
│  │  │  │  └─ UserLog
│  │  │  │     └─ Email.vue
│  │  │  ├─ Auth
│  │  │  │  ├─ Forgot.vue
│  │  │  │  ├─ Invite.vue
│  │  │  │  ├─ Login.vue
│  │  │  │  ├─ Register.vue
│  │  │  │  ├─ Reset.vue
│  │  │  │  └─ VerifyEmail.vue
│  │  │  ├─ Error.vue
│  │  │  ├─ Frontend
│  │  │  │  ├─ ApiDocumentation.vue
│  │  │  │  ├─ Contact.vue
│  │  │  │  ├─ CookiePolicy.vue
│  │  │  │  ├─ Dynamic.vue
│  │  │  │  ├─ Faqs.vue
│  │  │  │  ├─ FrontendLayout.vue
│  │  │  │  ├─ Index.vue
│  │  │  │  ├─ Layout.vue
│  │  │  │  ├─ Pricing.vue
│  │  │  │  └─ Product.vue
│  │  │  ├─ FrontendPremium
│  │  │  │  ├─ ApiDocumentation.vue
│  │  │  │  ├─ Contact.vue
│  │  │  │  ├─ CookiePolicy.vue
│  │  │  │  ├─ Dynamic.vue
│  │  │  │  ├─ Faqs.vue
│  │  │  │  ├─ FrontendLayout.vue
│  │  │  │  ├─ Index.vue
│  │  │  │  ├─ Layout.vue
│  │  │  │  ├─ Pricing.vue
│  │  │  │  └─ Product.vue
│  │  │  └─ User
│  │  │     ├─ Automation
│  │  │     │  ├─ Basic
│  │  │     │  │  ├─ Create.vue
│  │  │     │  │  ├─ Edit.vue
│  │  │     │  │  └─ Index.vue
│  │  │     │  ├─ Flows
│  │  │     │  │  ├─ Builder.vue
│  │  │     │  │  └─ Index.vue
│  │  │     │  └─ Layout.vue
│  │  │     ├─ Billing
│  │  │     │  ├─ Index.vue
│  │  │     │  ├─ InvoiceShow.vue
│  │  │     │  ├─ Plan.vue
│  │  │     │  └─ Usage.vue
│  │  │     ├─ Campaign
│  │  │     │  ├─ Create.vue
│  │  │     │  ├─ Index.vue
│  │  │     │  └─ View.vue
│  │  │     ├─ Chat
│  │  │     │  └─ Index.vue
│  │  │     ├─ Contact
│  │  │     │  ├─ Group.vue
│  │  │     │  └─ Index.vue
│  │  │     ├─ Dashboard.vue
│  │  │     ├─ Developer
│  │  │     │  ├─ Documentation.vue
│  │  │     │  ├─ Index.vue
│  │  │     │  └─ Menu.vue
│  │  │     ├─ Layout
│  │  │     │  ├─ App.vue
│  │  │     │  ├─ Menu.vue
│  │  │     │  ├─ MobileSidebar.vue
│  │  │     │  └─ Sidebar.vue
│  │  │     ├─ OrganizationSelect.vue
│  │  │     ├─ Role
│  │  │     │  ├─ Index.vue
│  │  │     │  └─ Show.vue
│  │  │     ├─ Settings
│  │  │     │  ├─ Automation.vue
│  │  │     │  ├─ Contact.vue
│  │  │     │  ├─ General.vue
│  │  │     │  ├─ Layout.vue
│  │  │     │  ├─ Main.vue
│  │  │     │  ├─ Ticket.vue
│  │  │     │  └─ Whatsapp.vue
│  │  │     ├─ Support
│  │  │     │  ├─ Create.vue
│  │  │     │  ├─ Index.vue
│  │  │     │  └─ View.vue
│  │  │     ├─ Team
│  │  │     │  ├─ CompanyIndex.vue
│  │  │     │  └─ Index.vue
│  │  │     └─ Templates
│  │  │        ├─ Add.vue
│  │  │        ├─ Edit.vue
│  │  │        └─ Index.vue
│  │  └─ Utils
│  │     ├─ apiDocumentationExamples.js
│  │     ├─ apiDocumentationExamples.test.js
│  │     ├─ flowIconRegistry.js
│  │     ├─ flowNodeVisuals.js
│  │     ├─ i18nLookup.js
│  │     └─ optionLocalizers.js
│  └─ views
│     ├─ app.blade.php
│     ├─ billing
│     │  └─ invoice-document.blade.php
│     ├─ emails
│     │  └─ custom_email_template.blade.php
│     ├─ errors
│     │  ├─ 403.blade.php
│     │  ├─ 404.blade.php
│     │  └─ 500.blade.php
│     └─ sitemap.blade.php
├─ routes
│  ├─ api
│  │  ├─ campaigns.php
│  │  ├─ canned-replies.php
│  │  ├─ contact-groups.php
│  │  ├─ contacts.php
│  │  ├─ messages.php
│  │  ├─ public.php
│  │  ├─ templates.php
│  │  ├─ utilities.php
│  │  └─ verification.php
│  ├─ api.php
│  ├─ channels.php
│  ├─ console.php
│  ├─ web
│  │  ├─ admin
│  │  │  ├─ core.php
│  │  │  ├─ languages.php
│  │  │  ├─ logs.php
│  │  │  ├─ settings.php
│  │  │  └─ support.php
│  │  ├─ admin.php
│  │  ├─ auth.php
│  │  ├─ automation.php
│  │  ├─ public.php
│  │  ├─ user
│  │  │  ├─ campaigns-templates.php
│  │  │  ├─ chats-contacts.php
│  │  │  ├─ dashboard-billing.php
│  │  │  ├─ developer-tools.php
│  │  │  ├─ settings-core.php
│  │  │  ├─ support-messages.php
│  │  │  └─ team.php
│  │  └─ user.php
│  └─ web.php
├─ scripts
│  ├─ check-build-budgets.php
│  ├─ check-dependency-audits.php
│  ├─ check-prebuilt-parity.php
│  ├─ check-repo-hygiene.php
│  ├─ ensure-prebuilt-build.php
│  ├─ ensure-vite-i18n-placeholders.mjs
│  ├─ go-no-go.ps1
│  ├─ go-no-go.sh
│  ├─ i18n
│  │  ├─ allowlist_terms.php
│  │  ├─ apply-deep-translations.php
│  │  ├─ audit-backend-translations.php
│  │  ├─ audit-ui-merge.php
│  │  ├─ audit-ui-options.php
│  │  ├─ audit-ui-template-ast.mjs
│  │  ├─ audit-ui-translations.php
│  │  ├─ check-ar-en.php
│  │  ├─ deep-untranslated-audit.php
│  │  ├─ excluded_paths.php
│  │  ├─ prune-unused-locale-keys.php
│  │  └─ wrap-php-user-facing-strings.php
│  ├─ lint-foundation.php
│  ├─ preflight.ps1
│  ├─ prepush-check.ps1
│  ├─ prune-build-assets.php
│  ├─ prune-build-assets.ps1
│  ├─ prune-build-assets.sh
│  ├─ readiness-audit.ps1
│  ├─ refresh-prebuilt-build.php
│  ├─ safe-test.ps1
│  ├─ safe-test.sh
│  ├─ server_update.sh
│  ├─ smoke-critical.ps1
│  ├─ stage-release-batch.ps1
│  └─ stage-release-batch.sh
├─ serve.log
├─ storage
│  └─ framework
├─ tailwind.config.js
├─ tests
│  ├─ bootstrap.php
│  ├─ Concerns
│  │  └─ CreatesOrganizationContext.php
│  ├─ Feature
│  │  ├─ AddonResourceResolvedInputFieldsTest.php
│  │  ├─ AdminEmailTemplateEditorTest.php
│  │  ├─ AdminEmbeddedSignupMetaReviewTest.php
│  │  ├─ AdminEmbeddedSignupSettingsTest.php
│  │  ├─ AdminFlowBuilderAddonSetupRouteTest.php
│  │  ├─ AdminFrontendSeoSettingsTest.php
│  │  ├─ AdminGeneralSettingsColorEditingTest.php
│  │  ├─ AdminLanguageDefaultRouteTest.php
│  │  ├─ AdminMoyasarPaymentGatewaySettingsTest.php
│  │  ├─ AdminOrganizationShowTeamDirectoryTest.php
│  │  ├─ AdminPermissionMiddlewareTest.php
│  │  ├─ AdminPremiumHomeMediaSettingsTest.php
│  │  ├─ AdminProvisioningCreateFlowTest.php
│  │  ├─ AdminRoleManagementTest.php
│  │  ├─ AdminSettingsFeaturesSurfaceTest.php
│  │  ├─ AdminSubscriptionPlanDefinitionTest.php
│  │  ├─ AdminSystemOwnerProtectionTest.php
│  │  ├─ AdminUserDeletionSyncTest.php
│  │  ├─ AdminUserDirectoryTest.php
│  │  ├─ AiAssistantPlanBackfillTest.php
│  │  ├─ ApiHardeningTest.php
│  │  ├─ ApiMessageEntitlementEnforcementTest.php
│  │  ├─ ArchitectureBudgetGuardTest.php
│  │  ├─ ArchitectureEnvBoundaryTest.php
│  │  ├─ AuthOrganizationFlowTest.php
│  │  ├─ AutomationFlowAssetAccessTest.php
│  │  ├─ AutomationFlowBuilderV2Test.php
│  │  ├─ AutomationFlowFeatureAccessTest.php
│  │  ├─ BackendTranslationAuditTest.php
│  │  ├─ BackfillAiAssistantInputFieldsMigrationTest.php
│  │  ├─ BackfillOrganizationApiKeysHashMigrationTest.php
│  │  ├─ BackfillOrganizationRolePermissionsMigrationTest.php
│  │  ├─ BasicFeatureTest.php
│  │  ├─ BillingCheckoutIntentEmailDispatchTest.php
│  │  ├─ BillingCheckoutIntentFlowTest.php
│  │  ├─ BillingDisplayStateTest.php
│  │  ├─ BillingInvoiceDocumentTest.php
│  │  ├─ CampaignDispatchSecurityTest.php
│  │  ├─ CampaignRetrySettingsTest.php
│  │  ├─ ChatMessageEntitlementTest.php
│  │  ├─ ChatMessagingIsolationTest.php
│  │  ├─ ChatTicketProvisioningTest.php
│  │  ├─ ClientRoleAccessBoundaryTest.php
│  │  ├─ ClientRuntimeSurfaceTest.php
│  │  ├─ CompanyWorkforceTest.php
│  │  ├─ DashboardDisplayStateTest.php
│  │  ├─ DeveloperApiContractTest.php
│  │  ├─ DeveloperApiDocumentationContractTest.php
│  │  ├─ DeveloperApiIsolationTest.php
│  │  ├─ EmailTemplateCoreFlowsTest.php
│  │  ├─ EmbeddedSignupPlanBackfillTest.php
│  │  ├─ EmbeddedSignupRoutesTest.php
│  │  ├─ EncryptAiApiKeysMigrationTest.php
│  │  ├─ EncryptGlobalAiKeySettingMigrationTest.php
│  │  ├─ FlowBuilderPlanBackfillTest.php
│  │  ├─ FrontendContactDetailsTest.php
│  │  ├─ FrontendPremiumHomeMediaTest.php
│  │  ├─ FrontendPricingLocalizationTest.php
│  │  ├─ FrontendPublicPayloadTest.php
│  │  ├─ FrontendSeoMetaTagsTest.php
│  │  ├─ FrontendSitemapTest.php
│  │  ├─ HealthEndpointsTest.php
│  │  ├─ InertiaAssetVersionTest.php
│  │  ├─ InertiaComponentContractTest.php
│  │  ├─ IntelliReplyChatSuggestionTest.php
│  │  ├─ IntelliReplyEndpointGateTest.php
│  │  ├─ IntelliReplyRoutesTest.php
│  │  ├─ LocaleEndpointsTest.php
│  │  ├─ LogoutRouteContractTest.php
│  │  ├─ MoyasarCheckoutPresentationTest.php
│  │  ├─ MoyasarInvoiceMetadataFallbackTest.php
│  │  ├─ MoyasarPaymentCallbackFallbackTest.php
│  │  ├─ MoyasarPaymentGatewayBackfillTest.php
│  │  ├─ NoDebugTerminatorsInProductionCodeTest.php
│  │  ├─ NoFrontendDebugArtifactsTest.php
│  │  ├─ OrganizationApiKeyHardeningTest.php
│  │  ├─ OrganizationBranchSubscriptionInheritanceTest.php
│  │  ├─ OrganizationDefaultRoleProvisioningTest.php
│  │  ├─ OrganizationRoleCountScopeTest.php
│  │  ├─ OrganizationSessionBoundaryTest.php
│  │  ├─ OrganizationSubscriptionSourceOfTruthTest.php
│  │  ├─ PaymentProcessorAllowListTest.php
│  │  ├─ ProvisionRealEstateFlowTemplatesCommandTest.php
│  │  ├─ PublicLegalAndLoginUxTest.php
│  │  ├─ PublicRuntimeSurfaceHardeningTest.php
│  │  ├─ RemainingSecurityHardeningTest.php
│  │  ├─ RouteContractSnapshotTest.php
│  │  ├─ SaClientSystemPreparationTest.php
│  │  ├─ ScaleHardeningPhase3Test.php
│  │  ├─ SocialAuthOnboardingTest.php
│  │  ├─ SubscriptionBillingInvoiceTotalsTest.php
│  │  ├─ SubscriptionCouponPreviewStateTest.php
│  │  ├─ SubscriptionCouponRouteContractTest.php
│  │  ├─ SubscriptionPlanChangePolicyTest.php
│  │  ├─ SystemBillingIntegrityAuditCommandTest.php
│  │  ├─ SystemCoreDataCommandsTest.php
│  │  ├─ SystemDocsConsistencyCheckCommandTest.php
│  │  ├─ SystemMetaReviewTestCommandTest.php
│  │  ├─ SystemPrepareTestingDatabaseCommandTest.php
│  │  ├─ SystemQueueProfileCommandTest.php
│  │  ├─ SystemReadinessScoreCommandTest.php
│  │  ├─ SystemRiskReportCommandTest.php
│  │  ├─ SystemSignupBillingAuditCommandTest.php
│  │  ├─ TeamRoleScopeEnforcementTest.php
│  │  ├─ UiOptionsLocalizationAuditTest.php
│  │  ├─ UiTranslationAuditTest.php
│  │  ├─ UiTranslationNoTransHelperInUiScopeTest.php
│  │  ├─ UnsafeFilePatternTest.php
│  │  ├─ UserAiAssistantSetupTest.php
│  │  ├─ UserAiKeyPolicyTest.php
│  │  ├─ UserAutomationSettingsSequenceTest.php
│  │  ├─ UserBillingWorkspaceTest.php
│  │  ├─ UserDashboardWhatsappFallbackTest.php
│  │  ├─ UserSeatEnforcementTest.php
│  │  ├─ UserSettingsFeaturePayloadTest.php
│  │  ├─ UserSettingsPermissionBoundaryTest.php
│  │  ├─ UserWhatsappManualFallbackTest.php
│  │  ├─ UserWhatsappModeSwitchTest.php
│  │  ├─ UserWhatsappRefreshRouteContractTest.php
│  │  ├─ WebChatSendPermissionTest.php
│  │  ├─ WebWorkspaceIsolationHardeningTest.php
│  │  └─ WhatsappWebhookSignatureTest.php
│  ├─ Playwright
│  │  └─ flow-builder-canvas.spec.js
│  ├─ TestCase.php
│  └─ Unit
│     ├─ AiKeyResolverTest.php
│     ├─ AiUsageLimiterServiceTest.php
│     ├─ AutomationFlowNodeCatalogTest.php
│     ├─ BasicUnitTest.php
│     ├─ BroadcastGuardTest.php
│     ├─ ContactServiceBulkAssignGroupTest.php
│     ├─ ContactsImportLimitTest.php
│     ├─ NewChatEventTest.php
│     ├─ OrganizationUsageSummaryServiceTest.php
│     ├─ QueueProfileServiceTest.php
│     ├─ ReadinessAssessmentServiceTest.php
│     ├─ SaClientPlanProfileTest.php
│     ├─ SubscriptionFeatureUsageServiceTest.php
│     ├─ TrialAddonEntitlementServiceTest.php
│     ├─ WhatsappAccountInspectionServiceTest.php
│     ├─ WhatsappServiceTest.php
│     ├─ WhatsappTemplateReadinessServiceTest.php
│     ├─ WhatsappTemplateRequestGuardServiceTest.php
│     └─ WhatsappTokenVaultTest.php
└─ vite.config.js

```#   b o t z o  
 