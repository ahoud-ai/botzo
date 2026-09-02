<template>
    <Modal :is-open="open" :label="title" :close-btn="true" @close="$emit('close')">
        <div class="space-y-5 pt-3">
            <div class="rounded-2xl border border-[color-mix(in_srgb,var(--ui-danger)_35%,var(--ui-border))] bg-[color-mix(in_srgb,var(--ui-danger)_12%,var(--ui-surface))] px-4 py-3 text-sm leading-6 text-[var(--ui-danger)]">
                {{ message }}
            </div>

            <div class="rounded-2xl border border-[var(--ui-border)] bg-[var(--ui-surface-soft)] px-4 py-3">
                <div class="text-sm font-semibold text-[var(--ui-text)]">{{ subject || $t('Automation') }}</div>
                <div v-if="note" class="mt-1 text-xs leading-5 text-[var(--ui-muted)]">{{ note }}</div>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-3">
                <AutomationButton variant="secondary" :disabled="busy" @click="$emit('close')">
                    {{ cancelLabel }}
                </AutomationButton>
                <AutomationButton variant="danger" :disabled="busy" @click="$emit('confirm')">
                    {{ busy ? busyLabel : confirmLabel }}
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
    title: {
        type: String,
        default: 'Delete automation?',
    },
    message: {
        type: String,
        default: 'This action cannot be undone.',
    },
    subject: {
        type: String,
        default: '',
    },
    note: {
        type: String,
        default: '',
    },
    confirmLabel: {
        type: String,
        default: 'Delete',
    },
    cancelLabel: {
        type: String,
        default: 'Cancel',
    },
    busyLabel: {
        type: String,
        default: 'Deleting...',
    },
});

defineEmits(['close', 'confirm']);
</script>
