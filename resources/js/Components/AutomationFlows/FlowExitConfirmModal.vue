<template>
    <Modal :is-open="open" :label="$t('Leave builder?')" :close-btn="true" @close="$emit('close')">
        <div class="space-y-5 pt-3">
            <div class="rounded-2xl border border-[color-mix(in_srgb,var(--ui-warning)_35%,var(--ui-border))] bg-[color-mix(in_srgb,var(--ui-warning)_12%,var(--ui-surface))] px-4 py-3 text-sm leading-6 text-[var(--ui-warning)]">
                {{ $t('You have unsaved changes in this flow. Save before leaving, or leave without saving.') }}
            </div>

            <div class="rounded-2xl border border-[var(--ui-border)] bg-[var(--ui-surface-soft)] px-4 py-3">
                <div class="text-sm font-semibold text-[var(--ui-text)]">{{ flowName || $t('Automation') }}</div>
                <div class="mt-1 text-xs leading-5 text-[var(--ui-muted)]">{{ $t('Your current canvas edits will be lost if you leave without saving.') }}</div>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-3">
                <AutomationButton variant="secondary" :disabled="busy" @click="$emit('close')">
                    {{ $t('Stay here') }}
                </AutomationButton>
                <AutomationButton variant="secondary" :disabled="busy" @click="$emit('discard')">
                    {{ $t('Leave without saving') }}
                </AutomationButton>
                <AutomationButton variant="primary" :disabled="busy" @click="$emit('save-and-exit')">
                    {{ busy ? $t('Saving...') : $t('Save and exit') }}
                </AutomationButton>
            </div>
        </div>
    </Modal>
</template>

<script setup>
import Modal from '@/Components/Modal.vue';
import AutomationButton from '@/Components/AutomationFlows/Ui/AutomationButton.vue';

defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    busy: {
        type: Boolean,
        default: false,
    },
    flowName: {
        type: String,
        default: '',
    },
});

defineEmits(['close', 'discard', 'save-and-exit']);
</script>
