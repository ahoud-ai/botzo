<script setup>
    const props = defineProps({
        position: {
            type: String,
            default: null
        },
    })

    const getRowClass = () => {
        if (props.position === 'first') {
            return 'ui-table-th ui-table-th-first';
        } else if (props.position === 'last') {
            return 'ui-table-th ui-table-th-last';
        } else {
            return 'ui-table-th';
        }
    };
</script>
<template>
    <th scope="col" :class="getRowClass()">
        <slot/>
    </th>
</template>
