<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\FaqResource;
use App\Http\Resources\PageResource;
use App\Jobs\ProcessCampaignMessagesJob;
use App\Jobs\SendCampaignMessageJob;
use App\Models\Addon;
use App\Models\Campaign;
use App\Models\CampaignLog;
use App\Models\Faq;
use App\Models\Page;
use App\Models\Review;
use App\Models\Setting;
use App\Models\SubscriptionPlan;
use App\Support\SaClientPlanProfile;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;

class FrontendController extends BaseController
{
    public function index(Request $request)
    {
        $frontendActive = (bool) (Setting::where('key', 'display_frontend')->value('value') ?? 1);

        if (! $frontendActive) {
            return Inertia::render('Auth/Login', [
                'companyConfig' => $this->frontendCompanyConfig([
                    'allow_facebook_login',
                    'allow_google_login',
                    'primary_color',
                    'secondary_color',
                ]),
            ]);
        }

        $component = $this->resolveFrontendComponent('Index');
        $data = $this->basePublicData();
        $data['reviews'] = Review::where('status', 1)
            ->get()
            ->map(static function (Review $review) {
                return [
                    'id' => $review->id,
                    'name' => $review->localizedName(),
                    'position' => $review->localizedPosition(),
                    'review' => $review->localizedReview(),
                    'rating' => $review->rating,
                    'image' => $review->image,
                    'status' => $review->status,
                ];
            })
            ->values();
        $data['plans'] = $this->frontendPricingPlans();
        $data['faqs'] = FaqResource::collection(Faq::where('status', '1')->get());
        $data['currency'] = $this->frontendCurrency();
        $data['addons'] = $this->planRestrictedAddonCatalog();
        $data['enable_ai_billing'] = Setting::where('key', 'enable_ai_billing')->value('value') ?? 0;

        if ($component === 'FrontendPremium/Index') {
            $data['premiumHomeMedia'] = $this->premiumHomeMediaSettings();
        }

        return Inertia::render($component, $data);
    }

    public function pages(Request $request, $slug)
    {
        $page = Page::get()->first(static function (Page $item) use ($slug) {
            return $item->matchesSlug((string) $slug);
        });

        if (! $page) {
            abort(404);
        }

        return Inertia::render($this->resolveFrontendComponent('Dynamic'), [
            ...$this->basePublicData(),
            'page' => new PageResource($page),
        ]);
    }

    public function pricing(Request $request)
    {
        return Inertia::render($this->resolveFrontendComponent('Pricing'), [
            ...$this->basePublicData(),
            'plans' => $this->frontendPricingPlans(),
            'faqs' => FaqResource::collection(Faq::where('status', '1')->get()),
            'currency' => $this->frontendCurrency(),
            'addons' => $this->planRestrictedAddonCatalog(),
            'enable_ai_billing' => Setting::where('key', 'enable_ai_billing')->value('value') ?? 0,
        ]);
    }

    public function contact(Request $request)
    {
        if ($request->isMethod('post')) {
            return Redirect::to('/contact')->with('status', [
                'type' => 'success',
                'message' => __('Your request has been received. The team will contact you soon.'),
            ]);
        }

        return Inertia::render($this->resolveFrontendComponent('Contact'), $this->basePublicData());
    }

    public function product(Request $request)
    {
        $component = $this->resolveFrontendComponent('Product');
        $data = $this->basePublicData();

        if ($component === 'FrontendPremium/Product') {
            $data['premiumHomeMedia'] = $this->premiumHomeMediaSettings();
        }

        return Inertia::render($component, $data);
    }

    public function apiDocumentation(Request $request)
    {
        return Inertia::render($this->resolveFrontendComponent('ApiDocumentation'), [
            ...$this->basePublicData(),
            'apirequests' => config('apiguide'),
        ]);
    }

    public function faqs(Request $request)
    {
        return Inertia::render($this->resolveFrontendComponent('Faqs'), [
            ...$this->basePublicData(),
            'faqs' => FaqResource::collection(Faq::where('status', '1')->get()),
        ]);
    }

    public function cookiePolicy(Request $request)
    {
        return Inertia::render($this->resolveFrontendComponent('CookiePolicy'), $this->basePublicData());
    }

    public function privacy(Request $request)
    {
        return $this->renderLegalPage(
            $request,
            ['privacy-policy', 'privacy'],
            __('Privacy Policy'),
            $this->fallbackPrivacyPolicyContent()
        );
    }

    public function termsOfService(Request $request)
    {
        return $this->renderLegalPage(
            $request,
            ['terms-of-service', 'terms', 'terms-and-conditions'],
            __('Terms of Service'),
            $this->fallbackTermsOfServiceContent()
        );
    }

    private function renderLegalPage(Request $request, array $slugCandidates, string $title, string $fallbackContent)
    {
        $pages = Page::get();
        $page = collect($slugCandidates)
            ->map(static fn (string $slug) => $pages->first(static fn (Page $item) => $item->matchesSlug($slug)))
            ->first();

        if ($page) {
            return Inertia::render($this->resolveFrontendComponent('Dynamic'), [
                ...$this->basePublicData(),
                'page' => new PageResource($page),
            ]);
        }

        return Inertia::render($this->resolveFrontendComponent('Dynamic'), [
            ...$this->basePublicData(),
            'page' => [
                'id' => null,
                'name' => $title,
                'display_name' => $title,
                'slug' => $slugCandidates[0] ?? null,
                'content' => $fallbackContent,
                'localized_content' => $fallbackContent,
            ],
        ]);
    }

    private function fallbackPrivacyPolicyContent(): string
    {
        if (app()->getLocale() === 'ar') {
            return implode('', [
                '<p>توضح سياسة الخصوصية هذه كيفية تعامل المنصة مع البيانات اللازمة لتقديم خدمات التواصل عبر واتساب.</p>',
                '<h2>البيانات التي نعالجها</h2>',
                '<p>قد نعالج بيانات الحساب، بيانات الفوترة، إعدادات واتساب، وجهات الاتصال والرسائل التي يديرها المستخدم داخل المنصة.</p>',
                '<h2>الغرض من المعالجة</h2>',
                '<p>تستخدم البيانات لتشغيل الخدمة، إدارة الاشتراكات، تحسين الأمان، وتقديم الدعم الفني.</p>',
                '<h2>التواصل</h2>',
                '<p>يمكنك التواصل معنا من صفحة التواصل لأي طلب يتعلق بالخصوصية.</p>',
            ]);
        }

        return implode('', [
            '<p>This Privacy Policy explains how the platform handles data needed to provide WhatsApp communication services.</p>',
            '<h2>Data we process</h2>',
            '<p>We may process account details, billing data, WhatsApp settings, contacts, and messages managed by users inside the platform.</p>',
            '<h2>Purpose of processing</h2>',
            '<p>Data is used to operate the service, manage subscriptions, improve security, and provide support.</p>',
            '<h2>Contact</h2>',
            '<p>Please contact us through the contact page for privacy-related requests.</p>',
        ]);
    }

    private function fallbackTermsOfServiceContent(): string
    {
        if (app()->getLocale() === 'ar') {
            return implode('', [
                '<p>توضح شروط الخدمة هذه قواعد استخدام الموقع وخدمات التواصل المرتبطة به عبر واتساب.</p>',
                '<h2>استخدام الخدمة</h2>',
                '<p>أنت مسؤول عن استخدام الخدمة بطريقة نظامية، حماية حسابك، والالتزام بسياسات واتساب والأنظمة ذات العلاقة.</p>',
                '<h2>الاشتراكات والمدفوعات</h2>',
                '<p>قد يتطلب الوصول إلى بعض المزايا اشتراكًا نشطًا. تظهر شروط الاشتراك والتجديد أثناء الدفع أو داخل حسابك.</p>',
                '<h2>الاستخدام المقبول</h2>',
                '<p>يمنع استخدام الخدمة في الرسائل المزعجة أو المحتوى المخالف أو أي نشاط قد يضر بالمنصة أو المستخدمين.</p>',
                '<h2>التواصل</h2>',
                '<p>لأي استفسار عن هذه الشروط، يمكنك التواصل معنا من صفحة التواصل.</p>',
            ]);
        }

        return implode('', [
            '<p>These Terms of Service describe the rules for using this website and the related WhatsApp communication services.</p>',
            '<h2>Use of the service</h2>',
            '<p>You are responsible for using the service lawfully, keeping your account secure, and complying with WhatsApp policies and applicable laws.</p>',
            '<h2>Subscriptions and payments</h2>',
            '<p>Access to paid features may require an active subscription. Subscription terms and renewal rules are shown during checkout or inside your account.</p>',
            '<h2>Acceptable use</h2>',
            '<p>You must not use the service for spam, abusive messaging, unlawful content, or activity that could harm the platform or users.</p>',
            '<h2>Contact</h2>',
            '<p>If you have questions about these terms, please contact us through the contact page.</p>',
        ]);
    }

    private function resolveFrontendComponent(string $page): string
    {
        $variant = strtolower((string) (Setting::where('key', 'frontend_variant')->value('value') ?? 'premium'));

        if (! in_array($variant, ['classic', 'premium'], true)) {
            $variant = 'premium';
        }

        if ($variant === 'premium') {
            $premiumPath = resource_path("js/Pages/FrontendPremium/{$page}.vue");
            if (is_file($premiumPath)) {
                return "FrontendPremium/{$page}";
            }
        }

        return "Frontend/{$page}";
    }

    private function basePublicData(): array
    {
        return [
            'companyConfig' => $this->frontendCompanyConfig(),
            'pages' => $this->frontendNavigationPages(),
        ];
    }

    private function premiumHomeMediaSettings(): array
    {
        $keys = config('frontend.premium_home_media_keys', []);

        if ($keys === []) {
            return [];
        }

        return Setting::whereIn('key', $keys)->pluck('value', 'key')->toArray();
    }

    private function frontendNavigationPages(): array
    {
        return Page::query()
            ->orderBy('id')
            ->get(['id', 'name', 'name_ar', 'name_en'])
            ->map(static function (Page $page) {
                return [
                    'id' => $page->id,
                    'name' => $page->name,
                    'display_name' => $page->display_name,
                    'slug' => $page->slug,
                ];
            })
            ->values()
            ->all();
    }

    private function frontendPricingPlans()
    {
        return SubscriptionPlan::query()
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get([
                'id',
                'uuid',
                'name',
                'name_ar',
                'name_en',
                'price',
                'period',
                'metadata',
            ]);
    }

    private function frontendCurrency(): string
    {
        return 'SAR';
    }

    private function planRestrictedAddonCatalog(): array
    {
        return Addon::query()
            ->where('status', 1)
            ->where('is_plan_restricted', 1)
            ->whereIn('name', SaClientPlanProfile::planAddonNames())
            ->get(['name'])
            ->mapWithKeys(static fn (Addon $addon) => [$addon->name => 1])
            ->all();
    }

    private function frontendCompanyConfig(array $additionalKeys = []): array
    {
        $baseKeys = [
            'logo',
            'company_name',
            'address',
            'email',
            'phone',
            'socials',
            'trial_period',
            'book_a_demo_link',
            'frontend_contact_phone_primary',
            'frontend_contact_phone_secondary',
            'frontend_contact_address_primary_ar',
            'frontend_contact_address_primary_en',
            'frontend_contact_address_secondary_ar',
            'frontend_contact_address_secondary_en',
            'frontend_contact_business_hours_primary_ar',
            'frontend_contact_business_hours_primary_en',
            'frontend_contact_business_hours_secondary_ar',
            'frontend_contact_business_hours_secondary_en',
            'premium_home_footer_payment_methods',
        ];

        $keys = array_values(array_unique(array_merge($baseKeys, $additionalKeys)));

        return Setting::whereIn('key', $keys)->pluck('value', 'key')->toArray();
    }

    public function changeLanguage($locale)
    {
        $supportedLocales = array_values(array_unique(array_map(
            static fn ($item) => strtolower((string) $item),
            config('i18n.supported_locales', ['en', 'ar'])
        )));
        $locale = strtolower((string) $locale);

        if (! in_array($locale, $supportedLocales, true)) {
            $locale = 'en';
        }

        app()->setLocale($locale);

        if (! auth()->check()) {
            session()->put('locale', $locale);
        }

        return redirect()->back();
    }

    public function campaignSend()
    {
        try {
            $this->markCompletedCampaigns();

            app(ProcessCampaignMessagesJob::class)->handle();
            $this->processPendingCampaignMessages(50);
            $this->processPendingCampaignMessages(50);
            $this->processPendingCampaignMessages(50);

            $this->markCompletedCampaigns();

            return response()->json(['status' => 'success']);
        } catch (\Throwable $e) {
            Log::error('Campaign dispatch endpoint failed.', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => __('Request unable to be processed'),
            ], 500);
        }
    }

    private function processPendingCampaignMessages($limit = 50)
    {
        $processedCount = 0;
        $now = Carbon::now('UTC');

        $pendingLogs = CampaignLog::with(['campaign', 'contact'])
            ->whereHas('campaign', static function ($query) {
                $query->where('status', 'ongoing')
                    ->whereNull('deleted_at');
            })
            ->whereIn('status', ['pending', 'retrying'])
            ->where(static function ($query) use ($now) {
                $query->whereNull('scheduled_at')
                    ->orWhereRaw('scheduled_at <= ?', [$now->toDateTimeString()]);
            })
            ->orderByRaw('COALESCE(scheduled_at, "1970-01-01 00:00:00") ASC')
            ->limit($limit)
            ->get();

        foreach ($pendingLogs as $log) {
            $rawScheduledAt = $log->getAttributes()['scheduled_at'] ?? null;

            if ($rawScheduledAt !== null && Carbon::parse($rawScheduledAt, 'UTC')->gt($now)) {
                continue;
            }

            try {
                $log->refresh(['campaign', 'contact']);

                if (! in_array($log->status, ['pending', 'retrying'], true) || $log->campaign->status !== 'ongoing') {
                    continue;
                }

                (new SendCampaignMessageJob($log->id))->handle();
                $log->refresh();

                if (in_array($log->status, ['success', 'failed'], true)) {
                    $processedCount++;
                }
            } catch (\Throwable $e) {
                Log::warning('Synchronous campaign message processing failed.', [
                    'campaign_log_id' => $log->id,
                    'campaign_id' => $log->campaign_id,
                    'message' => $e->getMessage(),
                    'exception' => get_class($e),
                ]);

                try {
                    $failedLog = CampaignLog::find($log->id);
                    if ($failedLog && in_array($failedLog->status, ['pending', 'retrying'], true)) {
                        $failedLog->status = 'failed';
                        $failedLog->metadata = json_encode([
                            'error' => $e->getMessage(),
                            'failed_at' => now()->toDateTimeString(),
                            'sync_processing_error' => true,
                        ]);
                        $failedLog->save();
                    }
                } catch (\Throwable $saveError) {
                    Log::warning('Unable to persist failed campaign log state during synchronous dispatch.', [
                        'campaign_log_id' => $log->id,
                        'message' => $saveError->getMessage(),
                        'exception' => get_class($saveError),
                    ]);
                }
            }
        }

        return $processedCount;
    }

    private function markCompletedCampaigns()
    {
        $ongoingCampaignIds = Campaign::where('status', 'ongoing')->pluck('id')->toArray();

        if ($ongoingCampaignIds === []) {
            return [];
        }

        $campaignsWithPending = CampaignLog::whereIn('campaign_id', $ongoingCampaignIds)
            ->whereIn('status', ['pending', 'ongoing', 'retrying'])
            ->distinct()
            ->pluck('campaign_id')
            ->toArray();

        $campaignsToComplete = array_diff($ongoingCampaignIds, $campaignsWithPending);

        if ($campaignsToComplete === []) {
            return [];
        }

        Campaign::whereIn('id', $campaignsToComplete)
            ->where('status', 'ongoing')
            ->update(['status' => 'completed']);

        return $campaignsToComplete;
    }
}
