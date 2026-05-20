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
    <section class="ui-form-section">
        <div class="ui-form-section-head">
            <h3 class="ui-form-section-title">{{ title }}</h3>
            <p v-if="description" class="ui-form-section-description">{{ description }}</p>
        </div>
        <div class="ui-form-section-body">
            <slot />
        </div>
    </section>
</template>
