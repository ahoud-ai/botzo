<template>
    <AppLayout>
        <UiPageHeader :title="$t('SEO and tracking settings')" :subtitle="$t('Manage indexing, search appearance, social sharing cards, and tracking tags for the public frontend')">
            <template #actions>
                <Link href="/admin/settings/frontend" class="seo-btn seo-btn--ghost">{{ $t('Back to frontend management') }}</Link>
            </template>
        </UiPageHeader>

        <div class="seo-banner mt-6">
            <p class="min-w-0 flex-1">{{ $t('These settings apply to the public website pages only. Dashboard and admin pages are excluded from indexing automatically.') }}</p>
            <div class="flex flex-shrink-0 items-center gap-2">
                <a href="/robots.txt" target="_blank" class="seo-banner-link">{{ $t('View robots.txt') }}</a>
                <a href="/sitemap.xml" target="_blank" class="seo-banner-link">{{ $t('View sitemap.xml') }}</a>
            </div>
        </div>

        <form class="mt-6 space-y-6" @submit.prevent="submitForm">
            <UiSectionCard :title="$t('Search appearance')" :subtitle="$t('Define the default title and description shown in search engine results.')">
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
            </UiSectionCard>

            <UiSectionCard :title="$t('Indexing and robots')" :subtitle="$t('Control whether search engines are allowed to index public pages and follow links.')">
                <div class="space-y-4">
                    <div class="seo-toggle-row">
                        <div class="min-w-0">
                            <p class="seo-toggle-title">{{ $t('Allow indexing') }}</p>
                            <p class="seo-toggle-copy">{{ $t('When disabled, robots.txt will block all crawlers from indexing the site.') }}</p>
                        </div>
                        <FormToggleSwitch v-model="form.seo_robots_index" />
                    </div>

                    <div class="seo-toggle-row">
                        <div class="min-w-0">
                            <p class="seo-toggle-title">{{ $t('Allow link following') }}</p>
                            <p class="seo-toggle-copy">{{ $t('Adds follow or nofollow to the robots meta tag on public pages.') }}</p>
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
            </UiSectionCard>

            <UiSectionCard :title="$t('Social sharing cards')" :subtitle="$t('Control Open Graph and Twitter card content shown when links are shared.')">
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
            </UiSectionCard>

            <UiSectionCard :title="$t('Verification tags')" :subtitle="$t('Add verification tokens for search engines and webmaster tools.')">
                <div class="grid gap-5 lg:grid-cols-2">
                    <FormInput v-model="form.seo_google_verification" :name="$t('Google site verification token')" :error="form.errors.seo_google_verification" :type="'text'" />
                    <FormInput v-model="form.seo_bing_verification" :name="$t('Bing site verification token')" :error="form.errors.seo_bing_verification" :type="'text'" />
                </div>
            </UiSectionCard>

            <UiSectionCard :title="$t('Tracking tags')" :subtitle="$t('Set analytics and marketing pixels that should load on the public frontend.')">
                <p class="seo-note">
                    {{ $t('Only valid tracking IDs are saved and loaded on public pages.') }}
                    {{ $t('Accepted formats: numeric Meta pixel ID and TikTok pixel ID.') }}
                </p>

                <div class="mt-4 grid gap-5 lg:grid-cols-2">
                    <FormInput v-model="form.tracking_meta_pixel_id" :name="$t('Meta pixel ID')" :error="form.errors.tracking_meta_pixel_id" :type="'text'" />
                    <FormInput v-model="form.tracking_tiktok_pixel_id" :name="$t('TikTok pixel ID')" :error="form.errors.tracking_tiktok_pixel_id" :type="'text'" />
                </div>

                <div class="mt-5 grid gap-5">
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
            </UiSectionCard>

            <div class="seo-save-bar">
                <span class="seo-save-hint">{{ form.isDirty ? $t('You have unsaved changes') : $t('All changes saved') }}</span>
                <div class="flex items-center gap-x-3">
                    <Link href="/admin/settings/frontend" class="seo-btn seo-btn--ghost">{{ $t('Back') }}</Link>
                    <button type="submit" class="seo-btn seo-btn--solid" :disabled="form.processing">
                        <span v-if="form.processing">{{ $t('Saving...') }}</span>
                        <span v-else>{{ $t('Save') }}</span>
                    </button>
                </div>
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
    import UiPageHeader from '@/Components/UI/UiPageHeader.vue';
    import UiSectionCard from '@/Components/UI/UiSectionCard.vue';
    import AppLayout from '../Layout/App.vue';
    import { useUnsavedChangesGuard } from '@/Composables/useUnsavedChangesGuard';

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

    const { markSubmitting } = useUnsavedChangesGuard(() => form.isDirty, t('You have unsaved changes. Leave this page?'));

    const setShareImage = (value) => {
        form.seo_share_image = value;
        form.remove_seo_share_image = false;
    };

    const clearShareImage = () => {
        form.seo_share_image = null;
        form.remove_seo_share_image = true;
    };

    const submitForm = () => {
        markSubmitting();
        form.post('/admin/settings?type=frontend-seo', {
            preserveScroll: true,
        });
    };
</script>

<style scoped>
.seo-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.85rem;
    padding: 0.6rem 1.2rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: filter 160ms ease, background-color 160ms ease, border-color 160ms ease, opacity 160ms ease;
}

.seo-btn--solid {
    background: var(--ui-secondary);
    color: #fff;
    box-shadow: var(--ui-shadow-1);
}

.seo-btn--solid:hover:not(:disabled) {
    filter: brightness(1.05);
}

.seo-btn--solid:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}

.seo-btn--ghost {
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    color: var(--ui-text);
}

.seo-btn--ghost:hover {
    background: var(--ui-surface-soft);
    border-color: var(--ui-border-strong);
}

.seo-banner {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    border-radius: 1rem;
    border: 1px solid var(--ui-border);
    background: color-mix(in srgb, var(--ui-secondary) 6%, var(--ui-surface));
    padding: 1rem 1.25rem;
    font-size: 0.83rem;
    color: var(--ui-text);
}

.seo-banner-link {
    border-radius: 0.6rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    padding: 0.35rem 0.7rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--ui-secondary);
    transition: background-color 160ms ease;
}

.seo-banner-link:hover {
    background: var(--ui-surface-soft);
}

.seo-toggle-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    border-radius: 0.9rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface-soft);
    padding: 0.85rem 1.05rem;
}

.seo-toggle-title {
    font-size: 0.86rem;
    font-weight: 700;
    color: var(--ui-text);
}

.seo-toggle-copy {
    margin-top: 0.15rem;
    font-size: 0.78rem;
    color: var(--ui-muted);
}

.seo-note {
    font-size: 0.78rem;
    line-height: 1.5;
    color: var(--ui-muted);
}

.seo-save-bar {
    position: sticky;
    bottom: 0.75rem;
    z-index: 10;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.85rem;
    border-radius: 1rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    padding: 1rem 1.25rem;
    box-shadow: var(--ui-shadow-2);
}

.seo-save-hint {
    font-size: 0.8rem;
    color: var(--ui-muted);
}
</style>
