<template>
    <div class="relative">
        <Handle
            v-if="!isTrigger"
            id="target"
            type="target"
            :position="Position.Left"
            class="!h-4 !w-4 !border-2 !border-white !bg-primary shadow-sm"
        />

        <article
            class="w-[280px] rounded-[24px] border bg-white shadow-[0_12px_38px_rgba(15,23,42,0.08)] transition"
            :class="selected ? 'border-primary ring-2 ring-primary/10' : 'border-slate-200 hover:border-slate-300'"
        >
            <header class="flow-card-drag-handle flex cursor-grab items-start justify-between gap-3 border-b border-slate-100 px-4 py-4 active:cursor-grabbing">
                <div class="min-w-0">
                    <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">
                        <GripVertical class="h-4 w-4 cursor-grab text-slate-400" />
                        <component :is="IconComponent" class="h-4 w-4" />
                        <span>{{ categoryLabel }}</span>
                    </div>
                    <div class="mt-2 flex items-center gap-2">
                        <h3 class="truncate text-base font-semibold text-slate-950">{{ displayTitle }}</h3>
                        <span v-if="isInactive" class="inline-flex rounded-full bg-slate-100 px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                            {{ $t('Inactive') }}
                        </span>
                    </div>
                </div>

                <div v-if="errorCount" class="inline-flex rounded-full bg-rose-50 px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.12em] text-rose-600">
                    {{ errorCount }}
                </div>
            </header>

            <div class="px-4 py-4">
                <p v-if="summaryText" class="max-h-[72px] overflow-hidden text-sm leading-6 text-slate-500">{{ summaryText }}</p>
                <div v-if="errors.length" class="mt-3 rounded-2xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs leading-5 text-rose-700">
                    {{ errors[0] }}
                </div>
            </div>
        </article>

        <Handle
            v-for="handle in sourceHandles"
            :key="handle.id"
            :id="handle.id"
            type="source"
            :position="Position.Right"
            class="!h-4 !w-4 !border-2 !border-white !bg-primary shadow-sm"
            :style="{ top: `${handle.top}%` }"
        />
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Handle, Position } from '@vue-flow/core';
import { GripVertical } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { flowNodeCategory, flowNodeHasActiveToggle, flowNodeIcon, flowNodeSummary } from '@/Components/AutomationFlows/flowBuilderMeta.js';

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

const { t } = useI18n();

const nodeType = computed(() => props.data?.nodeType || '');
const config = computed(() => props.data?.config || {});
const asset = computed(() => props.data?.asset || null);
const errors = computed(() => props.data?.errors || []);
const isTrigger = computed(() => nodeType.value === 'trigger');
const isInactive = computed(() => flowNodeHasActiveToggle(nodeType.value) && config.value.active === false);
const categoryLabel = computed(() => flowNodeCategory(nodeType.value, t));
const displayTitle = computed(() => props.data?.title || props.data?.label || nodeType.value);
const summaryText = computed(() => flowNodeSummary(nodeType.value, config.value, { asset: asset.value }, t));
const IconComponent = computed(() => flowNodeIcon(nodeType.value));
const errorCount = computed(() => errors.value.length || 0);

const sourceHandles = computed(() => {
    if (nodeType.value === 'end') {
        return [];
    }

    if (nodeType.value === 'condition') {
        return distributeHandles([
            { id: 'matched' },
            { id: 'unmatched' },
        ]);
    }

    if (nodeType.value === 'send_buttons') {
        return distributeHandles(
            (config.value.buttons || [])
                .filter((button) => button.id)
                .map((button) => ({ id: button.id })),
        );
    }

    if (nodeType.value === 'send_list') {
        return distributeHandles(
            (config.value.sections || [])
                .flatMap((section) => section.rows || [])
                .filter((row) => row.id)
                .map((row) => ({ id: row.id })),
        );
    }

    return [{ id: 'default', top: 50 }];
});

const distributeHandles = (items) => {
    if (!items.length) {
        return [];
    }

    if (items.length === 1) {
        return [{ ...items[0], top: 50 }];
    }

    return items.map((item, index) => ({
        ...item,
        top: Math.round((index + 1) * (100 / (items.length + 1))),
    }));
};
</script>
