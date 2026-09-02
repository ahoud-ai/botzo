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
    draft: 'border border-[color-mix(in_srgb,var(--ui-warning)_35%,var(--ui-border))] bg-[color-mix(in_srgb,var(--ui-warning)_12%,var(--ui-surface))] text-[var(--ui-warning)]',
    published: 'border border-[color-mix(in_srgb,var(--ui-success)_35%,var(--ui-border))] bg-[color-mix(in_srgb,var(--ui-success)_12%,var(--ui-surface))] text-[var(--ui-success)]',
    paused: 'border border-[var(--ui-border-strong)] bg-[var(--ui-surface-soft)] text-[var(--ui-muted)]',
    archived: 'border border-[color-mix(in_srgb,var(--ui-danger)_35%,var(--ui-border))] bg-[color-mix(in_srgb,var(--ui-danger)_12%,var(--ui-surface))] text-[var(--ui-danger)]',
}[props.status] || 'border border-[var(--ui-border)] bg-[var(--ui-surface-soft)] text-[var(--ui-muted)]'));

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
