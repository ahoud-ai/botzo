<script setup>
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
    rows: {
        type: Object,
        default: null,
    },
    className: {
        type: String,
        default: null,
    },
});
</script>

<template>
    <div class="ui-table-shell" :class="className">
        <table class="ui-table">
            <slot />
        </table>
    </div>

    <Pagination
        v-if="rows && rows.meta"
        class="mt-4"
        :pagination="rows.meta"
    />
</template>
