<template>
    <AppLayout>
        <UiPageHeader :title="$t('Social login settings')" :subtitle="$t('Configure social accounts for user login')" />

        <form @submit.prevent="submitForm()" class="mt-6 space-y-6">
            <UiSectionCard>
                <div class="grid gap-6 sm:grid-cols-2">
                    <FormInput v-model="form.google_login.client_id" :name="$t('Google client id')" :error="form.errors['google_login.client_id']" :type="'text'"/>
                    <FormInput v-model="form.google_login.client_secret" :name="$t('Google client secret')" :error="form.errors['google_login.client_secret']" :type="'password'"/>
                    <FormInput v-model="form.facebook_login.client_id" :name="$t('Facebook client id')" :error="form.errors['facebook_login.client_id']" :type="'text'"/>
                    <FormInput v-model="form.facebook_login.client_secret" :name="$t('Facebook client secret')" :error="form.errors['facebook_login.client_secret']" :type="'password'"/>

                    <div class="sc-toggle-row sm:col-span-2">
                        <span class="sc-toggle-label">{{ $t('Allow google login') }}</span>
                        <FormToggleSwitch v-model="form.allow_google_login"/>
                    </div>
                    <div class="sc-toggle-row sm:col-span-2">
                        <span class="sc-toggle-label">{{ $t('Allow facebook login') }}</span>
                        <FormToggleSwitch v-model="form.allow_facebook_login"/>
                    </div>
                </div>
            </UiSectionCard>

            <div class="flex items-center justify-end">
                <button type="submit" class="sc-btn sc-btn--solid" :disabled="isLoading">
                    <svg v-if="isLoading" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".5"/><path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z"><animateTransform attributeName="transform" dur="1s" from="0 12 12" repeatCount="indefinite" to="360 12 12" type="rotate"/></path></svg>
                    <span v-else>{{ $t('Save') }}</span>
                </button>
            </div>
        </form>
    </AppLayout>
</template>
<script setup>
    import AppLayout from "./Layout/App.vue";
    import { ref } from 'vue';
    import { useForm } from "@inertiajs/vue3";
    import FormInput from '@/Components/FormInput.vue';
    import FormToggleSwitch from '@/Components/FormToggleSwitch.vue';
    import UiPageHeader from '@/Components/UI/UiPageHeader.vue';
    import UiSectionCard from '@/Components/UI/UiSectionCard.vue';

    const props = defineProps({
        config: {
            type: Object,
            required: true
        }
    });

    const getValueByKey = (key) => {
        const found = props.config.find(item => item.key === key);
        return found ? found.value : '';
    };

    const getSocialSettings = (key, value) => {
        try {
            const raw = getValueByKey(key);
            const config = raw ? JSON.parse(raw) : null;
            if (config !== null && typeof config === 'object' && !Array.isArray(config)) {
                return config[value] ?? null;
            }
        } catch (e) {}
        return null;
    }

    const isLoading = ref(false);
    const form = useForm({
        allow_facebook_login: getValueByKey('allow_facebook_login') === '1' ? true : false,
        allow_google_login: getValueByKey('allow_google_login') === '1' ? true : false,
        facebook_login: {
            client_id: getSocialSettings('facebook_login', 'client_id') ?? null,
            client_secret: getSocialSettings('facebook_login', 'client_secret') ?? null,
        },
        google_login: {
            client_id: getSocialSettings('google_login', 'client_id') ?? null,
            client_secret: getSocialSettings('google_login', 'client_secret') ?? null,
        },
    })

    const submitForm = async () => {
        form.put('/admin/settings?type=socials', {
            preserveScroll: true,
        });
    };
</script>

<style scoped>
.sc-toggle-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    border-radius: 0.9rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface-soft);
    padding: 0.85rem 1.05rem;
}

.sc-toggle-label {
    font-size: 0.88rem;
    font-weight: 600;
    color: var(--ui-text);
}

.sc-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.85rem;
    padding: 0.6rem 1.3rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: filter 160ms ease, opacity 160ms ease;
}

.sc-btn--solid {
    background: var(--ui-secondary);
    color: #fff;
    box-shadow: var(--ui-shadow-1);
}

.sc-btn--solid:hover:not(:disabled) {
    filter: brightness(1.05);
}

.sc-btn--solid:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}
</style>
