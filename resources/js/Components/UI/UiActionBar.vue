<script setup>
const props = defineProps({
    stackedOnMobile: {
        type: Boolean,
        default: true,
    },
});
</script>

<template>
    <div class="ui-action-bar" :class="{ 'ui-action-bar-inline': !stackedOnMobile }">
        <slot />
    </div>
</template>
