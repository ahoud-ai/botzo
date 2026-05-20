<template>
    <AppLayout>
        <div class="mb-6">
            <h2 class="mb-1 text-xl">{{ $t('Frontend Management') }}</h2>
            <p class="flex items-center text-sm leading-6 text-gray-600">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24">
                    <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/>
                </svg>
                <span class="ms-1 mt-1">{{ $t('Manage homepage visibility and visitor experience from one place') }}</span>
            </p>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h3 class="text-base font-semibold text-gray-900">{{ $t('Content pages') }}</h3>
                <p class="mt-2 text-sm text-gray-600">{{ $t('Edit bilingual public pages using the advanced editor') }}</p>
                <div class="mt-4">
                    <Link href="/admin/settings/pages" class="inline-flex items-center rounded-md bg-primary px-3 py-2 text-sm text-white">
                        {{ $t('Open pages manager') }}
                    </Link>
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h3 class="text-base font-semibold text-gray-900">{{ $t('Brand and appearance') }}</h3>
                <p class="mt-2 text-sm text-gray-600">{{ $t('Control logo, favicon, and active frontend variant') }}</p>
                <div class="mt-4">
                    <Link href="/admin/settings/general" class="inline-flex items-center rounded-md border border-primary px-3 py-2 text-sm text-primary">
                        {{ $t('Open general frontend settings') }}
                    </Link>
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h3 class="text-base font-semibold text-gray-900">{{ $t('Frontend contact details') }}</h3>
                <p class="mt-2 text-sm text-gray-600">{{ $t('Manage bilingual addresses and up to two mobile numbers shown in the public footer and contact page') }}</p>
                <div class="mt-4">
                    <Link href="/admin/settings/frontend/contact-details" class="inline-flex items-center rounded-md bg-primary px-3 py-2 text-sm text-white">
                        {{ $t('Open frontend contact details') }}
                    </Link>
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h3 class="text-base font-semibold text-gray-900">{{ $t('Premium homepage images') }}</h3>
                <p class="mt-2 text-sm text-gray-600">{{ $t('Manage Premium homepage visuals with safe fallbacks to the built-in design assets') }}</p>
                <div class="mt-4">
                    <Link href="/admin/settings/frontend/premium-home" class="inline-flex items-center rounded-md bg-primary px-3 py-2 text-sm text-white">
                        {{ $t('Open premium homepage images') }}
                    </Link>
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h3 class="text-base font-semibold text-gray-900">{{ $t('SEO and tracking') }}</h3>
                <p class="mt-2 text-sm text-gray-600">{{ $t('Control indexing, search snippets, social sharing cards, and marketing tracking tags from one place') }}</p>
                <div class="mt-4">
                    <Link href="/admin/settings/frontend/seo" class="inline-flex items-center rounded-md bg-primary px-3 py-2 text-sm text-white">
                        {{ $t('Open SEO and tracking settings') }}
                    </Link>
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-gray-50 p-5 shadow-sm md:col-span-2">
                <h3 class="text-base font-semibold text-gray-900">{{ $t('Frontend status') }}</h3>
                <div class="mt-3 grid gap-3 text-sm md:grid-cols-2">
                    <div class="rounded-lg border border-gray-200 bg-white p-3">
                        <p class="text-gray-500">{{ $t('Default public frontend') }}</p>
                        <p class="mt-1 font-semibold text-gray-900">{{ frontendVariantLabel }}</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-white p-3">
                        <p class="text-gray-500">{{ $t('Status') }}</p>
                        <p class="mt-1 font-semibold" :class="isFrontendVisible ? 'text-emerald-600' : 'text-amber-700'">
                            {{ isFrontendVisible ? $t('Show') : $t('Hidden') }}
                        </p>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

<script setup>
    import { computed } from 'vue';
    import { Link } from '@inertiajs/vue3';
    import { useI18n } from 'vue-i18n';
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

    const frontendVariantLabel = computed(() => {
        const variant = getValueByKey('frontend_variant');

        return variant === 'premium' ? t('Premium Frontend') : t('Classic Frontend');
    });

    const isFrontendVisible = computed(() => {
        const value = getValueByKey('display_frontend');

        return value === '' || value === null || value === '1' || value === 1 || value === true || value === 'true';
    });
</script>
