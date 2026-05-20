<script setup>
import axios from 'axios';
import { useForm } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Modal from '@/Components/Modal.vue';
import FormInput from '@/Components/FormInput.vue';
import FormSelect from '@/Components/FormSelect.vue';

const { t } = useI18n();

defineProps({
    modelValue: Boolean,
});

const form = useForm({
    publishable_key: null,
    secret_key: null,
    webhook_secret: null,
    mode: 'test',
    status: null,
});

const isLoading = ref(false);

const modeOptions = ref([
    { value: 'test', label: t('Test') },
    { value: 'live', label: t('Live') },
]);

const statusOptions = ref([
    { value: '1', label: t('Active') },
    { value: '0', label: t('Inactive') },
]);

const emit = defineEmits(['update:modelValue', 'callback']);

const parseMetadata = (metadata) => {
    if (!metadata) {
        return {};
    }

    if (typeof metadata === 'object') {
        return metadata;
    }

    if (typeof metadata !== 'string') {
        return {};
    }

    try {
        const parsed = JSON.parse(metadata);
        return parsed && typeof parsed === 'object' ? parsed : {};
    } catch (error) {
        return {};
    }
};

const getRow = () => {
    axios
        .get('/admin/payment-gateways/moyasar')
        .then((response) => {
            const gateway = response?.data?.data ?? {};
            const metadata = parseMetadata(gateway.metadata);

            form.publishable_key = metadata.publishable_key || null;
            form.secret_key = metadata.secret_key || null;
            form.webhook_secret = metadata.webhook_secret || null;
            form.mode = metadata.mode || 'test';
            form.status = gateway.is_active ? '1' : '0';
        })
        .catch(() => {});
};

const submitForm = () => {
    form.put('/admin/payment-gateways/moyasar', {
        preserveScroll: true,
        onStart: () => {
            isLoading.value = true;
        },
        onFinish: () => {
            isLoading.value = false;
        },
        onSuccess: () => {
            onClose();
        },
    });
};

const onClose = () => {
    emit('update:modelValue', false);
};

onMounted(getRow);
</script>

<template>
    <Modal :label="$t('Edit Moyasar configuration')" :isOpen="modelValue" @close="onClose">
        <div class="mt-5 grid grid-cols-1 gap-x-6 gap-y-4">
            <form @submit.prevent="submitForm" class="">
                <div class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-6 sm:col-span-4 pb-8 border-b">
                    <FormInput
                        v-model="form.publishable_key"
                        :name="$t('Publishable key')"
                        :type="'text'"
                        :error="form.errors.publishable_key"
                        :class="'sm:col-span-3'"
                    />
                    <FormInput
                        v-model="form.secret_key"
                        :name="$t('Secret key')"
                        :type="'text'"
                        :error="form.errors.secret_key"
                        :class="'sm:col-span-3'"
                    />
                    <FormInput
                        v-model="form.webhook_secret"
                        :name="$t('Webhook secret')"
                        :type="'text'"
                        :error="form.errors.webhook_secret"
                        :class="'sm:col-span-6'"
                    />
                    <FormSelect
                        v-model="form.mode"
                        :name="$t('Mode')"
                        :type="'text'"
                        :options="modeOptions"
                        :error="form.errors.mode"
                        :class="'sm:col-span-3'"
                    />
                    <FormSelect
                        v-model="form.status"
                        :name="$t('Status')"
                        :type="'text'"
                        :options="statusOptions"
                        :error="form.errors.status"
                        :class="'sm:col-span-3'"
                    />
                </div>

                <div class="mt-4 flex">
                    <button
                        type="button"
                        @click="onClose"
                        class="inline-flex justify-center rounded-md border border-transparent bg-slate-50 px-4 py-2 text-sm text-slate-500 hover:bg-slate-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 me-4"
                    >
                        {{ $t('Cancel') }}
                    </button>
                    <button
                        type="submit"
                        :class="[
                            'inline-flex justify-center rounded-md border border-transparent bg-primary px-4 py-2 text-sm text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2',
                            { 'opacity-50': isLoading },
                        ]"
                        :disabled="isLoading"
                    >
                        <svg
                            v-if="isLoading"
                            xmlns="http://www.w3.org/2000/svg"
                            width="20"
                            height="20"
                            viewBox="0 0 24 24"
                        >
                            <path
                                fill="currentColor"
                                d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z"
                                opacity=".5"
                            />
                            <path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z">
                                <animateTransform
                                    attributeName="transform"
                                    dur="1s"
                                    from="0 12 12"
                                    repeatCount="indefinite"
                                    to="360 12 12"
                                    type="rotate"
                                />
                            </path>
                        </svg>
                        <span v-else>{{ $t('Save') }}</span>
                    </button>
                </div>
            </form>
        </div>
    </Modal>
</template>

