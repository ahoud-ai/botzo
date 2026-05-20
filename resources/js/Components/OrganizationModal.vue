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
                    class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
                >
                    {{ branchCreationBlockingMessage }}
                </div>
                <div
                    v-if="backendBranchGuardError"
                    class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"
                >
                    {{ backendBranchGuardError }}
                </div>
                <div
                    v-if="backendGeneralError"
                    class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"
                >
                    {{ backendGeneralError }}
                </div>
                <div class="grid grid-cols gap-y-4">
                    <FormInput v-model="form.name" :name="isBranchMode ? $t('Branch name') : $t('Workspace name')" :error="form.errors.name" :type="'text'" :class="'col-span-6'"/>
                    <div v-if="isBranchMode" class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                        {{ $t('This branch will inherit the subscription, features, and limits from') }}: {{ props.type?.parentOrganization?.name ?? $t('Parent organization') }}
                    </div>
                    <div v-else class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                                {{ $t('Your first workspace will be created for your own account. You can choose a plan after setup.') }}
                    </div>
                </div>
                
                <div class="mt-4 flex">
                    <button type="button" @click="closeModal()" class="inline-flex justify-center rounded-md border border-transparent bg-slate-50 px-4 py-2 text-sm text-slate-500 hover:bg-slate-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 me-4">{{ $t('Cancel') }}</button>
                    <button type="submit" 
                        :class="['inline-flex justify-center rounded-md border border-transparent bg-primary px-4 py-2 text-sm text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2', { 'opacity-50': form.processing }]"
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
