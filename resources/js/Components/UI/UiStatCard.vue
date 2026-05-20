<script setup>
import { computed, useSlots } from 'vue';

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    value: {
        type: [String, Number],
        required: true,
    },
    ctaLabel: {
        type: String,
        default: null,
    },
    ctaHref: {
        type: String,
        default: null,
    },
});

const slots = useSlots();
const hasIcon = computed(() => Boolean(slots.icon));
</script>

<template>
    <article class="ui-kpi">
        <div class="ui-kpi-head">
        <div class="ui-kpi-text">
            <p class="ui-kpi-title">{{ title }}</p>
            <p class="ui-kpi-value">{{ value }}</p>
        </div>
        <span v-if="hasIcon" class="ui-kpi-icon">
            <slot name="icon" />
        </span>
    </div>
        <div v-if="ctaLabel && ctaHref" class="ui-kpi-footer">
            <a :href="ctaHref" class="ui-kpi-link">{{ ctaLabel }}</a>
        </div>
    </article>
</template>
