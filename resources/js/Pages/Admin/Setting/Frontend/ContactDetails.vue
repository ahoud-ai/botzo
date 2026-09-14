<template>
    <AppLayout>
        <UiPageHeader :title="$t('Frontend contact details')" :subtitle="$t('Manage footer and contact-page addresses and phone numbers for both Classic and Premium frontends')">
            <template #actions>
                <Link href="/admin/settings/frontend" class="cd-btn cd-btn--ghost">{{ $t('Back to frontend management') }}</Link>
            </template>
        </UiPageHeader>

        <div class="cd-banner mt-6">
            <div class="min-w-0">
                <p class="cd-banner-title">{{ $t('These contact details are shared by Classic and Premium frontends') }}</p>
                <p class="cd-banner-copy">{{ $t('Addresses and business hours automatically switch by language (Arabic/English) on the public website') }}</p>
            </div>
            <div class="cd-banner-tile">
                <p class="cd-banner-tile-label">{{ $t('Active frontend variant') }}</p>
                <p class="cd-banner-tile-value">{{ frontendVariantLabel }}</p>
            </div>
        </div>

        <form class="mt-6 space-y-6" @submit.prevent="submitForm">
            <UiSectionCard :title="$t('Phone numbers')" :subtitle="$t('Add up to two phone numbers for the public contact blocks')">
                <div class="grid gap-5 lg:grid-cols-2">
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
            </UiSectionCard>

            <UiSectionCard :title="$t('Addresses')" :subtitle="$t('Each address supports Arabic and English to match the active site language')">
                <div class="grid gap-5 lg:grid-cols-2">
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
            </UiSectionCard>

            <UiSectionCard :title="$t('Business hours')" :subtitle="$t('Add up to two localized business-hours lines for the public contact page')">
                <div class="grid gap-5 lg:grid-cols-2">
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
            </UiSectionCard>

            <div class="cd-save-bar">
                <span class="cd-save-hint">{{ form.isDirty ? $t('You have unsaved changes') : $t('All changes saved') }}</span>
                <div class="flex items-center gap-x-3">
                    <Link href="/admin/settings/frontend" class="cd-btn cd-btn--ghost">{{ $t('Back') }}</Link>
                    <button type="submit" class="cd-btn cd-btn--solid" :disabled="form.processing">
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
    import FormInput from '@/Components/FormInput.vue';
    import FormTextArea from '@/Components/FormTextArea.vue';
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

    const { markSubmitting } = useUnsavedChangesGuard(() => form.isDirty, t('You have unsaved changes. Leave this page?'));

    const frontendVariantLabel = computed(() => {
        return getValueByKey('frontend_variant') === 'premium'
            ? t('Premium Frontend')
            : t('Classic Frontend');
    });

    const submitForm = () => {
        markSubmitting();
        form.post('/admin/settings?type=frontend-contact', {
            preserveScroll: true,
        });
    };
</script>

<style scoped>
.cd-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.85rem;
    padding: 0.6rem 1.2rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: filter 160ms ease, background-color 160ms ease, border-color 160ms ease, opacity 160ms ease;
}

.cd-btn--solid {
    background: var(--ui-secondary);
    color: #fff;
    box-shadow: var(--ui-shadow-1);
}

.cd-btn--solid:hover:not(:disabled) {
    filter: brightness(1.05);
}

.cd-btn--solid:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}

.cd-btn--ghost {
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    color: var(--ui-text);
}

.cd-btn--ghost:hover {
    background: var(--ui-surface-soft);
    border-color: var(--ui-border-strong);
}

.cd-banner {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    border-radius: 1rem;
    border: 1px solid var(--ui-border);
    background: color-mix(in srgb, var(--ui-secondary) 6%, var(--ui-surface));
    padding: 1.1rem 1.3rem;
}

.cd-banner-title {
    font-size: 0.88rem;
    font-weight: 700;
    color: var(--ui-text);
}

.cd-banner-copy {
    margin-top: 0.2rem;
    font-size: 0.82rem;
    color: var(--ui-muted);
}

.cd-banner-tile {
    border-radius: 0.85rem;
    background: var(--ui-surface);
    border: 1px solid var(--ui-border);
    padding: 0.65rem 1.1rem;
}

.cd-banner-tile-label {
    font-size: 0.72rem;
    color: var(--ui-muted);
}

.cd-banner-tile-value {
    margin-top: 0.15rem;
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--ui-text);
}

.cd-save-bar {
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

.cd-save-hint {
    font-size: 0.8rem;
    color: var(--ui-muted);
}
</style>
