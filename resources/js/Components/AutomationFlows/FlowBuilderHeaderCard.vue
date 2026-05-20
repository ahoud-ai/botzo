<template>
    <header class="relative z-30 rounded-[28px] border border-slate-200/90 bg-[linear-gradient(135deg,rgba(255,255,255,0.98),rgba(248,250,252,0.96))] px-4 py-3.5 shadow-sm backdrop-blur">
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
                        <span class="inline-flex items-center rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                            {{ $t('Journey studio') }}
                        </span>
                        <AutomationStatusBadge :status="draft.status || 'draft'" />
                    </div>
                    <div class="mt-2 flex min-w-0 flex-wrap items-center gap-2">
                        <h2 class="truncate text-lg font-semibold text-slate-950">{{ draft.name }}</h2>
                        <span class="inline-flex items-center rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-medium text-slate-500">
                            {{ saveStateLabel }}
                        </span>
                        <span v-if="validationErrorsCount" class="inline-flex items-center gap-1.5 rounded-full border border-rose-200 bg-rose-50 px-2.5 py-1 text-[11px] font-medium text-rose-700">
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
            class="fixed z-[2400] w-64 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_18px_48px_rgba(15,23,42,0.14)]"
            :style="menuStyle"
        >
            <button type="button" class="flex w-full items-center px-4 py-3 text-sm text-slate-700 transition hover:bg-slate-50" @click="emitMenuAction('run-validation')">{{ $t('Run validation') }}</button>
            <button type="button" class="flex w-full items-center gap-2 px-4 py-3 text-sm text-slate-700 transition hover:bg-slate-50" @click="emitMenuAction('open-meta-editor')">
                <PenLine class="h-4 w-4" />
                {{ $t('Edit') }}
            </button>
            <button v-if="showOpenStudioAction" type="button" class="flex w-full items-center gap-2 px-4 py-3 text-sm text-slate-700 transition hover:bg-slate-50" @click="emitMenuAction('open-studio-window')">
                <ArrowUpRight class="h-4 w-4" />
                {{ $t('Open studio in new window') }}
            </button>
            <button type="button" class="flex w-full items-center gap-2 px-4 py-3 text-sm text-slate-700 transition hover:bg-slate-50" @click="emitMenuAction('duplicate')">
                <Copy class="h-4 w-4" />
                {{ $t('Duplicate') }}
            </button>
            <button type="button" class="flex w-full items-center px-4 py-3 text-sm text-slate-700 transition hover:bg-slate-50" @click="emitMenuAction('toggle-pause')">{{ draft.status === 'paused' ? $t('Resume publishing') : $t('Pause new runs') }}</button>
            <button type="button" class="flex w-full items-center px-4 py-3 text-sm text-rose-600 transition hover:bg-rose-50" @click="emitMenuAction('delete')">{{ $t('Delete automation') }}</button>
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
