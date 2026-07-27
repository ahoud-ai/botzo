<template>
    <Transition name="field-error">
        <p v-if="message" class="self-start inline-flex items-center gap-1.5 mt-1.5 px-3 py-1.5 rounded-full bg-red-50 dark:bg-red-500/15 text-red-600 dark:text-red-400 text-xs font-semibold">
            <ExclamationCircleIcon class="w-3.5 h-3.5 shrink-0" />
            <span>{{ message }}</span>
        </p>
    </Transition>
</template>

<script setup>
    import { ExclamationCircleIcon } from '@heroicons/vue/24/solid';

    defineProps({
        message: String,
    });
</script>

<style scoped>
    .field-error-enter-active,
    .field-error-leave-active {
        transition: opacity 0.18s ease, transform 0.18s ease;
    }
    .field-error-enter-from,
    .field-error-leave-to {
        opacity: 0;
        transform: translateY(-4px);
    }
</style>
