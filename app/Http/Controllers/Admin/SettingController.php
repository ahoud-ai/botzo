<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\StoreConfig;
use App\Http\Resources\AddonResource;
use App\Models\Addon;
use App\Models\Setting;
use App\Modules\Platform\Application\Environment\DemoModeService;
use App\Services\EmbeddedSignup\EmbeddedSignupReviewTestService;
use App\Services\SettingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;

class SettingController extends BaseController
{
    private $settingService;

    public function __construct(SettingService $settingService)
    {
        $this->settingService = $settingService;
    }

    public function index()
    {
        return redirect('/admin/settings/general');
    }

    public function update(StoreConfig $request)
    {
        if (app(DemoModeService::class)->enabled()) {
            // Return a response indicating that the function is not allowed in demo environment
            return Redirect::back()->with(
                'status', [
                    'type' => 'error',
                    'message' => __('Updating settings is not allowed in demo.'),
                ]
            );
        }

        $settings = $this->settingService->updateSettings($request);

        return Redirect::back()->with(
            'status', [
                'type' => 'success',
                'message' => __('You\'ve updated your settings successfully'),
            ]
        );
    }

    public function general(Request $request)
    {
        $data['config'] = Setting::get();

        return Inertia::render('Admin/Setting/General', $data);
    }

    public function frontend(Request $request)
    {
        $data['config'] = Setting::get();

        return Inertia::render('Admin/Setting/Frontend/Index', $data);
    }

    public function frontendContact(Request $request)
    {
        $data['config'] = Setting::get();

        return Inertia::render('Admin/Setting/Frontend/ContactDetails', $data);
    }

    public function premiumHomeMedia(Request $request)
    {
        $data['config'] = Setting::get();

        return Inertia::render('Admin/Setting/Frontend/PremiumHome', $data);
    }

    public function frontendSeo(Request $request)
    {
        $data['config'] = Setting::get();

        return Inertia::render('Admin/Setting/Frontend/Seo', $data);
    }

    public function email(Request $request)
    {
        $data['config'] = Setting::get();

        return Inertia::render('Admin/Setting/Email', $data);
    }

    public function billing(Request $request)
    {
        $data['config'] = Setting::get();

        return Inertia::render('Admin/Setting/Billing', $data);
    }

    public function broadcast_driver(Request $request)
    {
        $data['config'] = Setting::get();

        return Inertia::render('Admin/Setting/Broadcast', $data);
    }

    public function seo(Request $request)
    {
        $data['config'] = Setting::get();

        return Inertia::render('Admin/Setting/Frontend/Seo', $data);
    }

    public function subscription(Request $request)
    {
        $data['config'] = Setting::get();

        return Inertia::render('Admin/Setting/Subscription', $data);
    }

    public function embeddedSignup(Request $request)
    {
        $data = $this->featureViewData('Embedded Signup');

        return Inertia::render('Admin/Setting/EmbeddedSignup', $data);
    }

    public function features(Request $request)
    {
        return redirect('/admin/settings/features/embedded-signup');
    }

    public function aiAssistant(Request $request)
    {
        return Inertia::render('Admin/Setting/Features/AiAssistant', $this->featureViewData('AI Assistant'));
    }

    public function flowBuilder(Request $request)
    {
        return Inertia::render('Admin/Setting/Features/FlowBuilder', $this->featureViewData('Flow builder'));
    }

    public function embeddedSignupMetaReviewTests(Request $request, EmbeddedSignupReviewTestService $reviewTestService)
    {
        $requestedTests = $request->input('tests', []);
        if (! is_array($requestedTests)) {
            $requestedTests = [$requestedTests];
        }

        return response()->json($reviewTestService->buildReport($requestedTests));
    }

    public function timezone(Request $request)
    {
        $data['timezones'] = config('formats.timezones');
        $data['date_formats'] = config('formats.date_formats');
        $data['time_formats'] = config('formats.time_formats');
        $data['currencies'] = config('currencies');
        $data['config'] = Setting::get();

        return Inertia::render('Admin/Setting/Timezone', $data);
    }

    public function storage(Request $request)
    {
        $data['config'] = Setting::get();

        return Inertia::render('Admin/Setting/Storage', $data);
    }

    public function socials(Request $request)
    {
        $data['config'] = Setting::get();

        return Inertia::render('Admin/Setting/Socials', $data);
    }

    private function featureViewData(string $featureName): array
    {
        $addon = $this->resolveFeatureAddon($featureName);

        return [
            'config' => Setting::get(),
            'addon' => (new AddonResource($addon))->resolve(request()),
        ];
    }

    private function resolveFeatureAddon(string $featureName): Addon
    {
        $defaults = [
            'Embedded Signup' => [
                'category' => 'whatsapp',
                'logo' => 'whatsapp.png',
                'description' => __('Embedded Signup'),
                'metadata' => json_encode(['name' => 'EmbeddedSignup']),
            ],
            'AI Assistant' => [
                'category' => 'ai',
                'logo' => 'ai.png',
                'description' => __('AI Assistant'),
                'metadata' => json_encode(['name' => 'IntelliReply']),
            ],
            'Flow builder' => [
                'category' => 'automation',
                'logo' => 'flow_icon.png',
                'description' => __('Flow builder'),
                'metadata' => json_encode(['name' => 'FlowBuilder']),
            ],
        ];

        return Addon::query()->firstOrCreate(
            ['name' => $featureName],
            array_merge([
                'status' => 1,
                'is_active' => 0,
                'is_plan_restricted' => 1,
            ], $defaults[$featureName] ?? [
                'category' => 'system',
                'logo' => '',
                'description' => $featureName,
                'metadata' => json_encode(['name' => $featureName]),
            ])
        );
    }
}
