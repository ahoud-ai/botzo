<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    status: {
        type: String,
        default: 'draft',
    },
});

const { t } = useI18n();

const classes = computed(() => ({
    draft: 'border border-amber-200 bg-amber-50 text-amber-700',
    published: 'border border-emerald-200 bg-emerald-50 text-emerald-700',
    paused: 'border border-slate-200 bg-slate-100 text-slate-700',
    archived: 'border border-rose-200 bg-rose-50 text-rose-700',
}[props.status] || 'border border-slate-200 bg-slate-50 text-slate-700'));

const label = computed(() => ({
    draft: t('Draft'),
    published: t('Published'),
    paused: t('Paused'),
    archived: t('Archived'),
}[props.status] || props.status));
</script>

<template>
    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold" :class="classes">
        {{ label }}
    </span>
</template>
