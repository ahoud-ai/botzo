<script setup>
    import { computed, ref, watch } from 'vue';
    import { useForm } from "@inertiajs/vue3";
    import FormInput from '@/Components/FormInput.vue';
    import Modal from '@/Components/Modal.vue';

    const props = defineProps(['type', 'modelValue']);
    const emit = defineEmits(['update:modelValue']);

    const isOpenModal = ref(props.modelValue);

    const form = useForm({
        name: null,
    })

    const resetFormState = () => {
        form.reset();
        form.clearErrors();
    };

    watch(() => props.modelValue, (newValue) => {
        isOpenModal.value = newValue;

        if (newValue) {
            resetFormState();
        }
    });

    const isBranchMode = computed(() => props.type?.type === 'branch');
    const branchCreationBlocked = computed(() => Boolean(
        isBranchMode.value && props.type?.canCreateBranch === false
    ));
    const branchCreationBlockingMessage = computed(() => (
        branchCreationBlocked.value ? (props.type?.blockingMessage || '') : ''
    ));
    const backendBranchGuardError = computed(() => (
        form.errors.parent_organization_uuid
        || form.errors.parentOrganization
        || ''
    ));
    const backendGeneralError = computed(() => (
        form.errors.organization
        || form.errors.plan
        || ''
    ));

    const submitForm = async () => {
        if (branchCreationBlocked.value) {
            return;
        }

        form.post('/organization', {
            preserveScroll: true,
        })
    };

    function closeModal(){
        resetFormState();
        isOpenModal.value = false;
        emit('update:modelValue', false);
    }
</script>
<template>
    <Modal :label="isBranchMode ? $t('Add branch') : $t('Create workspace')" :isOpen="isOpenModal" @close="closeModal()">
        <div class="mt-5 grid grid-cols-1 gap-x-6 gap-y-4">
            <form @submit.prevent="submitForm()" class="gap-y-4">
                <div
                    v-if="branchCreationBlockingMessage"
                    class="orgm-notice orgm-notice--warning"
                >
                    {{ branchCreationBlockingMessage }}
                </div>
                <div
                    v-if="backendBranchGuardError"
                    class="orgm-notice orgm-notice--danger"
                >
                    {{ backendBranchGuardError }}
                </div>
                <div
                    v-if="backendGeneralError"
                    class="orgm-notice orgm-notice--danger"
                >
                    {{ backendGeneralError }}
                </div>
                <div class="grid grid-cols gap-y-4">
                    <FormInput v-model="form.name" :name="isBranchMode ? $t('Branch name') : $t('Workspace name')" :error="form.errors.name" :type="'text'" :class="'col-span-6'"/>
                    <div v-if="isBranchMode" class="orgm-hint">
                        {{ $t('This branch will inherit the subscription, features, and limits from') }}: {{ props.type?.parentOrganization?.name ?? $t('Parent organization') }}
                    </div>
                    <div v-else class="orgm-hint">
                                {{ $t('Your first workspace will be created for your own account. You can choose a plan after setup.') }}
                    </div>
                </div>

                <div class="mt-4 flex">
                    <button type="button" @click="closeModal()" class="orgm-btn orgm-btn--ghost me-3">{{ $t('Cancel') }}</button>
                    <button type="submit"
                        class="orgm-btn orgm-btn--solid"
                        :class="{ 'orgm-btn--loading': form.processing }"
                        :disabled="form.processing || branchCreationBlocked"
                    >
                        <svg v-if="form.processing" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".5"/><path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z"><animateTransform attributeName="transform" dur="1s" from="0 12 12" repeatCount="indefinite" to="360 12 12" type="rotate"/></path></svg>
                        <span v-else>{{ $t('Save') }}</span>
                    </button>
                </div>
            </form>
        </div>
    </Modal>
</template>

<style scoped>
.orgm-notice {
    margin-bottom: 1rem;
    border-radius: 0.85rem;
    padding: 0.65rem 1rem;
    font-size: 0.85rem;
}

.orgm-notice--warning {
    border: 1px solid color-mix(in srgb, var(--ui-warning) 35%, var(--ui-border));
    background: color-mix(in srgb, var(--ui-warning) 10%, var(--ui-surface));
    color: color-mix(in srgb, var(--ui-warning) 80%, var(--ui-text));
}

.orgm-notice--danger {
    border: 1px solid color-mix(in srgb, var(--ui-danger) 35%, var(--ui-border));
    background: color-mix(in srgb, var(--ui-danger) 10%, var(--ui-surface));
    color: color-mix(in srgb, var(--ui-danger) 80%, var(--ui-text));
}

.orgm-hint {
    border-radius: 0.85rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface-soft);
    padding: 0.65rem 1rem;
    font-size: 0.85rem;
    color: var(--ui-muted);
}

.orgm-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.75rem;
    padding: 0.55rem 1.1rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: filter 160ms ease, background-color 160ms ease, border-color 160ms ease, opacity 160ms ease;
}

.orgm-btn--ghost {
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    color: var(--ui-text);
}

.orgm-btn--ghost:hover {
    background: var(--ui-surface-soft);
    border-color: var(--ui-border-strong);
}

.orgm-btn--solid {
    background: var(--ui-secondary);
    color: #fff;
    box-shadow: var(--ui-shadow-1);
}

.orgm-btn--solid:hover:not(:disabled) {
    filter: brightness(1.05);
}

.orgm-btn--solid:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}
</style>
