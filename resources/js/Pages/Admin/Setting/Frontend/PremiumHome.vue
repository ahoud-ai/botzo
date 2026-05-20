<template>
    <AppLayout>
        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="mb-1 text-xl">{{ $t('Premium homepage images') }}</h2>
                <p class="flex items-center text-sm leading-6 text-gray-600">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24">
                        <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/>
                    </svg>
                    <span class="ms-1 mt-1">{{ $t('Manage the Premium homepage visuals from one place without touching the classic frontend') }}</span>
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
                <div>
                    <p class="font-semibold">{{ $t('This section only affects the Premium homepage') }}</p>
                    <p>{{ $t('If no custom image is uploaded, the built-in design image stays active automatically.') }}</p>
                </div>
                <div class="rounded-lg bg-white px-4 py-3 shadow-sm">
                    <p class="text-xs text-slate-500">{{ $t('Active frontend variant') }}</p>
                    <p class="text-sm font-semibold text-slate-900">{{ frontendVariantLabel }}</p>
                </div>
            </div>
        </div>

        <form class="space-y-8" @submit.prevent="submitForm">
            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="mb-5 border-b border-gray-100 pb-4">
                    <h3 class="text-base font-semibold text-gray-900">{{ $t('Hero visuals') }}</h3>
                    <p class="mt-1 text-sm text-gray-600">{{ $t('These images appear in the top hero collage and the main product preview.') }}</p>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <FormImageAsset
                        v-for="field in heroFields"
                        :key="field.key"
                        :model-value="form[field.key]"
                        :input-id="`premium-home-${field.key}`"
                        :class-name="field.className"
                        :name="field.name"
                        :description="field.description"
                        :error="form.errors[field.key] || form.errors[`remove_${field.key}`]"
                        :image-url="getStoredImageUrl(field.key)"
                        :fallback-image-url="field.fallbackImageUrl"
                        :preview-class="field.previewClass"
                        @update:model-value="(value) => setFieldValue(field.key, value)"
                        @clear="markForReset(field.key)"
                    />
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="mb-5 border-b border-gray-100 pb-4">
                    <h3 class="text-base font-semibold text-gray-900">{{ $t('Feature section visuals') }}</h3>
                    <p class="mt-1 text-sm text-gray-600">{{ $t('Control the screenshot shown in the feature highlight section.') }}</p>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <FormImageAsset
                        v-for="field in featureFields"
                        :key="field.key"
                        :model-value="form[field.key]"
                        :input-id="`premium-home-${field.key}`"
                        :class-name="field.className"
                        :name="field.name"
                        :description="field.description"
                        :error="form.errors[field.key] || form.errors[`remove_${field.key}`]"
                        :image-url="getStoredImageUrl(field.key)"
                        :fallback-image-url="field.fallbackImageUrl"
                        :preview-class="field.previewClass"
                        @update:model-value="(value) => setFieldValue(field.key, value)"
                        @clear="markForReset(field.key)"
                    />
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="mb-5 border-b border-gray-100 pb-4">
                    <h3 class="text-base font-semibold text-gray-900">{{ $t('How it works arrows') }}</h3>
                    <p class="mt-1 text-sm text-gray-600">{{ $t('Replace the decorative arrows used in the onboarding steps section.') }}</p>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <FormImageAsset
                        v-for="field in stepFields"
                        :key="field.key"
                        :model-value="form[field.key]"
                        :input-id="`premium-home-${field.key}`"
                        :class-name="field.className"
                        :name="field.name"
                        :description="field.description"
                        :error="form.errors[field.key] || form.errors[`remove_${field.key}`]"
                        :image-url="getStoredImageUrl(field.key)"
                        :fallback-image-url="field.fallbackImageUrl"
                        :preview-class="field.previewClass"
                        @update:model-value="(value) => setFieldValue(field.key, value)"
                        @clear="markForReset(field.key)"
                    />
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="mb-5 border-b border-gray-100 pb-4">
                    <h3 class="text-base font-semibold text-gray-900">{{ $t('Premium footer payment methods') }}</h3>
                    <p class="mt-1 text-sm text-gray-600">{{ $t('Control the payment-methods strip shown in the Premium footer.') }}</p>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <FormImageAsset
                        v-for="field in footerFields"
                        :key="field.key"
                        :model-value="form[field.key]"
                        :input-id="`premium-home-${field.key}`"
                        :class-name="field.className"
                        :name="field.name"
                        :description="field.description"
                        :error="form.errors[field.key] || form.errors[`remove_${field.key}`]"
                        :image-url="getStoredImageUrl(field.key)"
                        :fallback-image-url="field.fallbackImageUrl"
                        :preview-class="field.previewClass"
                        @update:model-value="(value) => setFieldValue(field.key, value)"
                        @clear="markForReset(field.key)"
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

    const heroFields = [
        {
            key: 'premium_home_hero_background',
            name: t('Hero background image'),
            description: t('Main background image of the Premium hero section behind the headline and CTA buttons.'),
            fallbackImageUrl: '/images/hero/hero-background-default.svg',
            previewClass: 'aspect-[21/9] w-full',
            className: 'lg:col-span-2',
        },
        {
            key: 'premium_home_cta_background',
            name: t('CTA section background image'),
            description: t('Background image used in the final call-to-action section ("Ready to get started?") of the Premium homepage.'),
            fallbackImageUrl: '/images/hero/hero-background-default.svg',
            previewClass: 'aspect-[21/9] w-full',
            className: 'lg:col-span-2',
        },
        {
            key: 'premium_home_hero_avatar_top_right',
            name: t('Hero avatar (top right)'),
            description: t('Floating profile image shown on the top-right side of the Premium hero section.'),
            fallbackImageUrl: '/images/hero/user-6.png',
            previewClass: 'aspect-[4/3] w-full',
        },
        {
            key: 'premium_home_hero_avatar_top_left',
            name: t('Hero avatar (top left)'),
            description: t('Floating profile image shown on the top-left side of the Premium hero section.'),
            fallbackImageUrl: '/images/hero/user-3.png',
            previewClass: 'aspect-[4/3] w-full',
        },
        {
            key: 'premium_home_hero_avatar_bottom_left',
            name: t('Hero avatar (bottom left)'),
            description: t('Floating profile image shown on the lower-left side of the Premium hero section.'),
            fallbackImageUrl: '/images/hero/user-4.png',
            previewClass: 'aspect-[4/3] w-full',
        },
        {
            key: 'premium_home_hero_avatar_bottom_right',
            name: t('Hero avatar (bottom right)'),
            description: t('Floating profile image shown on the lower-right side of the Premium hero section.'),
            fallbackImageUrl: '/images/hero/user-2.png',
            previewClass: 'aspect-[4/3] w-full',
        },
        {
            key: 'premium_home_hero_dashboard',
            name: t('Hero dashboard preview'),
            description: t('Primary dashboard screenshot displayed inside the main hero mockup.'),
            fallbackImageUrl: '/images/hero/dashboard2.png',
            previewClass: 'aspect-[16/10] w-full',
            className: 'lg:col-span-2',
        },
    ];

    const featureFields = [
        {
            key: 'premium_home_feature_dashboard',
            name: t('Feature dashboard preview'),
            description: t('Secondary product screenshot displayed in the Premium features section.'),
            fallbackImageUrl: '/images/hero/dashboard3.png',
            previewClass: 'aspect-[16/10] w-full',
        },
    ];

    const stepFields = [
        {
            key: 'premium_home_steps_arrow_right',
            name: t('Steps arrow (right)'),
            description: t('Decorative arrow between the first and second steps in the how-it-works section.'),
            fallbackImageUrl: '/images/shapes/stepArrow1.png',
            previewClass: 'aspect-[3/1] w-full',
        },
        {
            key: 'premium_home_steps_arrow_left',
            name: t('Steps arrow (left)'),
            description: t('Decorative arrow between the second and third steps in the how-it-works section.'),
            fallbackImageUrl: '/images/shapes/stepArrow2.png',
            previewClass: 'aspect-[3/1] w-full',
        },
    ];

    const footerFields = [
        {
            key: 'premium_home_footer_payment_methods',
            name: t('Payment methods strip'),
            description: t('Image shown in the Premium footer under the "We offer multiple payment methods" text.'),
            fallbackImageUrl: '/images/defaults/payment-methods-default.svg',
            previewClass: 'aspect-[21/6] w-full',
            className: 'lg:col-span-2',
        },
    ];

    const allFields = [...heroFields, ...featureFields, ...stepFields, ...footerFields];
    const initialForm = allFields.reduce((accumulator, field) => {
        accumulator[field.key] = null;
        accumulator[`remove_${field.key}`] = false;

        return accumulator;
    }, {
        _method: 'put',
    });

    const form = useForm(initialForm);

    const frontendVariantLabel = computed(() => {
        return getValueByKey('frontend_variant') === 'premium'
            ? t('Premium Frontend')
            : t('Classic Frontend');
    });

    const setFieldValue = (key, value) => {
        form[key] = value;

        if (value) {
            form[`remove_${key}`] = false;
        }
    };

    const markForReset = (key) => {
        form[key] = null;
        form[`remove_${key}`] = true;
    };

    const submitForm = () => {
        form.post('/admin/settings?type=premium-home-media', {
            preserveScroll: true,
        });
    };
</script>
