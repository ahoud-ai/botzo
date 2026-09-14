<template>
    <AppLayout>
        <UiPageHeader :title="$t('Broadcast drivers')" :subtitle="$t('Configure your broadcast drivers for realtime chat updates')" />

        <form @submit.prevent="submitForm()" class="mt-6 space-y-6">
            <UiSectionCard>
                <div class="grid gap-6 sm:grid-cols-2">
                    <FormSelect v-model="form.broadcast_driver" :name="$t('Broadcast driver')" :type="'text'" :options="methods" :error="form.errors.broadcast_driver" :class="'sm:col-span-2'"/>
                    <template v-if="form.broadcast_driver === 'pusher'">
                        <FormInput v-model="form.pusher_app_id" :name="$t('Pusher app id')" :type="'text'" :error="form.errors.pusher_app_id"/>
                        <FormInput v-model="form.pusher_app_key" :name="$t('Pusher app key')" :type="'text'" :error="form.errors.pusher_app_key"/>
                        <FormInput v-model="form.pusher_app_secret" :name="$t('Pusher app secret')" :type="'password'" :error="form.errors.pusher_app_secret"/>
                        <FormInput v-model="form.pusher_app_cluster" :name="$t('Pusher app cluster')" :type="'text'" :error="form.errors.pusher_app_cluster"/>
                    </template>
                </div>
            </UiSectionCard>

            <div class="flex items-center justify-end">
                <button type="submit" class="bd-btn bd-btn--solid" :disabled="isLoading">
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
    import { useI18n } from 'vue-i18n';
    import FormInput from '@/Components/FormInput.vue';
    import FormSelect from '@/Components/FormSelect.vue';
    import UiPageHeader from '@/Components/UI/UiPageHeader.vue';
    import UiSectionCard from '@/Components/UI/UiSectionCard.vue';
    const { t } = useI18n();

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

    const isLoading = ref(false);
    const form = useForm({
        broadcast_driver: getValueByKey('broadcast_driver'),
        pusher_app_key: getValueByKey('pusher_app_key'),
        pusher_app_id: getValueByKey('pusher_app_id'),
        pusher_app_secret: getValueByKey('pusher_app_secret'),
        pusher_app_cluster: getValueByKey('pusher_app_cluster'),
    })

    const methods = [
        { label: t('Pusher'), value: 'pusher' },
    ]

    const submitForm = async () => {
        form.put('/admin/settings?type=broadcast', {
            preserveScroll: true,
        });
    };
</script>

<style scoped>
.bd-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.85rem;
    padding: 0.6rem 1.3rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: filter 160ms ease, opacity 160ms ease;
}

.bd-btn--solid {
    background: var(--ui-secondary);
    color: #fff;
    box-shadow: var(--ui-shadow-1);
}

.bd-btn--solid:hover:not(:disabled) {
    filter: brightness(1.05);
}

.bd-btn--solid:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}
</style>
