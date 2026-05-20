<template>
    <header class="border-b border-slate-200 bg-[linear-gradient(180deg,rgba(255,255,255,0.98),rgba(248,250,252,0.94))] px-3 py-2.5">
        <div class="flex items-start justify-between gap-2.5">
            <div class="flow-card-drag-handle min-w-0 flex flex-1 items-start gap-2.5 rounded-2xl pe-1" data-flow-node-drag-handle="true">
                <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-xl border text-slate-700" :class="cardTone.iconClass">
                    <component :is="icon" class="h-3.5 w-3.5" />
                </span>

                <div class="min-w-0 flex-1">
                    <div class="flex min-w-0 flex-wrap items-center gap-1.5">
                        <span class="inline-flex rounded-full border px-2 py-0.5 text-[9px] font-semibold uppercase tracking-[0.14em]" :class="cardTone.categoryClass">{{ categoryLabel }}</span>
                        <span v-if="statusBadge" class="inline-flex rounded-full border px-2 py-0.5 text-[9px] font-semibold" :class="statusBadge.className">{{ statusBadge.label }}</span>
                    </div>

                    <h3 class="mt-1.5 truncate text-[11.5px] font-semibold text-slate-950">{{ title }}</h3>
                </div>
            </div>

            <div class="pointer-events-auto relative z-[2] flex shrink-0 items-center gap-1.5">
                <button
                    type="button"
                    class="nodrag nopan inline-flex h-7 w-7 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 transition hover:border-slate-300 hover:text-slate-900"
                    data-flow-node-action="true"
                    data-flow-node-expand="true"
                    :aria-label="isExpanded ? t('Collapse step') : t('Edit step')"
                    :title="isExpanded ? t('Collapse step') : t('Edit step')"
                    @pointerdown.stop="$emit('action-pointerdown')"
                    @mousedown.stop="$emit('action-pointerdown')"
                    @click.stop="$emit('toggle-inline')"
                >
                    <ChevronUp v-if="isExpanded" class="h-4 w-4" />
                    <ChevronDown v-else class="h-4 w-4" />
                </button>

                <button
                    v-if="!isTrigger"
                    :ref="menuButtonRef"
                    type="button"
                    class="nodrag nopan inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 transition hover:border-slate-300 hover:text-slate-900"
                    data-flow-node-action="true"
                    data-flow-node-menu="true"
                    @pointerdown.stop="$emit('action-pointerdown')"
                    @mousedown.stop="$emit('action-pointerdown')"
                    @click.stop="$emit('menu-click')"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none">
                        <path d="M12 5.5a1.5 1.5 0 1 0 0-3a1.5 1.5 0 0 0 0 3M12 13.5a1.5 1.5 0 1 0 0-3a1.5 1.5 0 0 0 0 3M12 21.5a1.5 1.5 0 1 0 0-3a1.5 1.5 0 0 0 0 3" fill="currentColor" />
                    </svg>
                </button>
            </div>
        </div>
    </header>
</template>

<script setup>
import { ChevronDown, ChevronUp } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

defineProps({
    categoryLabel: { type: String, required: true },
    cardTone: { type: Object, required: true },
    icon: { type: [Object, Function], required: true },
    isExpanded: { type: Boolean, default: false },
    isTrigger: { type: Boolean, default: false },
    menuButtonRef: { type: Function, default: null },
    statusBadge: { type: Object, default: null },
    title: { type: String, required: true },
});

defineEmits(['action-pointerdown', 'menu-click', 'toggle-inline']);
</script>

<style scoped>
.flow-card-drag-handle {
    touch-action: none;
    cursor: grab;
    user-select: none;
}
</style>
