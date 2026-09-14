<template>
    <AppLayout>
        <UiPageHeader :title="$t('General settings')" :subtitle="$t('Configure general account information')" />

        <form @submit.prevent="submitForm()" enctype="multipart/form-data" class="mt-6 space-y-6">
            <UiSectionCard>
                <div class="grid gap-6 sm:grid-cols-2">
                    <FormInput v-model="form.company_name" :name="$t('Organization/company name')" :error="form.errors.company_name" :type="'text'" :class="'sm:col-span-2'"/>
                    <FormInput v-model="form.email" :name="$t('Email')" :error="form.errors.email" :type="'text'" :class="'sm:col-span-2'"/>
                    <FormInput v-model="form.book_a_demo_link" :name="$t('Book a demo link')" :error="form.errors.book_a_demo_link" :type="'url'" :placeholder="'https://example.com'" :class="'sm:col-span-2'"/>
                </div>
            </UiSectionCard>

            <UiSectionCard :title="$t('Social media accounts')" :subtitle="$t('Add your social media account links')">
                <div class="grid gap-6 sm:grid-cols-2">
                    <FormInput v-model="form.socials.facebook" :name="'Facebook'" :type="'text'"/>
                    <FormInput v-model="form.socials.twitter" :name="'Twitter'" :type="'text'"/>
                    <FormInput v-model="form.socials.tiktok" :name="'TikTok'" :type="'text'"/>
                    <FormInput v-model="form.socials.snapchat" :name="'Snapchat'" :type="'text'"/>
                    <FormInput v-model="form.socials.slack" :name="'Slack'" :type="'text'"/>
                    <FormInput v-model="form.socials.linkedin" :name="'LinkedIn'" :type="'text'"/>
                </div>
            </UiSectionCard>

            <UiSectionCard :title="$t('Frontend Settings')" :subtitle="$t('Show/hide the main frontend page')">
                <div class="gs-toggle-row">
                    <div class="min-w-0 flex-1">
                        <p class="gs-toggle-title">{{ $t('Use the current default frontend') }}</p>
                        <p class="gs-toggle-hint">{{ $t('Toggle on/off to show/hide the main frontend page') }}</p>
                    </div>
                    <FormToggleSwitch v-model="form.display_frontend"/>
                </div>

                <div class="mt-6 grid gap-2 sm:grid-cols-2">
                    <FormSelect
                        v-model="form.frontend_variant"
                        :name="$t('Frontend Variant')"
                        :options="frontendVariantOptions"
                        :placeholder="$t('Select option')"
                        :error="form.errors.frontend_variant"
                        :class-name="'sm:col-span-2'"
                    />
                    <p class="gs-hint sm:col-span-2">
                        {{ $t('Choose which public-site experience to serve visitors') }}
                    </p>
                </div>
            </UiSectionCard>

            <UiSectionCard :title="$t('Site logo and favicon')" :subtitle="$t('Add your logo and favicon')">
                <div class="grid gap-6 sm:grid-cols-2">
                    <FormImageLogo v-model="form.logo" :name="$t('Site logo')" :error="form.errors.logo" :label="$t('Upload logo')" :imageUrl="getImageUrl('logo')"/>
                    <FormImageFavicon v-model="form.favicon" :name="$t('Favicon')" :error="form.errors.favicon" :label="$t('Upload logo')" :imageUrl="getImageUrl('favicon')"/>
                </div>
            </UiSectionCard>

            <div class="flex items-center justify-end">
                <button type="submit" class="gs-btn gs-btn--solid" :disabled="isLoading">
                    <svg v-if="isLoading" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".5"/><path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z"><animateTransform attributeName="transform" dur="1s" from="0 12 12" repeatCount="indefinite" to="360 12 12" type="rotate"/></path></svg>
                    <span v-else>{{ $t('Save') }}</span>
                </button>
            </div>
        </form>
    </AppLayout>
</template>
<script setup>
    import AppLayout from "./Layout/App.vue";
    import { defineProps, ref } from 'vue';
    import { useForm } from "@inertiajs/vue3";
    import { useI18n } from 'vue-i18n';
    import FormImageFavicon from '@/Components/FormImageFavicon.vue';
    import FormImageLogo from '@/Components/FormImageLogo.vue';
    import FormInput from '@/Components/FormInput.vue';
    import FormSelect from '@/Components/FormSelect.vue';
    import FormToggleSwitch from '@/Components/FormToggleSwitch.vue';
    import UiPageHeader from '@/Components/UI/UiPageHeader.vue';
    import UiSectionCard from '@/Components/UI/UiSectionCard.vue';
    const { t } = useI18n();

    const props = defineProps({
        config: {
            type: Array,
            required: true
        }
    });

    const getValueByKey = (key) => {
        const found = props.config.find(item => item.key === key);
        return found ? found.value : '';
    };

    const getImageUrl = (value) => {
        const file = getValueByKey(value);
        return file != '' && file != null ? '/media/' + file : null;
    }

    const frontendVariantOptions = [
        { label: t('Classic Frontend'), value: 'classic' },
        { label: t('Premium Frontend'), value: 'premium' },
    ];

    const normalizedFrontendVariant = (value) => ['classic', 'premium'].includes(value) ? value : 'premium';

    const socials = JSON.parse(getValueByKey('socials') || '{}');
    const isLoading = ref(false);

    const form = useForm({
        _method: 'put',
        company_name: getValueByKey('company_name'),
        email: getValueByKey('email'),
        book_a_demo_link: getValueByKey('book_a_demo_link'),
        app_environment: getValueByKey('app_environment'),
        frontend_variant: normalizedFrontendVariant(getValueByKey('frontend_variant') || 'premium'),
        display_frontend:  getValueByKey('display_frontend') == 1 || getValueByKey('display_frontend') == '' ? true : false,
        logo: null,
        favicon: null,
        socials: {
            facebook: socials?.facebook,
            twitter: socials?.twitter,
            tiktok: socials?.tiktok,
            snapchat: socials?.snapchat,
            slack: socials?.slack,
            linkedin: socials?.linkedin
        }
    })

    const submitForm = async () => {
        form.post('/admin/settings?type=general', {
            preserveScroll: true,
        })
    };
</script>

<style scoped>
.gs-toggle-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.gs-toggle-title {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--ui-text);
}

.gs-toggle-hint {
    margin-top: 0.2rem;
    font-size: 0.8rem;
    color: var(--ui-muted);
}

.gs-hint {
    font-size: 0.78rem;
    color: var(--ui-muted);
}

.gs-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.85rem;
    padding: 0.6rem 1.3rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: filter 160ms ease, opacity 160ms ease;
}

.gs-btn--solid {
    background: var(--ui-secondary);
    color: #fff;
    box-shadow: var(--ui-shadow-1);
}

.gs-btn--solid:hover:not(:disabled) {
    filter: brightness(1.05);
}

.gs-btn--solid:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}
</style>
