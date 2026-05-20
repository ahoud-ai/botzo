<script setup>
    const props = defineProps({
        position: {
            type: String,
            default: null
        },
    })

    const getRowClass = () => {
        if (props.position === 'first') {
            return 'ui-table-td ui-table-td-first';
        } else if (props.position === 'last') {
            return 'ui-table-td ui-table-td-last';
        }

        return 'ui-table-td';
    };

    const getCellClass = () => {
        if (props.position === 'last') {
            return 'ui-table-cell ui-table-cell-end';
        }

        return 'ui-table-cell';
    };
</script>
<template>
    <td :class="getRowClass()">
        <div :class="getCellClass()"><slot /></div>
    </td>
</template>
