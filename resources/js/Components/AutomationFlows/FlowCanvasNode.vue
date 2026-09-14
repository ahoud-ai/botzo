<template>
    <div class="relative overflow-visible" :class="wrapperClass">
        <Handle
            v-if="!isTrigger"
            id="target"
            type="target"
            :position="Position.Left"
            class="!h-3.5 !w-3.5 !border-2 !border-[var(--ui-surface)]"
            :class="handleClass"
        />

        <article
            class="flow-card-shell relative overflow-visible rounded-[20px] border bg-[var(--ui-surface)] shadow-[0_14px_34px_color-mix(in_srgb,var(--ui-text)_8%,transparent)] transition-[border-color,box-shadow,opacity,transform] duration-150"
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
                        class="rounded-[16px] border border-[color-mix(in_srgb,var(--ui-border)_80%,transparent)] bg-[linear-gradient(180deg,color-mix(in_srgb,var(--ui-surface-soft)_82%,transparent),color-mix(in_srgb,var(--ui-surface)_98%,transparent))] px-2.5 py-2 text-[11px] leading-[1.15rem] text-[var(--ui-muted)] shadow-[inset_0_1px_0_color-mix(in_srgb,var(--ui-surface)_90%,transparent)]"
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
                        <span v-if="primaryCardIssue" class="inline-flex max-w-full items-center rounded-full border border-[color-mix(in_srgb,var(--ui-danger)_35%,var(--ui-border))] bg-[color-mix(in_srgb,var(--ui-danger)_12%,var(--ui-surface))] px-2 py-0.5 text-[9px] font-semibold text-[var(--ui-danger)]">
                            <span class="truncate">{{ primaryCardIssue }}</span>
                        </span>
                    </div>
                </div>
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
            class="!h-3.5 !w-3.5 !border-2 !border-[var(--ui-surface)]"
            :class="handleClass"
            :style="{ top: `${handle.top}%` }"
        />
    </div>
</template>

<script setup>
import { Handle, Position } from '@vue-flow/core';
import FlowCanvasNodeHeader from '@/Components/AutomationFlows/FlowCanvasNodeHeader.vue';
import FlowCanvasNodeMenu from '@/Components/AutomationFlows/FlowCanvasNodeMenu.vue';
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
    background: linear-gradient(180deg, var(--ui-surface) 0%, var(--ui-surface-soft) 100%);
}
</style>
