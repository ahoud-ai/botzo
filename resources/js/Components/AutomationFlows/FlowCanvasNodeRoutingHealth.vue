<template>
    <div class="flex flex-wrap items-center gap-1.5">
        <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[10px] font-semibold text-slate-600">
            {{ branchCoverage.linked }} / {{ branchCoverage.total }} {{ $t('Linked') }}
        </span>
        <span
            v-for="branch in visibleBranchPreviewItems"
            :key="branch.id"
            class="inline-flex max-w-full items-center gap-1.5 rounded-full border px-2 py-1 text-[10px] font-semibold"
            :class="branch.connected ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700'"
        >
            <span class="max-w-[74px] truncate">{{ branch.label }}</span>
            <span>{{ branch.connected ? $t('Linked') : $t('Needs link') }}</span>
        </span>
        <span v-if="hiddenBranchPreviewCount > 0" class="inline-flex items-center rounded-full border border-slate-200 bg-white px-2 py-1 text-[10px] font-semibold text-slate-600">
            {{ hiddenBranchPreviewLabel }}
        </span>
    </div>
</template>

<script setup>
defineProps({
    branchCoverage: { type: Object, required: true },
    hiddenBranchPreviewCount: { type: Number, default: 0 },
    hiddenBranchPreviewLabel: { type: String, default: '' },
    visibleBranchPreviewItems: { type: Array, default: () => [] },
});
</script>
