<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { Copy, MoreVertical, Pencil, Trash2 } from 'lucide-vue-next';

const emit = defineEmits(['open', 'duplicate', 'delete']);

const rootRef = ref(null);
const panelRef = ref(null);
const menuOpen = ref(false);
const panelStyle = ref({
    top: '0px',
    left: '0px',
    minWidth: '176px',
});

const closeMenu = () => {
    menuOpen.value = false;
};

const calculatePlacement = () => {
    const trigger = rootRef.value;
    const panel = panelRef.value;

    if (!trigger || !panel || typeof window === 'undefined') {
        return;
    }

    const triggerRect = trigger.getBoundingClientRect();
    const menuWidth = Math.max(panel.offsetWidth || 0, 176);
    const menuHeight = Math.max(panel.offsetHeight || 0, 170);
    const spaceBelow = window.innerHeight - triggerRect.bottom;
    const spaceAbove = triggerRect.top;
    const placeTop = spaceBelow < (menuHeight + 12) && spaceAbove > spaceBelow;

    const top = placeTop
        ? Math.max(8, triggerRect.top - menuHeight - 8)
        : Math.max(8, Math.min(window.innerHeight - menuHeight - 8, triggerRect.bottom + 8));
    const left = Math.min(
        Math.max(8, triggerRect.right - menuWidth),
        Math.max(8, window.innerWidth - menuWidth - 8),
    );

    panelStyle.value = {
        top: `${Math.round(top)}px`,
        left: `${Math.round(left)}px`,
        minWidth: `${Math.round(menuWidth)}px`,
    };
};

const openMenu = async () => {
    menuOpen.value = true;
    await nextTick();
    calculatePlacement();
};

const toggleMenu = async () => {
    if (menuOpen.value) {
        closeMenu();
        return;
    }

    await openMenu();
};

const emitAction = (name) => {
    closeMenu();
    emit(name);
};

const handleDocumentPointerDown = (event) => {
    if (!menuOpen.value) {
        return;
    }

    if (rootRef.value?.contains(event.target)) {
        return;
    }

    if (panelRef.value?.contains(event.target)) {
        return;
    }

    closeMenu();
};

const handleEscape = (event) => {
    if (event.key === 'Escape') {
        closeMenu();
    }
};

const handleViewportMutation = () => {
    if (menuOpen.value) {
        calculatePlacement();
    }
};

onMounted(() => {
    window.addEventListener('pointerdown', handleDocumentPointerDown);
    window.addEventListener('keydown', handleEscape);
    window.addEventListener('resize', handleViewportMutation);
    window.addEventListener('scroll', handleViewportMutation, true);
});

onBeforeUnmount(() => {
    window.removeEventListener('pointerdown', handleDocumentPointerDown);
    window.removeEventListener('keydown', handleEscape);
    window.removeEventListener('resize', handleViewportMutation);
    window.removeEventListener('scroll', handleViewportMutation, true);
});
</script>

<template>
    <div ref="rootRef" class="relative inline-flex">
        <button
            type="button"
            class="inline-flex rounded-xl border border-[var(--ui-border)] bg-[var(--ui-surface)] p-2 text-[var(--ui-muted)] transition hover:border-[var(--ui-border-strong)] hover:text-[var(--ui-text)]"
            @click="toggleMenu"
        >
            <MoreVertical class="h-4 w-4" />
        </button>
    </div>

    <Teleport to="body">
        <div
            v-if="menuOpen"
            ref="panelRef"
            class="ui-layer-dropdown fixed z-[120] w-44 overflow-hidden rounded-2xl border border-[var(--ui-border)] bg-[var(--ui-surface)] shadow-[0_18px_60px_color-mix(in_srgb,var(--ui-text)_12%,transparent)]"
            :style="panelStyle"
        >
            <button type="button" class="flex w-full items-center gap-2 px-4 py-3 text-sm text-[var(--ui-text)] transition hover:bg-[var(--ui-surface-soft)]" @click="emitAction('open')">
                <Pencil class="h-4 w-4" />
                <span>{{ $t('Edit') }}</span>
            </button>
            <button type="button" class="flex w-full items-center gap-2 px-4 py-3 text-sm text-[var(--ui-text)] transition hover:bg-[var(--ui-surface-soft)]" @click="emitAction('duplicate')">
                <Copy class="h-4 w-4" />
                <span>{{ $t('Copy') }}</span>
            </button>
            <button type="button" class="flex w-full items-center gap-2 px-4 py-3 text-sm text-[var(--ui-danger)] transition hover:bg-[color-mix(in_srgb,var(--ui-danger)_10%,transparent)]" @click="emitAction('delete')">
                <Trash2 class="h-4 w-4" />
                <span>{{ $t('Delete') }}</span>
            </button>
        </div>
    </Teleport>
</template>
