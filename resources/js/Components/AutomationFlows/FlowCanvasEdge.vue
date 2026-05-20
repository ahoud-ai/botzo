<template>
    <BaseEdge :path="edgePath" :marker-end="markerEnd" :style="edgeStyle" />

    <EdgeLabelRenderer>
        <div class="pointer-events-none absolute z-[8]" :style="labelStyle">
            <div
                class="flow-canvas-edge-badge pointer-events-auto inline-flex items-center gap-1.5 rounded-full border shadow-[0_10px_24px_rgba(15,23,42,0.10)]"
                :class="[badgeClass, showLabel ? 'px-2.5 py-1.5' : 'px-1.5 py-1.5', selected ? 'scale-[1.02]' : '']"
            >
                <span v-if="showLabel" class="max-w-[128px] truncate text-[10px] font-semibold">{{ data?.branchLabel }}</span>
                <button
                    v-if="data?.canInsert"
                    type="button"
                    class="nodrag nopan inline-flex h-5 w-5 items-center justify-center rounded-full border transition"
                    :class="actionClass"
                    :title="$t('Add step here')"
                    @click.stop="insertStep"
                >
                    <Plus class="h-3 w-3" />
                </button>
            </div>
        </div>
    </EdgeLabelRenderer>
</template>

<script setup>
import { computed } from 'vue';
import { BaseEdge, EdgeLabelRenderer, Position, getSmoothStepPath } from '@vue-flow/core';
import { Plus } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    id: { type: String, required: true },
    sourceX: { type: Number, required: true },
    sourceY: { type: Number, required: true },
    targetX: { type: Number, required: true },
    targetY: { type: Number, required: true },
    sourcePosition: { type: String, default: Position.Right },
    targetPosition: { type: String, default: Position.Left },
    markerEnd: { type: [String, Object], default: null },
    style: { type: Object, default: () => ({}) },
    selected: { type: Boolean, default: false },
    data: { type: Object, default: () => ({}) },
});

const { t } = useI18n();

const edgeGeometry = computed(() => getSmoothStepPath({
    sourceX: props.sourceX,
    sourceY: props.sourceY,
    targetX: props.targetX,
    targetY: props.targetY,
    sourcePosition: props.sourcePosition || Position.Right,
    targetPosition: props.targetPosition || Position.Left,
    borderRadius: 22,
    offset: 18,
}));
const edgePath = computed(() => edgeGeometry.value[0]);
const labelX = computed(() => edgeGeometry.value[1]);
const labelY = computed(() => edgeGeometry.value[2]);
const edgeStyle = computed(() => ({
    ...props.style,
    strokeLinecap: 'round',
    strokeLinejoin: 'round',
}));
const labelStyle = computed(() => ({
    transform: `translate(-50%, -50%) translate(${labelX.value}px, ${labelY.value}px)`,
}));
const showLabel = computed(() => Boolean(props.data?.showLabel && props.data?.branchLabel));
const badgeClass = computed(() => props.data?.badgeClass || 'border-slate-200 bg-white/95 text-slate-700');
const actionClass = computed(() => props.data?.actionClass || 'border-slate-200 bg-white text-slate-500 hover:border-slate-300 hover:text-slate-900');

const insertStep = () => {
    props.data?.onInsertNode?.(props.id, 'send_text');
};
</script>
