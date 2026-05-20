<template>
    <FlowDangerConfirmModal
        :open="nodeDeleteOpen"
        :title="$t('Delete step?')"
        :message="$t('This step will be removed from the canvas together with any connections that depend on it.')"
        :subject="nodeDeleteSubject || $t('Step')"
        :note="$t('This action cannot be undone.')"
        :confirm-label="$t('Delete step')"
        :cancel-label="$t('Keep step')"
        :busy-label="$t('Deleting...')"
        @close="$emit('close-node-delete')"
        @confirm="$emit('confirm-node-delete')"
    />
    <FlowDangerConfirmModal
        :open="flowDeleteOpen"
        :busy="flowDeleteBusy"
        :title="$t('Delete automation?')"
        :message="$t('This automation will be removed permanently from your workspace. Published versions and drafts cannot be restored after deletion.')"
        :subject="flowName || $t('Automation')"
        :note="$t('This action cannot be undone.')"
        :confirm-label="$t('Delete automation')"
        :cancel-label="$t('Keep automation')"
        :busy-label="$t('Deleting...')"
        @close="$emit('close-flow-delete')"
        @confirm="$emit('confirm-flow-delete')"
    />
</template>

<script setup>
import FlowDangerConfirmModal from '@/Components/AutomationFlows/FlowDangerConfirmModal.vue';

defineProps({
    nodeDeleteOpen: {
        type: Boolean,
        default: false,
    },
    nodeDeleteSubject: {
        type: String,
        default: '',
    },
    flowDeleteOpen: {
        type: Boolean,
        default: false,
    },
    flowDeleteBusy: {
        type: Boolean,
        default: false,
    },
    flowName: {
        type: String,
        default: '',
    },
});

defineEmits(['close-node-delete', 'confirm-node-delete', 'close-flow-delete', 'confirm-flow-delete']);
</script>
