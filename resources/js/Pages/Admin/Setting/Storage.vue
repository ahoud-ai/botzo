<template>
    <AppLayout>
        <UiPageHeader :title="$t('Storage settings')" :subtitle="$t('Configure your storage location')" />

        <form @submit.prevent="submitForm()" class="mt-6 space-y-6">
            <UiSectionCard>
                <div class="grid gap-6 sm:grid-cols-2">
                    <FormSelect v-model="form.storage_system" :name="$t('File system for storage')" :type="'text'" :options="methods" :error="form.errors.storage_system" :class="'sm:col-span-2'"/>

                    <template v-if="form.storage_system === 'aws'">
                        <FormInput v-model="form.aws.access_key" :name="$t('S3 AWS access key')" :type="'text'" :error="form.errors['aws.access_key']"/>
                        <FormInput v-model="form.aws.secret_key" :name="$t('S3 AWS secret access key')" :type="'password'" :error="form.errors['aws.secret_key']"/>
                        <FormInput v-model="form.aws.default_region" :name="$t('S3 AWS default region')" :type="'text'" :error="form.errors['aws.default_region']"/>
                        <FormInput v-model="form.aws.bucket" :name="$t('S3 AWS bucket')" :type="'text'" :error="form.errors['aws.bucket']"/>
                    </template>
                </div>
            </UiSectionCard>

            <div class="flex items-center justify-end">
                <button type="submit" class="stg-btn stg-btn--solid" :disabled="isLoading">
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

    const getAWSSettings = (key) => {
        if(getValueByKey('aws')){
            const AWSConfig = JSON.parse(getValueByKey('aws'));

            // Check if mailConfig is not null, is an object, and not an array
            if (AWSConfig !== null && typeof AWSConfig === 'object' && !Array.isArray(AWSConfig)) {
                return AWSConfig[key] ?? null;
            }
        }

        return null;
    }

    const isLoading = ref(false);
    const form = useForm({
        storage_system: getValueByKey('storage_system'),
        aws: {
            access_key: getAWSSettings('access_key') ?? undefined,
            secret_key: getAWSSettings('secret_key') ?? undefined,
            default_region: getAWSSettings('default_region') ?? undefined,
            bucket: getAWSSettings('bucket') ?? undefined,
        }
    })

    const methods = [
        { label: t('Local File Storage'), value: 'local' },
        { label: t('AWS S3 Bucket'), value: 'aws' },
    ]

    const submitForm = async () => {
        form.put('/admin/settings?type=storage', {
            preserveScroll: true,
        });
    };
</script>

<style scoped>
.stg-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.85rem;
    padding: 0.6rem 1.3rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: filter 160ms ease, opacity 160ms ease;
}

.stg-btn--solid {
    background: var(--ui-secondary);
    color: #fff;
    box-shadow: var(--ui-shadow-1);
}

.stg-btn--solid:hover:not(:disabled) {
    filter: brightness(1.05);
}

.stg-btn--solid:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}
</style>
