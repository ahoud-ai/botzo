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
        <div v-if="props.label" class="mb-2 flex items-center gap-1 text-sm font-semibold text-slate-900">
            <span>{{ t(props.label) }}</span>
            <span v-if="props.required" class="text-rose-500">*</span>
        </div>
        <slot />
        <div v-if="props.hint" class="mt-2 text-xs leading-5 text-slate-500">{{ t(props.hint) }}</div>
        <div v-if="props.error" class="mt-2 text-xs font-medium text-rose-600">{{ t(props.error) }}</div>
    </label>
</template>
