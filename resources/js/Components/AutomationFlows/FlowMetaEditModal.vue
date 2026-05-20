<template>
    <Modal :is-open="open" :label="$t('Edit details')" :close-btn="true" @close="$emit('close')">
        <div class="space-y-5 pt-3">
            <FormInput v-model="localName" :label="$t('Name')" :placeholder="$t('Automation name')" />
            <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">{{ $t('Description') }}</label>
                <FlowAutosizeTextarea
                    v-model="localDescription"
                    rows="3"
                    class="w-full min-h-[96px] rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm leading-7 outline-none transition focus:border-primary focus:bg-white"
                    :placeholder="$t('Short internal description')"
                />
            </div>
            <div class="flex items-center justify-end gap-3">
                <AutomationButton variant="secondary" @click="$emit('close')">{{ $t('Cancel') }}</AutomationButton>
                <AutomationButton variant="primary" @click="emitSave">{{ $t('Save changes') }}</AutomationButton>
            </div>
        </div>
    </Modal>
</template>

<script setup>
import { ref, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import FormInput from '@/Components/FormInput.vue';
import FlowAutosizeTextarea from '@/Components/AutomationFlows/FlowAutosizeTextarea.vue';
import AutomationButton from '@/Components/AutomationFlows/Ui/AutomationButton.vue';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    name: {
        type: String,
        default: '',
    },
    description: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['close', 'save']);

const localName = ref(props.name || '');
const localDescription = ref(props.description || '');

watch(() => [props.open, props.name, props.description], ([open, name, description]) => {
    if (!open) {
        return;
    }

    localName.value = name || '';
    localDescription.value = description || '';
}, { immediate: true });

const emitSave = () => {
    emit('save', {
        name: localName.value || '',
        description: localDescription.value || '',
    });
};
</script>
