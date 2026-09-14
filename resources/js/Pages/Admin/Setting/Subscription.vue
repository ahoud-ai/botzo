<template>
    <AppLayout>
        <UiPageHeader :title="$t('Subscription settings')" :subtitle="$t('Configure how your subscriptions are set')" />

        <form @submit.prevent="submitForm()" class="mt-6 space-y-6">
            <UiSectionCard>
                <div class="sub-toggle-row">
                    <div class="min-w-0 flex-1">
                        <p class="sub-toggle-title">{{ $t('Enable custom payment amounts') }}</p>
                        <p class="sub-hint mt-1">{{ $t('Allow users to make custom payments that can be applied to their subscription fees automatically.') }}</p>
                    </div>
                    <FormToggleSwitch v-model="form.enable_custom_payment"/>
                </div>

                <div class="mt-6 max-w-xs">
                    <FormInput v-model="form.trial_period" :name="$t('Trial period (in days)')" :error="form.errors.trial_period" :type="'number'"/>
                    <p class="sub-hint mt-2">{{ $t('Please note that if you put zero then the user will be prompted to first subscribe before using the app') }}</p>
                </div>
            </UiSectionCard>

            <UiSectionCard :title="$t('Trial period limits')" :subtitle="$t('Define the usage limits during the trial period')">
                <div class="sub-banner sub-banner--warning mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                    <p>{{ $t('For unlimited usage, set -1 as the value') }}</p>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <FormInput v-model="form.trial_limits.messages" :name="$t('Message limit')" :error="form.errors?.['trial_limits.messages']" :type="'number'"/>
                    <FormInput v-model="form.trial_limits.campaigns" :name="$t('Campaign limit')" :error="form.errors?.['trial_limits.campaigns']" :type="'number'"/>
                    <FormInput v-model="form.trial_limits.contacts" :name="$t('Contacts limit')" :error="form.errors?.['trial_limits.contacts']" :type="'number'"/>
                    <FormInput v-model="form.trial_limits.automated_replies" :name="$t('Canned/Automated replies limit')" :error="form.errors?.['trial_limits.automated_replies']" :type="'number'"/>
                    <FormInput v-model="form.trial_limits.users" :name="$t('User limit')" :error="form.errors?.['trial_limits.users']" :type="'number'"/>
                    <p class="sub-hint sm:col-span-2">{{ $t('Automated replies and canned replies refer to the same feature. This value applies only to trial accounts.') }}</p>
                </div>
            </UiSectionCard>

            <div class="flex items-center justify-end">
                <button type="submit" class="sub-btn sub-btn--solid" :disabled="isLoading">
                    <svg v-if="isLoading" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".5"/><path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z"><animateTransform attributeName="transform" dur="1s" from="0 12 12" repeatCount="indefinite" to="360 12 12" type="rotate"/></path></svg>
                    <span v-else>{{ $t('Save') }}</span>
                </button>
            </div>
        </form>
    </AppLayout>
</template>
<script setup>
    import AppLayout from "./Layout/App.vue";
    import { useForm } from "@inertiajs/vue3";
    import { defineProps, ref } from 'vue';
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

    const limits = getValueByKey('trial_limits') ? JSON.parse(getValueByKey('trial_limits')) : {};

    const isLoading = ref(false);
    const form = useForm({
        enable_custom_payment: getValueByKey('enable_custom_payment') == 1 || getValueByKey('enable_custom_payment') == '' ? true : false,
        trial_period: getValueByKey('trial_period'),
        trial_limits: {
            users: limits?.users ?? '-1',
            messages: limits?.messages ?? '-1',
            contacts: limits?.contacts ?? '-1',
            campaigns: limits?.campaigns ?? '-1',
            automated_replies: limits?.automated_replies ?? '-1'
        }
    })

    const submitForm = async () => {
        form.put('/admin/settings?type=subscription', {
            preserveScroll: true,
        });
    };
</script>

<style scoped>
.sub-toggle-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.sub-toggle-title {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--ui-text);
}

.sub-hint {
    font-size: 0.78rem;
    color: var(--ui-muted);
}

.sub-banner {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    border-radius: 0.8rem;
    border: 1px solid color-mix(in srgb, var(--ui-warning) 35%, var(--ui-border));
    background: color-mix(in srgb, var(--ui-warning) 10%, var(--ui-surface));
    padding: 0.6rem 0.85rem;
    font-size: 0.85rem;
    color: var(--ui-text);
}

.sub-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.85rem;
    padding: 0.6rem 1.3rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: filter 160ms ease, opacity 160ms ease;
}

.sub-btn--solid {
    background: var(--ui-secondary);
    color: #fff;
    box-shadow: var(--ui-shadow-1);
}

.sub-btn--solid:hover:not(:disabled) {
    filter: brightness(1.05);
}

.sub-btn--solid:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}
</style>
