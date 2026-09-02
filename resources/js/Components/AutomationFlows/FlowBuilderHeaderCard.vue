<template>
    <header class="relative z-30 rounded-[28px] border border-[color-mix(in_srgb,var(--ui-border)_90%,transparent)] bg-[var(--flow-panel-bg)] px-4 py-3.5 shadow-[var(--ui-shadow-1)] backdrop-blur">
        <div class="flex flex-wrap items-center gap-3.5">
            <div class="flex min-w-0 flex-1 items-center gap-3">
                <AutomationButton
                    size="icon"
                    variant="secondary"
                    :title="$t('Go Back')"
                    @click="$emit('go-back')"
                >
                    <ArrowLeft class="h-4 w-4" />
                </AutomationButton>

                <div class="min-w-0 flex-1">
                    <div class="flex min-w-0 flex-wrap items-center gap-2">
                        <span class="inline-flex items-center rounded-full border border-[var(--ui-border)] bg-[var(--ui-surface)] px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-[var(--ui-muted)]">
                            {{ $t('Journey studio') }}
                        </span>
                        <AutomationStatusBadge :status="draft.status || 'draft'" />
                    </div>
                    <div class="mt-2 flex min-w-0 flex-wrap items-center gap-2">
                        <h2 class="truncate text-lg font-semibold text-[var(--ui-text)]">{{ draft.name }}</h2>
                        <span class="inline-flex items-center rounded-full border border-[var(--ui-border)] bg-[var(--ui-surface)] px-2.5 py-1 text-[11px] font-medium text-[var(--ui-muted)]">
                            {{ saveStateLabel }}
                        </span>
                        <span v-if="validationErrorsCount" class="inline-flex items-center gap-1.5 rounded-full border border-[color-mix(in_srgb,var(--ui-danger)_35%,var(--ui-border))] bg-[color-mix(in_srgb,var(--ui-danger)_12%,var(--ui-surface))] px-2.5 py-1 text-[11px] font-medium text-[var(--ui-danger)]">
                            <CircleAlert class="h-3.5 w-3.5" />
                            {{ validationErrorsCount }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="flex shrink-0 flex-wrap items-center gap-2 self-start">
                <AutomationButton size="sm" variant="secondary" @click="$emit('save')">{{ $t('Save') }}</AutomationButton>
                <AutomationButton size="sm" variant="secondary" @click="$emit('open-preview')">
                    <Smartphone class="h-4 w-4" />
                    {{ $t('Preview') }}
                </AutomationButton>
                <AutomationButton size="sm" variant="primary" @click="$emit('publish')">{{ $t('Publish') }}</AutomationButton>
                <div ref="menuTriggerRef" class="inline-flex">
                    <AutomationButton size="icon" variant="secondary" :title="$t('More')" @click="toggleMoreMenu">
                        <MoreHorizontal class="h-4 w-4" />
                    </AutomationButton>
                </div>
            </div>
        </div>
    </header>
    <Teleport to="body">
        <div
            v-if="moreOpen"
            ref="menuPanelRef"
            class="fixed z-[2400] w-64 overflow-hidden rounded-2xl border border-[var(--ui-border)] bg-[var(--ui-surface)] shadow-[0_18px_48px_color-mix(in_srgb,var(--ui-text)_14%,transparent)]"
            :style="menuStyle"
        >
            <button type="button" class="flex w-full items-center px-4 py-3 text-sm text-[var(--ui-text)] transition hover:bg-[var(--ui-surface-soft)]" @click="emitMenuAction('run-validation')">{{ $t('Run validation') }}</button>
            <button type="button" class="flex w-full items-center gap-2 px-4 py-3 text-sm text-[var(--ui-text)] transition hover:bg-[var(--ui-surface-soft)]" @click="emitMenuAction('open-meta-editor')">
                <PenLine class="h-4 w-4" />
                {{ $t('Edit') }}
            </button>
            <button v-if="showOpenStudioAction" type="button" class="flex w-full items-center gap-2 px-4 py-3 text-sm text-[var(--ui-text)] transition hover:bg-[var(--ui-surface-soft)]" @click="emitMenuAction('open-studio-window')">
                <ArrowUpRight class="h-4 w-4" />
                {{ $t('Open studio in new window') }}
            </button>
            <button type="button" class="flex w-full items-center gap-2 px-4 py-3 text-sm text-[var(--ui-text)] transition hover:bg-[var(--ui-surface-soft)]" @click="emitMenuAction('duplicate')">
                <Copy class="h-4 w-4" />
                {{ $t('Duplicate') }}
            </button>
            <button type="button" class="flex w-full items-center px-4 py-3 text-sm text-[var(--ui-text)] transition hover:bg-[var(--ui-surface-soft)]" @click="emitMenuAction('toggle-pause')">{{ draft.status === 'paused' ? $t('Resume publishing') : $t('Pause new runs') }}</button>
            <button type="button" class="flex w-full items-center px-4 py-3 text-sm text-[var(--ui-danger)] transition hover:bg-[color-mix(in_srgb,var(--ui-danger)_10%,transparent)]" @click="emitMenuAction('delete')">{{ $t('Delete automation') }}</button>
        </div>
    </Teleport>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { ArrowLeft, ArrowUpRight, CircleAlert, Copy, MoreHorizontal, PenLine, Smartphone } from 'lucide-vue-next';
import AutomationButton from '@/Components/AutomationFlows/Ui/AutomationButton.vue';
import AutomationStatusBadge from '@/Components/AutomationFlows/Ui/AutomationStatusBadge.vue';

const props = defineProps({
    draft: {
        type: Object,
        required: true,
    },
    saveStateLabel: {
        type: String,
        default: '',
    },
    validationErrorsCount: {
        type: Number,
        default: 0,
    },
    moreOpen: {
        type: Boolean,
        default: false,
    },
    showOpenStudioAction: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits([
    'update:moreOpen',
    'go-back',
    'save',
    'open-preview',
    'publish',
    'open-meta-editor',
    'open-studio-window',
    'run-validation',
    'duplicate',
    'toggle-pause',
    'delete',
]);

const menuTriggerRef = ref(null);
const menuPanelRef = ref(null);
const menuPosition = ref({ top: 0, left: 0 });

const menuStyle = computed(() => ({
    top: `${menuPosition.value.top}px`,
    left: `${menuPosition.value.left}px`,
}));

const closeMoreMenu = () => {
    if (props.moreOpen) {
        emit('update:moreOpen', false);
    }
};

const toggleMoreMenu = () => {
    emit('update:moreOpen', !props.moreOpen);
    if (!props.moreOpen) {
        void nextTick(() => {
            syncMenuPosition();
        });
    }
};

const emitMenuAction = (action) => {
    closeMoreMenu();
    emit(action);
};

const resolveElement = (target) => {
    if (!target) {
        return null;
    }

    if (target instanceof HTMLElement) {
        return target;
    }

    return target?.$el instanceof HTMLElement ? target.$el : null;
};

const syncMenuPosition = () => {
    const triggerEl = resolveElement(menuTriggerRef.value);
    if (!triggerEl || typeof window === 'undefined') {
        return;
    }

    const rect = triggerEl.getBoundingClientRect();
    const panelWidth = menuPanelRef.value?.offsetWidth || 256;
    const viewportWidth = window.innerWidth || 0;
    const left = Math.max(12, Math.min(rect.right - panelWidth, viewportWidth - panelWidth - 12));

    menuPosition.value = {
        top: Math.round(rect.bottom + 10),
        left: Math.round(left),
    };
};

const handlePointerDown = (event) => {
    if (!props.moreOpen) return;
    if (resolveElement(menuTriggerRef.value)?.contains(event.target)) return;
    if (menuPanelRef.value?.contains(event.target)) return;
    closeMoreMenu();
};

const handleEscape = (event) => {
    if (event.key === 'Escape') {
        closeMoreMenu();
    }
};

const handleViewportUpdate = () => {
    if (!props.moreOpen) {
        return;
    }

    syncMenuPosition();
};

watch(() => props.moreOpen, (isOpen) => {
    if (!isOpen) {
        return;
    }

    void nextTick(() => {
        syncMenuPosition();
    });
});

onMounted(() => {
    window.addEventListener('pointerdown', handlePointerDown);
    window.addEventListener('keydown', handleEscape);
    window.addEventListener('resize', handleViewportUpdate);
    window.addEventListener('scroll', handleViewportUpdate, true);
});

onBeforeUnmount(() => {
    window.removeEventListener('pointerdown', handlePointerDown);
    window.removeEventListener('keydown', handleEscape);
    window.removeEventListener('resize', handleViewportUpdate);
    window.removeEventListener('scroll', handleViewportUpdate, true);
});
</script>
