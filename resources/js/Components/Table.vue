<script setup>
    import Pagination from '@/Components/Pagination.vue';

    const props = defineProps({
        rows: Object,
        className: String,
    })
</script>
<template>
    <div class="ui-table-shell" :class="className">
        <table class="ui-table">
            <slot />
        </table>
    </div>

    <Pagination class="mt-3" :pagination="rows.meta"/>
</template>
