<template>
    <AppLayout>
        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="mb-1 text-xl">{{ $t('SEO and tracking settings') }}</h2>
                <p class="flex items-center text-sm leading-6 text-gray-600">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24">
                        <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/>
                    </svg>
                    <span class="ms-1 mt-1">{{ $t('Manage indexing, search appearance, social sharing cards, and tracking tags for the public frontend') }}</span>
                </p>
            </div>

            <Link
                href="/admin/settings/frontend"
                class="inline-flex items-center rounded-md border border-primary px-3 py-2 text-sm text-primary transition hover:bg-primary hover:text-white"
            >
                {{ $t('Back to frontend management') }}
            </Link>
        </div>

        <div class="mb-6 rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm leading-6 text-sky-900">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p>{{ $t('These settings apply to the public website pages only. Dashboard and admin pages are excluded from indexing automatically.') }}</p>
                <div class="flex items-center gap-2 text-xs">
                    <a href="/robots.txt" target="_blank" class="rounded border border-sky-300 bg-white px-3 py-1 text-sky-800 hover:bg-sky-100">{{ $t('View robots.txt') }}</a>
                    <a href="/sitemap.xml" target="_blank" class="rounded border border-sky-300 bg-white px-3 py-1 text-sky-800 hover:bg-sky-100">{{ $t('View sitemap.xml') }}</a>
                </div>
            </div>
        </div>

        <form class="space-y-8" @submit.prevent="submitForm">
            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="mb-5 border-b border-gray-100 pb-4">
                    <h3 class="text-base font-semibold text-gray-900">{{ $t('Search appearance') }}</h3>
                    <p class="mt-1 text-sm text-gray-600">{{ $t('Define the default title and description shown in search engine results.') }}</p>
                </div>

                <div class="grid gap-5 lg:grid-cols-2">
                    <FormInput v-model="form.seo_site_name_ar" :name="$t('Site name (Arabic)')" :error="form.errors.seo_site_name_ar" :type="'text'" />
                    <FormInput v-model="form.seo_site_name_en" :name="$t('Site name (English)')" :error="form.errors.seo_site_name_en" :type="'text'" />

                    <FormInput v-model="form.seo_home_title_ar" :name="$t('Homepage title (Arabic)')" :error="form.errors.seo_home_title_ar" :type="'text'" />
                    <FormInput v-model="form.seo_home_title_en" :name="$t('Homepage title (English)')" :error="form.errors.seo_home_title_en" :type="'text'" />

                    <FormTextArea v-model="form.seo_home_description_ar" :name="$t('Homepage description (Arabic)')" :error="form.errors.seo_home_description_ar" :textAreaRows="4" />
                    <FormTextArea v-model="form.seo_home_description_en" :name="$t('Homepage description (English)')" :error="form.errors.seo_home_description_en" :textAreaRows="4" />

                    <FormInput v-model="form.seo_default_title_ar" :name="$t('Default pages title (Arabic)')" :error="form.errors.seo_default_title_ar" :type="'text'" />
                    <FormInput v-model="form.seo_default_title_en" :name="$t('Default pages title (English)')" :error="form.errors.seo_default_title_en" :type="'text'" />

                    <FormTextArea v-model="form.seo_default_description_ar" :name="$t('Default pages description (Arabic)')" :error="form.errors.seo_default_description_ar" :textAreaRows="4" />
                    <FormTextArea v-model="form.seo_default_description_en" :name="$t('Default pages description (English)')" :error="form.errors.seo_default_description_en" :textAreaRows="4" />

                    <FormTextArea v-model="form.seo_keywords_ar" :name="$t('Keywords (Arabic)')" :error="form.errors.seo_keywords_ar" :textAreaRows="3" />
                    <FormTextArea v-model="form.seo_keywords_en" :name="$t('Keywords (English)')" :error="form.errors.seo_keywords_en" :textAreaRows="3" />

                    <FormInput v-model="form.seo_canonical_base_url" :name="$t('Canonical base URL')" :error="form.errors.seo_canonical_base_url" :type="'url'" :placeholder="'https://example.com'" :class="'lg:col-span-2'" />
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="mb-5 border-b border-gray-100 pb-4">
                    <h3 class="text-base font-semibold text-gray-900">{{ $t('Indexing and robots') }}</h3>
                    <p class="mt-1 text-sm text-gray-600">{{ $t('Control whether search engines are allowed to index public pages and follow links.') }}</p>
                </div>

                <div class="space-y-4">
                    <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ $t('Allow indexing') }}</p>
                            <p class="text-xs text-gray-600">{{ $t('When disabled, robots.txt will block all crawlers from indexing the site.') }}</p>
                        </div>
                        <FormToggleSwitch v-model="form.seo_robots_index" />
                    </div>

                    <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ $t('Allow link following') }}</p>
                            <p class="text-xs text-gray-600">{{ $t('Adds follow or nofollow to the robots meta tag on public pages.') }}</p>
                        </div>
                        <FormToggleSwitch v-model="form.seo_robots_follow" />
                    </div>

                    <FormTextArea
                        v-model="form.seo_robots_custom"
                        :name="$t('Custom robots.txt content (optional)')"
                        :error="form.errors.seo_robots_custom"
                        :textAreaRows="6"
                        :showLabel="true"
                    />
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="mb-5 border-b border-gray-100 pb-4">
                    <h3 class="text-base font-semibold text-gray-900">{{ $t('Social sharing cards') }}</h3>
                    <p class="mt-1 text-sm text-gray-600">{{ $t('Control Open Graph and Twitter card content shown when links are shared.') }}</p>
                </div>

                <div class="grid gap-5 lg:grid-cols-2">
                    <FormInput v-model="form.seo_og_title_ar" :name="$t('Open Graph title (Arabic)')" :error="form.errors.seo_og_title_ar" :type="'text'" />
                    <FormInput v-model="form.seo_og_title_en" :name="$t('Open Graph title (English)')" :error="form.errors.seo_og_title_en" :type="'text'" />

                    <FormTextArea v-model="form.seo_og_description_ar" :name="$t('Open Graph description (Arabic)')" :error="form.errors.seo_og_description_ar" :textAreaRows="4" />
                    <FormTextArea v-model="form.seo_og_description_en" :name="$t('Open Graph description (English)')" :error="form.errors.seo_og_description_en" :textAreaRows="4" />

                    <FormSelect
                        v-model="form.seo_twitter_card"
                        :name="$t('Twitter card type')"
                        :options="twitterCardOptions"
                        :placeholder="$t('Select option')"
                        :error="form.errors.seo_twitter_card"
                        :class-name="'lg:col-span-1'"
                    />
                    <FormInput v-model="form.seo_twitter_site" :name="$t('Twitter username')" :error="form.errors.seo_twitter_site" :type="'text'" :placeholder="'@yourbrand'" />

                    <div class="lg:col-span-2">
                        <FormImageAsset
                            :model-value="form.seo_share_image"
                            :input-id="'seo-share-image'"
                            :name="$t('Default share image')"
                            :description="$t('Used in Open Graph and Twitter cards when no page-specific image exists.')"
                            :error="form.errors.seo_share_image || form.errors.remove_seo_share_image"
                            :image-url="getStoredImageUrl('seo_share_image')"
                            :fallback-image-url="shareImageFallback"
                            :preview-class="'aspect-[16/9] w-full'"
                            @update:model-value="(value) => setShareImage(value)"
                            @clear="clearShareImage"
                        />
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="mb-5 border-b border-gray-100 pb-4">
                    <h3 class="text-base font-semibold text-gray-900">{{ $t('Verification tags') }}</h3>
                    <p class="mt-1 text-sm text-gray-600">{{ $t('Add verification tokens for search engines and webmaster tools.') }}</p>
                </div>

                <div class="grid gap-5 lg:grid-cols-2">
                    <FormInput v-model="form.seo_google_verification" :name="$t('Google site verification token')" :error="form.errors.seo_google_verification" :type="'text'" />
                    <FormInput v-model="form.seo_bing_verification" :name="$t('Bing site verification token')" :error="form.errors.seo_bing_verification" :type="'text'" />
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="mb-5 border-b border-gray-100 pb-4">
                    <h3 class="text-base font-semibold text-gray-900">{{ $t('Tracking tags') }}</h3>
                    <p class="mt-1 text-sm text-gray-600">{{ $t('Set analytics and marketing pixels that should load on the public frontend.') }}</p>
                    <p class="mt-2 text-xs leading-5 text-gray-500">
                        {{ $t('Only valid tracking IDs are saved and loaded on public pages.') }}
                        {{ $t('Accepted formats: numeric Meta pixel ID and TikTok pixel ID.') }}
                    </p>
                </div>

                <div class="grid gap-5 lg:grid-cols-2">
                    <FormInput v-model="form.tracking_meta_pixel_id" :name="$t('Meta pixel ID')" :error="form.errors.tracking_meta_pixel_id" :type="'text'" />
                    <FormInput v-model="form.tracking_tiktok_pixel_id" :name="$t('TikTok pixel ID')" :error="form.errors.tracking_tiktok_pixel_id" :type="'text'" />
                </div>

                <div class="mt-5 grid gap-5 lg:grid-cols-1">
                    <FormTextArea
                        v-model="form.head_scripts"
                        :name="$t('Additional head scripts')"
                        :error="form.errors.head_scripts"
                        :textAreaRows="6"
                    />
                    <FormTextArea
                        v-model="form.body_scripts"
                        :name="$t('Additional body scripts')"
                        :error="form.errors.body_scripts"
                        :textAreaRows="6"
                    />
                    <FormTextArea
                        v-model="form.meta_tags"
                        :name="$t('Additional meta tags')"
                        :error="form.errors.meta_tags"
                        :textAreaRows="4"
                    />
                </div>
            </section>

            <div class="flex items-center justify-end gap-x-3">
                <Link
                    href="/admin/settings/frontend"
                    class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 transition hover:border-gray-400 hover:text-gray-900"
                >
                    {{ $t('Back') }}
                </Link>
                <button
                    type="submit"
                    class="inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm text-white transition hover:opacity-90"
                    :disabled="form.processing"
                >
                    <span v-if="form.processing">{{ $t('Saving...') }}</span>
                    <span v-else>{{ $t('Save') }}</span>
                </button>
            </div>
        </form>
    </AppLayout>
</template>

<script setup>
    import { computed } from 'vue';
    import { Link, useForm } from '@inertiajs/vue3';
    import { useI18n } from 'vue-i18n';
    import FormImageAsset from '@/Components/FormImageAsset.vue';
    import FormInput from '@/Components/FormInput.vue';
    import FormSelect from '@/Components/FormSelect.vue';
    import FormTextArea from '@/Components/FormTextArea.vue';
    import FormToggleSwitch from '@/Components/FormToggleSwitch.vue';
    import AppLayout from '../Layout/App.vue';

    const { t } = useI18n();

    const props = defineProps({
        config: {
            type: Array,
            required: true,
        },
    });

    const getValueByKey = (key) => {
        const found = props.config.find((item) => item.key === key);

        return found ? found.value : '';
    };

    const getStoredImageUrl = (key) => {
        const file = getValueByKey(key);

        return file ? `/media/${file}` : null;
    };

    const parseBoolean = (value, fallback = true) => {
        if (value === '' || value === null || value === undefined) {
            return fallback;
        }

        return ['1', 'true', 'yes', 'on'].includes(String(value).toLowerCase());
    };

    const twitterCardOptions = [
        { label: t('Summary'), value: 'summary' },
        { label: t('Summary large image'), value: 'summary_large_image' },
    ];

    const shareImageFallback = computed(() => getStoredImageUrl('logo') || '/images/favicon.png');

    const form = useForm({
        _method: 'put',
        seo_site_name_ar: getValueByKey('seo_site_name_ar'),
        seo_site_name_en: getValueByKey('seo_site_name_en'),
        seo_home_title_ar: getValueByKey('seo_home_title_ar'),
        seo_home_title_en: getValueByKey('seo_home_title_en'),
        seo_default_title_ar: getValueByKey('seo_default_title_ar'),
        seo_default_title_en: getValueByKey('seo_default_title_en'),
        seo_home_description_ar: getValueByKey('seo_home_description_ar'),
        seo_home_description_en: getValueByKey('seo_home_description_en'),
        seo_default_description_ar: getValueByKey('seo_default_description_ar'),
        seo_default_description_en: getValueByKey('seo_default_description_en'),
        seo_keywords_ar: getValueByKey('seo_keywords_ar'),
        seo_keywords_en: getValueByKey('seo_keywords_en'),
        seo_og_title_ar: getValueByKey('seo_og_title_ar'),
        seo_og_title_en: getValueByKey('seo_og_title_en'),
        seo_og_description_ar: getValueByKey('seo_og_description_ar'),
        seo_og_description_en: getValueByKey('seo_og_description_en'),
        seo_twitter_card: getValueByKey('seo_twitter_card') || 'summary_large_image',
        seo_twitter_site: getValueByKey('seo_twitter_site'),
        seo_canonical_base_url: getValueByKey('seo_canonical_base_url'),
        seo_google_verification: getValueByKey('seo_google_verification'),
        seo_bing_verification: getValueByKey('seo_bing_verification'),
        seo_robots_index: parseBoolean(getValueByKey('seo_robots_index'), true),
        seo_robots_follow: parseBoolean(getValueByKey('seo_robots_follow'), true),
        seo_robots_custom: getValueByKey('seo_robots_custom'),
        tracking_meta_pixel_id: getValueByKey('tracking_meta_pixel_id'),
        tracking_tiktok_pixel_id: getValueByKey('tracking_tiktok_pixel_id'),
        head_scripts: getValueByKey('head_scripts'),
        body_scripts: getValueByKey('body_scripts'),
        meta_tags: getValueByKey('meta_tags'),
        seo_share_image: null,
        remove_seo_share_image: false,
    });

    const setShareImage = (value) => {
        form.seo_share_image = value;
        form.remove_seo_share_image = false;
    };

    const clearShareImage = () => {
        form.seo_share_image = null;
        form.remove_seo_share_image = true;
    };

    const submitForm = () => {
        form.post('/admin/settings?type=frontend-seo', {
            preserveScroll: true,
        });
    };
</script>
