<script setup>
const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    subtitle: {
        type: String,
        default: null,
    },
    compact: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <header class="ui-page-header" :class="{ 'ui-page-header-compact': compact }">
        <div>
            <h1 class="ui-page-title">{{ title }}</h1>
            <p v-if="subtitle" class="ui-page-subtitle">{{ subtitle }}</p>
        </div>
        <div v-if="$slots.actions" class="ui-page-actions">
            <slot name="actions" />
        </div>
    </header>
</template>
