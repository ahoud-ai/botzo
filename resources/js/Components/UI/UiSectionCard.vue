<script setup>
import { computed, useSlots } from 'vue';

const props = defineProps({
    title: {
        type: String,
        default: null,
    },
    subtitle: {
        type: String,
        default: null,
    },
});

const slots = useSlots();
const hasIcon = computed(() => Boolean(slots.icon));
</script>

<template>
    <section class="ui-card">
        <header v-if="title || subtitle || hasIcon" class="ui-section-head">
            <div class="ui-section-head-row">
                <div v-if="title || subtitle" class="ui-section-copy">
                    <h2 v-if="title" class="ui-section-title">{{ title }}</h2>
                    <p v-if="subtitle" class="ui-section-subtitle">{{ subtitle }}</p>
                </div>
                <div v-if="hasIcon" class="ui-section-icon">
                    <slot name="icon" />
                </div>
            </div>
        </header>
        <div class="ui-section-body">
            <slot />
        </div>
    </section>
</template>

<style scoped>
.ui-section-head-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.85rem;
}

.ui-section-copy {
    min-width: 0;
    flex: 1;
}

.ui-section-icon {
    display: inline-flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
}
</style>
