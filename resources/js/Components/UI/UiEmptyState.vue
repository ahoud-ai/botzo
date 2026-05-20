<script setup>
const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: null,
    },
});
</script>

<template>
    <div class="ui-empty">
        <div class="ui-empty-icon">
            <slot name="icon" />
        </div>
        <p class="ui-empty-title">{{ title }}</p>
        <p v-if="description" class="ui-empty-description">{{ description }}</p>
        <div v-if="$slots.actions" class="ui-empty-actions">
            <slot name="actions" />
        </div>
    </div>
</template>
