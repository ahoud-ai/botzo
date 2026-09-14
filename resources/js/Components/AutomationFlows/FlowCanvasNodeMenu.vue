<template>
    <Teleport to="body">
        <div
            v-if="open && !isTrigger"
            class="flow-node-menu-layer fixed z-[2200] w-44 overflow-hidden rounded-xl border border-[var(--ui-border)] bg-[var(--ui-surface)] shadow-[var(--ui-shadow-1)]"
            :style="style"
            data-flow-node-action="true"
            @pointerdown.stop="$emit('action-pointerdown')"
            @mousedown.stop="$emit('action-pointerdown')"
        >
            <button type="button" class="nodrag flex w-full items-center px-4 py-3 text-sm text-[var(--ui-text)] transition hover:bg-[var(--ui-surface-soft)]" @click="$emit('duplicate')">
                {{ $t('Duplicate') }}
            </button>
            <button type="button" class="nodrag flex w-full items-center px-4 py-3 text-sm text-[var(--ui-text)] transition hover:bg-[var(--ui-surface-soft)]" @click="$emit('rename')">
                {{ $t('Rename') }}
            </button>
            <button type="button" class="nodrag flex w-full items-center px-4 py-3 text-sm text-[var(--ui-danger)] transition hover:bg-[color-mix(in_srgb,var(--ui-danger)_10%,transparent)]" @click="$emit('delete')">
                {{ $t('Delete') }}
            </button>
        </div>
    </Teleport>
</template>

<script setup>
defineProps({
    isTrigger: { type: Boolean, default: false },
    open: { type: Boolean, default: false },
    style: { type: Object, required: true },
});

defineEmits(['action-pointerdown', 'delete', 'duplicate', 'rename']);
</script>

<style scoped>
.flow-node-menu-layer {
    max-width: calc(100vw - 16px);
}
</style>
