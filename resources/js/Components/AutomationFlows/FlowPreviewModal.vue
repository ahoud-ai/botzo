<template>
    <TransitionRoot appear :show="open" as="template">
        <Dialog as="div" class="relative z-[2400]" @close="$emit('close')">
            <TransitionChild
                as="template"
                enter="duration-300 ease-out"
                enter-from="opacity-0"
                enter-to="opacity-100"
                leave="duration-200 ease-in"
                leave-from="opacity-100"
                leave-to="opacity-0"
            >
                <div class="fixed inset-0 ui-layer-modal-backdrop backdrop-blur-sm" />
            </TransitionChild>

            <div class="fixed inset-0 z-[2400] overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-3 sm:p-4 xl:p-6">
                    <TransitionChild
                        as="template"
                        enter="duration-300 ease-out"
                        enter-from="opacity-0 scale-95"
                        enter-to="opacity-100 scale-100"
                        leave="duration-200 ease-in"
                        leave-from="opacity-100 scale-100"
                        leave-to="opacity-0 scale-95"
                    >
                        <DialogPanel class="w-full max-w-[400px] overflow-hidden rounded-[28px] border border-[var(--ui-border-strong)] bg-[var(--ui-surface)] shadow-[0_30px_72px_color-mix(in_srgb,var(--ui-text)_28%,transparent)] sm:max-w-[420px] sm:rounded-[30px]">
                            <div class="flex items-center justify-between gap-4 border-b border-[var(--ui-border)] bg-[var(--flow-panel-bg)] px-4 py-3">
                                <DialogTitle class="text-[15px] font-semibold text-[var(--ui-text)] sm:text-base">{{ $t('Mobile preview') }}</DialogTitle>
                                <button
                                    type="button"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[var(--ui-border-strong)] bg-[var(--ui-surface-soft)] text-[var(--ui-muted)] transition hover:border-[var(--ui-secondary)] hover:text-[var(--ui-text)]"
                                    :title="$t('Close')"
                                    @click="$emit('close')"
                                >
                                    <X class="h-4 w-4" />
                                </button>
                            </div>

                            <div class="p-3 sm:p-4">
                                <FlowPreviewDrawer
                                    :preview="preview"
                                    :collapsed="false"
                                    surface="modal"
                                    :ui-enhanced="uiEnhanced"
                                    :active-node-label="activeNodeLabel"
                                    :graph="graph"
                                    :node-titles="nodeTitles"
                                    :assets="assets"
                                    :contact-fields="contactFields"
                                    :contact-groups="contactGroups"
                                    :assignable-agents="assignableAgents"
                                    :validation="validation"
                                    :insights="insights"
                                    :builder-policy="builderPolicy"
                                    :runtime-rules="runtimeRules"
                                    @simulation-state-change="$emit('simulation-state-change', $event)"
                                />
                            </div>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

<script setup>
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue';
import { X } from 'lucide-vue-next';
import FlowPreviewDrawer from '@/Components/AutomationFlows/FlowPreviewDrawer.vue';

defineProps({
    open: { type: Boolean, default: false },
    preview: { type: Object, default: () => ({ scenario: 'main', steps: [] }) },
    activeNodeLabel: { type: String, default: '' },
    graph: { type: Object, default: () => ({ start_node_id: null, nodes: [], edges: [] }) },
    nodeTitles: { type: Object, default: () => ({}) },
    assets: { type: Object, default: () => ({}) },
    contactFields: { type: Array, default: () => [] },
    contactGroups: { type: Array, default: () => [] },
    assignableAgents: { type: Array, default: () => [] },
    uiEnhanced: { type: Boolean, default: true },
    validation: { type: Object, default: () => ({ valid: true, errors: [], warnings: [] }) },
    builderPolicy: { type: Object, default: () => ({}) },
    runtimeRules: { type: Object, default: () => ({}) },
    insights: {
        type: Object,
        default: () => ({
            buttonChoices: 0,
            listChoices: 0,
            conditionSteps: 0,
            replyCaptureSteps: 0,
        }),
    },
});

defineEmits(['close', 'simulation-state-change']);
</script>
