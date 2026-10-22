<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\WebWidget;
use App\Services\OrganizationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\IntelliReply\Models\Faq;

/**
 * Sets up the organization whose AI assistant answers visitors on botzo.net itself: its owner,
 * the top plan for a year, the assistant persona and FAQs, and an enabled website widget.
 * Safe to run again: what already exists is kept, only missing pieces are added.
 */
class SetupSiteWidgetCommand extends Command
{
    protected $signature = 'botzo:setup-site-widget
        {email : Email of the account that owns the organization (created when missing)}';

    protected $description = 'Create the Botzo organization and website widget that answer visitors on botzo.net.';

    public function handle(OrganizationService $organizations): int
    {
        $content = require database_path('data/botzo_site_assistant.php');
        $email = strtolower(trim((string) $this->argument('email')));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->components->error('Enter a valid email address.');

            return self::FAILURE;
        }

        $plan = $this->topPlan();
        if (! $plan) {
            $this->components->error('No active subscription plan found.');

            return self::FAILURE;
        }

        if (! data_get($plan->metadataArray(), 'addons.AI Assistant')) {
            $this->components->warn("The plan \"{$plan->name}\" does not include the AI Assistant add-on; the assistant will not reply until it does.");
        }

        $report = DB::transaction(function () use ($organizations, $content, $email, $plan) {
            $report = [];

            $user = User::where('email', $email)->first();
            if (! $user) {
                $user = User::create([
                    'first_name' => 'Botzo',
                    'last_name' => 'Team',
                    'email' => $email,
                    'role' => 'user',
                    // Nobody knows this password: the owner sets their own with "Forgot password".
                    'password' => Str::random(48),
                ]);
                $user->forceFill(['email_verified_at' => now()])->save();
                $report[] = 'Account created: '.$email.' (set its password with "Forgot password").';
            } else {
                $report[] = 'Account found: '.$email.'.';
            }

            $organization = Organization::where('created_by', $user->id)
                ->where('name', $content['organization_name'])
                ->whereNull('parent_organization_id')
                ->first();

            if (! $organization) {
                $organization = $organizations->createOwnedMainOrganization($user, $content['organization_name'], (int) $user->id, $plan);
                $report[] = 'Organization created: '.$organization->name.'.';
            } else {
                $report[] = 'Organization found: '.$organization->name.'.';
            }

            $report[] = $this->ensureYearSubscription($organization, $plan);
            $report[] = $this->ensureAssistant($organization, $content['persona']);
            $report[] = $this->ensureFaqs($organization, $content['faqs']);
            [$widgetLine, $widget] = $this->ensureWidget($organization, $user, $content['widget']);
            $report[] = $widgetLine;

            return ['lines' => $report, 'widget' => $widget];
        });

        foreach ($report['lines'] as $line) {
            $this->line('• '.$line);
        }

        $this->newLine();
        $this->components->info('Paste this into Admin → Settings → Frontend → SEO → Body scripts:');
        $this->line($this->snippet($report['widget']));

        return self::SUCCESS;
    }

    private function topPlan(): ?SubscriptionPlan
    {
        return SubscriptionPlan::where('status', 'active')
            ->whereNull('deleted_at')
            ->get()
            ->sortByDesc(fn (SubscriptionPlan $plan) => [$plan->tierRank(), (float) $plan->price])
            ->first();
    }

    private function ensureYearSubscription(Organization $organization, SubscriptionPlan $plan): string
    {
        $subscription = Subscription::where('organization_id', $organization->id)->latest('id')->first();

        // A new organization starts on a trial or a single period; give it the full year once.
        if ($subscription && $subscription->status === 'active' && $subscription->valid_until && now()->addMonths(11)->lt($subscription->valid_until)) {
            return 'Subscription kept: active until '.$subscription->valid_until.'.';
        }

        $values = ['plan_id' => $plan->id, 'status' => 'active', 'start_date' => now(), 'valid_until' => now()->addYear()];
        $subscription
            ? $subscription->update($values)
            : Subscription::create($values + ['organization_id' => $organization->id]);

        return 'Subscription: '.$plan->name.', active for one year.';
    }

    private function ensureAssistant(Organization $organization, array $persona): string
    {
        $metadata = json_decode((string) $organization->metadata, true) ?: [];
        $ai = (array) ($metadata['ai'] ?? []);

        $ai += [
            'platform' => 'OpenAI',
            'key_source' => 'global',
            'model' => 'gpt-4o-mini',
            'max_tokens' => 512,
            'temperature' => 0.5,
        ];
        $ai['active'] = true;
        $ai['enable_automatic_responses'] = true;
        $ai['persona'] = array_merge($persona, array_filter((array) ($ai['persona'] ?? []), fn ($value) => $value !== '' && $value !== null));

        $metadata['ai'] = $ai;
        $organization->forceFill(['metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE)])->save();

        return 'AI assistant on (Saudi dialect, Botzo key).';
    }

    private function ensureFaqs(Organization $organization, array $faqs): string
    {
        $existing = Faq::where('organization_id', $organization->id)->pluck('question')->map(fn ($question) => trim((string) $question))->all();
        $room = max(0, Faq::MAX_PER_ORGANIZATION - count($existing));
        $added = 0;

        foreach ($faqs as [$question, $answer]) {
            if ($added >= $room || in_array($question, $existing, true)) {
                continue;
            }

            Faq::create(['organization_id' => $organization->id, 'question' => $question, 'answer' => $answer]);
            $added++;
        }

        return "FAQs: {$added} added, ".count($existing).' already there.';
    }

    /**
     * @return array{0: string, 1: WebWidget}
     */
    private function ensureWidget(Organization $organization, User $user, array $settings): array
    {
        $widget = WebWidget::where('organization_id', $organization->id)->first();
        if ($widget) {
            return ['Website widget kept (existing settings unchanged).', $widget];
        }

        $widget = WebWidget::create([
            'organization_id' => $organization->id,
            'public_key' => WebWidget::generatePublicKey(),
            'is_enabled' => true,
            'allowed_domains' => $settings['domains'],
            'daily_reply_limit' => (int) $settings['daily_reply_limit'],
            'created_by' => $user->id,
            'settings' => [
                'accent' => $settings['accent'],
                'position' => $settings['position'],
                'assistant_name' => $settings['assistant_name'],
                'welcome_text' => $settings['welcome_text'],
                'suggestions' => $settings['suggestions'],
            ],
        ]);

        return ['Website widget created and turned on for '.implode(', ', $settings['domains']).'.', $widget];
    }

    /**
     * The admin "Body scripts" field only outputs content as-is when it contains "<script>",
     * so the widget is added by a small inline loader instead of a <script src> tag.
     */
    private function snippet(WebWidget $widget): string
    {
        return sprintf(
            "<script>(function(){var s=document.createElement('script');s.src='%s';s.async=true;s.setAttribute('data-key','%s');document.body.appendChild(s);})();</script>",
            url('/widget.js'),
            $widget->public_key
        );
    }
}
