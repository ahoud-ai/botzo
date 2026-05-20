<template>
    <AppLayout>
        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="mb-1 text-xl">{{ $t('Frontend contact details') }}</h2>
                <p class="flex items-center text-sm leading-6 text-gray-600">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24">
                        <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/>
                    </svg>
                    <span class="ms-1 mt-1">{{ $t('Manage footer and contact-page addresses and phone numbers for both Classic and Premium frontends') }}</span>
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
                    <p class="font-semibold">{{ $t('These contact details are shared by Classic and Premium frontends') }}</p>
                    <p>{{ $t('Addresses and business hours automatically switch by language (Arabic/English) on the public website') }}</p>
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
                    <h3 class="text-base font-semibold text-gray-900">{{ $t('Phone numbers') }}</h3>
                    <p class="mt-1 text-sm text-gray-600">{{ $t('Add up to two phone numbers for the public contact blocks') }}</p>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <FormInput
                        v-model="form.frontend_contact_phone_primary"
                        :name="$t('Mobile number 1')"
                        :type="'text'"
                        :error="form.errors.frontend_contact_phone_primary"
                    />
                    <FormInput
                        v-model="form.frontend_contact_phone_secondary"
                        :name="$t('Mobile number 2')"
                        :type="'text'"
                        :error="form.errors.frontend_contact_phone_secondary"
                    />
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="mb-5 border-b border-gray-100 pb-4">
                    <h3 class="text-base font-semibold text-gray-900">{{ $t('Addresses') }}</h3>
                    <p class="mt-1 text-sm text-gray-600">{{ $t('Each address supports Arabic and English to match the active site language') }}</p>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <FormTextArea
                        v-model="form.frontend_contact_address_primary_ar"
                        :name="$t('Address 1 (Arabic)')"
                        :showLabel="true"
                        :textAreaRows="4"
                        :error="form.errors.frontend_contact_address_primary_ar"
                    />
                    <FormTextArea
                        v-model="form.frontend_contact_address_primary_en"
                        :name="$t('Address 1 (English)')"
                        :showLabel="true"
                        :textAreaRows="4"
                        :error="form.errors.frontend_contact_address_primary_en"
                    />
                    <FormTextArea
                        v-model="form.frontend_contact_address_secondary_ar"
                        :name="$t('Address 2 (Arabic)')"
                        :showLabel="true"
                        :textAreaRows="4"
                        :error="form.errors.frontend_contact_address_secondary_ar"
                    />
                    <FormTextArea
                        v-model="form.frontend_contact_address_secondary_en"
                        :name="$t('Address 2 (English)')"
                        :showLabel="true"
                        :textAreaRows="4"
                        :error="form.errors.frontend_contact_address_secondary_en"
                    />
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="mb-5 border-b border-gray-100 pb-4">
                    <h3 class="text-base font-semibold text-gray-900">{{ $t('Business hours') }}</h3>
                    <p class="mt-1 text-sm text-gray-600">{{ $t('Add up to two localized business-hours lines for the public contact page') }}</p>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <FormInput
                        v-model="form.frontend_contact_business_hours_primary_ar"
                        :name="$t('Business hours line 1 (Arabic)')"
                        :type="'text'"
                        :error="form.errors.frontend_contact_business_hours_primary_ar"
                    />
                    <FormInput
                        v-model="form.frontend_contact_business_hours_primary_en"
                        :name="$t('Business hours line 1 (English)')"
                        :type="'text'"
                        :error="form.errors.frontend_contact_business_hours_primary_en"
                    />
                    <FormInput
                        v-model="form.frontend_contact_business_hours_secondary_ar"
                        :name="$t('Business hours line 2 (Arabic)')"
                        :type="'text'"
                        :error="form.errors.frontend_contact_business_hours_secondary_ar"
                    />
                    <FormInput
                        v-model="form.frontend_contact_business_hours_secondary_en"
                        :name="$t('Business hours line 2 (English)')"
                        :type="'text'"
                        :error="form.errors.frontend_contact_business_hours_secondary_en"
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
    import FormInput from '@/Components/FormInput.vue';
    import FormTextArea from '@/Components/FormTextArea.vue';
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

    const form = useForm({
        _method: 'put',
        frontend_contact_phone_primary: getValueByKey('frontend_contact_phone_primary') || getValueByKey('phone'),
        frontend_contact_phone_secondary: getValueByKey('frontend_contact_phone_secondary'),
        frontend_contact_address_primary_ar: getValueByKey('frontend_contact_address_primary_ar'),
        frontend_contact_address_primary_en: getValueByKey('frontend_contact_address_primary_en'),
        frontend_contact_address_secondary_ar: getValueByKey('frontend_contact_address_secondary_ar'),
        frontend_contact_address_secondary_en: getValueByKey('frontend_contact_address_secondary_en'),
        frontend_contact_business_hours_primary_ar: getValueByKey('frontend_contact_business_hours_primary_ar'),
        frontend_contact_business_hours_primary_en: getValueByKey('frontend_contact_business_hours_primary_en'),
        frontend_contact_business_hours_secondary_ar: getValueByKey('frontend_contact_business_hours_secondary_ar'),
        frontend_contact_business_hours_secondary_en: getValueByKey('frontend_contact_business_hours_secondary_en'),
    });

    const frontendVariantLabel = computed(() => {
        return getValueByKey('frontend_variant') === 'premium'
            ? t('Premium Frontend')
            : t('Classic Frontend');
    });

    const submitForm = () => {
        form.post('/admin/settings?type=frontend-contact', {
            preserveScroll: true,
        });
    };
</script>
