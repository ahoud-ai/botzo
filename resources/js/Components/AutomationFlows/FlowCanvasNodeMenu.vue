<template>
    <Teleport to="body">
        <div
            v-if="open && !isTrigger"
            class="flow-node-menu-layer fixed z-[2200] w-44 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
            :style="style"
            data-flow-node-action="true"
            @pointerdown.stop="$emit('action-pointerdown')"
            @mousedown.stop="$emit('action-pointerdown')"
        >
            <button type="button" class="nodrag flex w-full items-center px-4 py-3 text-sm text-slate-700 transition hover:bg-slate-50" @click="$emit('duplicate')">
                {{ $t('Duplicate') }}
            </button>
            <button type="button" class="nodrag flex w-full items-center px-4 py-3 text-sm text-slate-700 transition hover:bg-slate-50" @click="$emit('rename')">
                {{ $t('Rename') }}
            </button>
            <button type="button" class="nodrag flex w-full items-center px-4 py-3 text-sm text-rose-600 transition hover:bg-rose-50" @click="$emit('delete')">
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
