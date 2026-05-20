<template>
    <div class="flex h-full min-h-0 flex-col overflow-hidden">
        <div v-if="node" class="min-h-0 flex-1 overflow-y-auto rounded-[20px] bg-white">
            <FlowNodeInspectorRenderer
                :id="node.id"
                :data="node.data"
                :selected="true"
                variant="inspector"
            />
        </div>

        <div v-else class="flex h-full min-h-[220px] items-center justify-center rounded-[20px] border border-dashed border-slate-300 bg-white px-5 text-center">
            <div class="max-w-[280px]">
                <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 text-slate-500">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none">
                        <path d="M9 6h11M9 12h11M9 18h11M4 6h.01M4 12h.01M4 18h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </div>
                <div class="mt-4 text-sm font-semibold text-slate-900">{{ $t('Select a step to configure it here') }}</div>
                <div class="mt-2 text-xs leading-5 text-slate-500">{{ $t('Choose any step on the canvas to edit it from one calm panel.') }}</div>
                <div class="mt-3 flex flex-wrap items-center justify-center gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center rounded-xl border border-slate-200 bg-slate-900 px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-800"
                        @click="$emit('open-trigger')"
                    >
                        {{ $t('Open trigger settings') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import FlowNodeInspectorRenderer from '@/Components/AutomationFlows/FlowNodeInspectorRenderer.vue';

defineProps({
    node: {
        type: Object,
        default: null,
    },
});

defineEmits(['open-trigger']);
</script>
