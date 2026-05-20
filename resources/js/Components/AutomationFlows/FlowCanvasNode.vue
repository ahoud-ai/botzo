<template>
    <div class="relative overflow-visible" :class="wrapperClass">
        <Handle
            v-if="!isTrigger"
            id="target"
            type="target"
            :position="Position.Left"
            class="!h-3.5 !w-3.5 !border-2 !border-white"
            :class="handleClass"
        />

        <article
            class="flow-card-shell relative overflow-visible rounded-[20px] border bg-white shadow-[0_14px_34px_rgba(15,23,42,0.08)] transition-[border-color,box-shadow,opacity,transform] duration-150"
            :class="articleClass"
            data-flow-node-card="true"
            data-flow-node-draggable="true"
            :data-flow-node-id="id"
            @mousedown.capture="handleSurfacePointerDown"
            @click="handleSurfaceClick"
            @dblclick="handleSurfaceDoubleClick"
        >
            <div class="relative z-[1]">
                <div class="h-1 rounded-t-[18px]" :class="cardTone.railClass"></div>

                <FlowCanvasNodeHeader
                    :card-tone="cardTone"
                    :category-label="nodeCategoryLabel"
                    :icon="IconComponent"
                    :is-expanded="isFocused"
                    :is-trigger="isTrigger"
                    :menu-button-ref="setMenuTriggerRef"
                    :status-badge="statusBadge"
                    :title="displayTitle"
                    @action-pointerdown="handleActionPointerDown"
                    @menu-click="toggleMenu"
                    @toggle-inline="toggleInlineEditor"
                />

                <div class="space-y-2 px-3 py-3">
                    <div
                        v-if="canvasPreviewText"
                        class="rounded-[16px] border border-slate-200/80 bg-[linear-gradient(180deg,rgba(248,250,252,0.82),rgba(255,255,255,0.98))] px-2.5 py-2 text-[11px] leading-[1.15rem] text-slate-600 shadow-[inset_0_1px_0_rgba(255,255,255,0.9)]"
                        :class="previewClampClass"
                        data-flow-node-summary="true"
                    >
                        {{ canvasPreviewText }}
                    </div>

                    <div v-if="cardMetrics.length || primaryCardIssue" class="flex flex-wrap gap-1.5">
                        <span
                            v-for="metric in cardMetrics"
                            :key="`${id}-metric-${metric.label}`"
                            class="inline-flex items-center rounded-full border px-2 py-0.5 text-[9px] font-semibold"
                            :class="metric.className"
                        >
                            {{ metric.label }}
                        </span>
                        <span v-if="primaryCardIssue" class="inline-flex max-w-full items-center rounded-full border border-rose-200 bg-rose-50 px-2 py-0.5 text-[9px] font-semibold text-rose-700">
                            <span class="truncate">{{ primaryCardIssue }}</span>
                        </span>
                    </div>
                </div>
            </div>

            <div v-if="isFocused" class="relative z-[2] nopan border-t border-slate-200 bg-[linear-gradient(180deg,rgba(248,250,252,0.95),rgba(255,255,255,0.96))] px-3 py-3" data-flow-node-inline-editor="true">
                <FlowNodeInspectorRenderer
                    :id="id"
                    :data="data"
                    :selected="selected"
                    variant="inline"
                    @close-inline="collapseInlineEditor"
                />
            </div>
        </article>

        <FlowCanvasNodeMenu
            :is-trigger="isTrigger"
            :open="menuOpen"
            :style="menuStyle"
            @action-pointerdown="handleActionPointerDown"
            @delete="handleDelete"
            @duplicate="handleDuplicate"
            @rename="handleRename"
        />

        <Handle
            v-for="handle in sourceHandles"
            :key="handle.id"
            :id="handle.id"
            type="source"
            :position="Position.Right"
            class="!h-3.5 !w-3.5 !border-2 !border-white"
            :class="handleClass"
            :style="{ top: `${handle.top}%` }"
        />
    </div>
</template>

<script setup>
import { Handle, Position } from '@vue-flow/core';
import FlowCanvasNodeHeader from '@/Components/AutomationFlows/FlowCanvasNodeHeader.vue';
import FlowCanvasNodeMenu from '@/Components/AutomationFlows/FlowCanvasNodeMenu.vue';
import FlowNodeInspectorRenderer from '@/Components/AutomationFlows/FlowNodeInspectorRenderer.vue';
import { useFlowCanvasNode } from '@/Components/AutomationFlows/useFlowCanvasNode.js';

const props = defineProps({
    id: {
        type: String,
        required: true,
    },
    data: {
        type: Object,
        required: true,
    },
    selected: {
        type: Boolean,
        default: false,
    },
});

    const {
        IconComponent,
        articleClass,
        cardMetrics,
        cardTone,
        canvasPreviewText,
        collapseInlineEditor,
        displayTitle,
        handleActionPointerDown,
        handleSurfaceClick,
    handleSurfaceDoubleClick,
    handleSurfacePointerDown,
    handleClass,
    handleDelete,
    handleDuplicate,
    handleRename,
    isTrigger,
    menuOpen,
    menuStyle,
    nodeCategoryLabel,
    primaryCardIssue,
    previewClampClass,
    setMenuTriggerRef,
    sourceHandles,
    statusBadge,
    toggleInlineEditor,
    toggleMenu,
    wrapperClass,
    isFocused,
} = useFlowCanvasNode(props);
</script>

<style scoped>
.nopan,
.nodrag {
    touch-action: auto;
}

.flow-card-shell.flow-card-shell-enhanced header {
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
}
</style>
