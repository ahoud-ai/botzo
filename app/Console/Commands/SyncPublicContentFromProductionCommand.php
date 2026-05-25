<?php

namespace App\Console\Commands;

use App\Models\Addon;
use App\Models\Faq;
use App\Models\Page;
use App\Models\Review;
use App\Models\Setting;
use App\Models\SubscriptionPlan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SyncPublicContentFromProductionCommand extends Command
{
    protected $signature = 'botzo:sync-public-content {--source=https://botzo.net/}';

    protected $description = 'Sync local public website content from the deployed Botzo website.';

    public function handle(): int
    {
        $source = rtrim((string) $this->option('source'), '/') . '/';

        $this->info("Reading public payload from {$source}");

        $payload = $this->fetchInertiaPayload($source);
        $props = $payload['props'] ?? [];

        DB::transaction(function () use ($props, $source) {
            $this->syncSettings($props);
            $this->syncReviews($props['reviews'] ?? []);
            $this->syncFaqs($props['faqs']['data'] ?? []);
            $this->syncPages($props['pages'] ?? [], $source);
            $this->syncPlans($props['plans'] ?? []);
            $this->syncAddons(array_keys((array) ($props['addons'] ?? [])));
        });

        $mediaPaths = $this->collectMediaPaths($props);
        $downloaded = $this->downloadMedia($source, $mediaPaths);

        $hotFile = public_path('hot');
        if (is_file($hotFile)) {
            unlink($hotFile);
            $this->line('Removed public/hot so Laravel uses the production build assets.');
        }

        $this->call('optimize:clear');

        $this->info('Public content sync finished.');
        $this->line('Media files checked: ' . count($mediaPaths) . '; downloaded: ' . $downloaded);

        return self::SUCCESS;
    }

    private function fetchInertiaPayload(string $url): array
    {
        $response = Http::withoutVerifying()->timeout(30)->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException("Unable to fetch {$url}: HTTP {$response->status()}");
        }

        $html = $response->body();

        if (! preg_match('/<div[^>]+id=["\']app["\'][^>]+data-page=["\']([^"\']+)["\']/i', $html, $matches)) {
            throw new \RuntimeException("Unable to find Inertia data-page payload in {$url}");
        }

        $json = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $payload = json_decode($json, true);

        if (! is_array($payload)) {
            throw new \RuntimeException("Unable to decode Inertia payload from {$url}: " . json_last_error_msg());
        }

        return $payload;
    }

    private function syncSettings(array $props): void
    {
        $settings = [];

        foreach ((array) ($props['config'] ?? []) as $item) {
            if (isset($item['key'])) {
                $settings[$item['key']] = $item['value'] ?? null;
            }
        }

        foreach ((array) ($props['companyConfig'] ?? []) as $key => $value) {
            $settings[$key] = $value;
        }

        foreach ((array) ($props['premiumHomeMedia'] ?? []) as $key => $value) {
            $settings[$key] = $value;
        }

        $settings['default_language'] = $props['currentLanguage'] ?? 'ar';
        $settings['display_frontend'] = '1';
        $settings['frontend_variant'] = 'premium';

        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(['key' => (string) $key], ['value' => $value]);
        }

        $this->line('Synced settings: ' . count($settings));
    }

    private function syncReviews(array $reviews): void
    {
        if ($reviews === []) {
            return;
        }

        Review::query()->delete();

        foreach ($reviews as $review) {
            Review::query()->create($this->onlyExistingColumns('reviews', [
                'id' => $review['id'] ?? null,
                'name' => $review['name'] ?? null,
                'name_ar' => $review['name'] ?? null,
                'position' => $review['position'] ?? null,
                'position_ar' => $review['position'] ?? null,
                'review' => $review['review'] ?? null,
                'review_ar' => $review['review'] ?? null,
                'rating' => $review['rating'] ?? 5,
                'image' => $review['image'] ?? null,
                'status' => $review['status'] ?? 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        $this->line('Synced reviews: ' . count($reviews));
    }

    private function syncFaqs(array $faqs): void
    {
        if ($faqs === []) {
            return;
        }

        Faq::query()->delete();

        foreach ($faqs as $faq) {
            Faq::query()->create($this->onlyExistingColumns('faqs', [
                'id' => $faq['id'] ?? null,
                'question' => $faq['question'] ?? $faq['question_ar'] ?? $faq['question_en'] ?? null,
                'question_ar' => $faq['question_ar'] ?? $faq['question'] ?? null,
                'question_en' => $faq['question_en'] ?? $faq['question'] ?? null,
                'answer' => $faq['answer'] ?? $faq['answer_ar'] ?? $faq['answer_en'] ?? null,
                'answer_ar' => $faq['answer_ar'] ?? $faq['answer'] ?? null,
                'answer_en' => $faq['answer_en'] ?? $faq['answer'] ?? null,
                'status' => $faq['status'] ?? '1',
            ]));
        }

        $this->line('Synced FAQs: ' . count($faqs));
    }

    private function syncPages(array $pages, string $source): void
    {
        if ($pages === []) {
            return;
        }

        Page::query()->delete();

        foreach ($pages as $page) {
            $details = $this->fetchPageDetails($source, (string) ($page['slug'] ?? ''));

            Page::query()->create($this->onlyExistingColumns('pages', [
                'id' => $page['id'] ?? null,
                'name' => $details['name'] ?? $page['name'] ?? $page['display_name'] ?? null,
                'name_ar' => $details['display_name'] ?? $page['display_name'] ?? $page['name'] ?? null,
                'name_en' => $details['name'] ?? $page['name'] ?? null,
                'content' => $details['content'] ?? null,
                'content_ar' => $details['localized_content'] ?? $details['content'] ?? null,
                'content_en' => $details['content'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        $this->line('Synced pages: ' . count($pages));
    }

    private function fetchPageDetails(string $source, string $slug): array
    {
        if ($slug === '') {
            return [];
        }

        try {
            $encodedSlug = implode('/', array_map('rawurlencode', explode('/', ltrim($slug, '/'))));
            $payload = $this->fetchInertiaPayload($source . $encodedSlug);

            return (array) ($payload['props']['page'] ?? []);
        } catch (\Throwable $e) {
            $this->warn("Could not sync page details for {$slug}: {$e->getMessage()}");

            return [];
        }
    }

    private function syncPlans(array $plans): void
    {
        foreach ($plans as $plan) {
            if (! isset($plan['uuid'])) {
                continue;
            }

            SubscriptionPlan::withTrashed()->updateOrCreate(
                ['uuid' => $plan['uuid']],
                $this->onlyExistingColumns('subscription_plans', [
                    'name' => $plan['name'] ?? null,
                    'name_ar' => $plan['name_ar'] ?? $plan['name'] ?? null,
                    'name_en' => $plan['name_en'] ?? $plan['name'] ?? null,
                    'price' => $plan['price'] ?? 0,
                    'period' => $plan['period'] ?? 'monthly',
                    'metadata' => is_array($plan['metadata'] ?? null)
                        ? json_encode($plan['metadata'], JSON_UNESCAPED_UNICODE)
                        : ($plan['metadata'] ?? '{}'),
                    'status' => 'active',
                    'deleted_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $this->line('Synced plans: ' . count($plans));
    }

    private function syncAddons(array $addonNames): void
    {
        foreach ($addonNames as $name) {
            Addon::query()->updateOrCreate(
                ['name' => $name],
                $this->onlyExistingColumns('addons', [
                    'name' => $name,
                    'category' => 'Public website',
                    'logo' => 'default.svg',
                    'description' => null,
                    'metadata' => '{}',
                    'status' => 1,
                    'is_plan_restricted' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $this->line('Synced plan-restricted addons: ' . count($addonNames));
    }

    private function collectMediaPaths(array $value): array
    {
        $paths = [];
        $walker = function ($item) use (&$walker, &$paths): void {
            if (is_array($item)) {
                foreach ($item as $nested) {
                    $walker($nested);
                }

                return;
            }

            if (! is_string($item)) {
                return;
            }

            if (preg_match_all('#(?:/media/)?(public/[A-Za-z0-9._/\-]+\.(?:png|jpe?g|webp|gif|svg))#i', $item, $matches)) {
                foreach ($matches[1] as $path) {
                    $paths[] = $path;
                }
            }
        };

        $walker($value);

        return array_values(array_unique($paths));
    }

    private function downloadMedia(string $source, array $paths): int
    {
        $downloaded = 0;

        foreach ($paths as $path) {
            $storagePath = Str::after($path, 'public/');

            if (Storage::disk('public')->exists($storagePath)) {
                continue;
            }

            $url = rtrim($source, '/') . '/media/' . ltrim($path, '/');
            $response = Http::withoutVerifying()->timeout(30)->get($url);

            if (! $response->successful()) {
                $this->warn("Could not download {$url}: HTTP {$response->status()}");
                continue;
            }

            Storage::disk('public')->put($storagePath, $response->body());
            $downloaded++;
        }

        return $downloaded;
    }

    private function onlyExistingColumns(string $table, array $values): array
    {
        return array_filter(
            $values,
            static fn ($value, $key) => Schema::hasColumn($table, (string) $key),
            ARRAY_FILTER_USE_BOTH
        );
    }
}
