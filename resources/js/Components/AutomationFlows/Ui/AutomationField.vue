<script setup>
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    label: {
        type: String,
        default: '',
    },
    hint: {
        type: String,
        default: '',
    },
    error: {
        type: String,
        default: '',
    },
    required: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <label class="block">
        <div v-if="props.label" class="mb-2 flex items-center gap-1 text-sm font-semibold text-[var(--ui-text)]">
            <span>{{ t(props.label) }}</span>
            <span v-if="props.required" class="text-[var(--ui-danger)]">*</span>
        </div>
        <slot />
        <div v-if="props.hint" class="mt-2 text-xs leading-5 text-[var(--ui-muted)]">{{ t(props.hint) }}</div>
        <div v-if="props.error" class="mt-2 text-xs font-medium text-[var(--ui-danger)]">{{ t(props.error) }}</div>
    </label>
</template>
