<script setup>
    import AlertModal from '@/Components/AlertModal.vue';
    import { useForm } from "@inertiajs/vue3";
    import { useAlertModal } from '@/Composables/useAlertModal';
    import Table from '@/Components/Table.vue';
    import TableHeader from '@/Components/TableHeader.vue';
    import TableHeaderRow from '@/Components/TableHeaderRow.vue';
    import TableHeaderRowItem from '@/Components/TableHeaderRowItem.vue';
    import TableBody from '@/Components/TableBody.vue';
    import TableBodyRow from '@/Components/TableBodyRow.vue';
    import TableBodyRowItem from '@/Components/TableBodyRowItem.vue';
    import Dropdown from '@/Components/Dropdown.vue';
    import DropdownItemGroup from '@/Components/DropdownItemGroup.vue';
    import DropdownItem from '@/Components/DropdownItem.vue';
    import Pagination from '@/Components/Pagination.vue';
    import draggable from 'vuedraggable';
    import axios from 'axios';

    const props = defineProps({
        rows: {
            type: Object,
            required: true,
        },
    });

    const { isOpenAlert, openAlert, confirmAlert } = useAlertModal();

    const emit = defineEmits(['edit', 'delete']);

    const form = useForm({'test': null});
    
    function edit(id) {
        emit('edit', id);
    }

    function deleteItem(id) {
        emit('delete', id);
    }

    const deleteAction = (key) => {
        form.delete('/contact-fields/' + key);
    }

    const isLastRow = (index) => {
        return index === props.rows.data.length - 1;
    }

    const list = props.rows.data;

    const capitalizeFirstLetter = (string) => {
        return string.charAt(0).toUpperCase() + string.slice(1);
    };

    // Handle drag end event to save new order
    const onDragEnd = async () => {
        // Get the new order of UUIDs
        const orderedIds = props.rows.data.map(item => item.uuid);
        
        try {
            const response = await axios.post('/contact-fields/update-positions', {
                orderedIds: orderedIds
            });
            
            // Show success message
            if (response.data.success) {
                // Success - order updated
            }
        } catch (error) {
            console.error('Error updating contact field order:', error);
            // Optionally show error message to user
        }
    };
</script>
<template>
    <div>
        <div class="ui-table-shell">
            <table class="ui-table">
                <TableHeader>
                    <TableHeaderRow>
                        <TableHeaderRowItem :position="'first'">{{ $t('Input field name') }}</TableHeaderRowItem>
                        <TableHeaderRowItem>{{ $t('Input type') }}</TableHeaderRowItem>
                        <TableHeaderRowItem>{{ $t('Is required') }}</TableHeaderRowItem>
                        <TableHeaderRowItem :position="'last'"></TableHeaderRowItem>
                    </TableHeaderRow>
                </TableHeader>
                <draggable
                    tag="tbody"
                    :list="rows.data"
                    handle=".handle"
                    item-key="uuid"
                    @update="onDragEnd"
                >
                    <template #item="{ element, index }">
                        <tr>
                            <TableBodyRowItem :position="'first'">
                                <div class="flex items-center">
                                    <div class="handle cursor-grab text-[var(--ui-muted)] me-3">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M9 19.23q-.508 0-.87-.36q-.36-.362-.36-.87t.36-.87t.87-.36t.87.36q.36.362.36.87t-.36.87t-.87.36m6 0q-.508 0-.87-.36q-.36-.362-.36-.87t.36-.87t.87-.36t.87.36q.36.362.36.87t-.36.87t-.87.36m-6-6q-.508 0-.87-.36q-.36-.362-.36-.87t.36-.87t.87-.36t.87.36q.36.362.36.87t-.36.87t-.87.36m6 0q-.508 0-.87-.36q-.36-.362-.36-.87t.36-.87t.87-.36t.87.36q.36.362.36.87t-.36.87t-.87.36m-6-6q-.508 0-.87-.36q-.36-.362-.36-.87t.36-.87t.87-.36t.87.36q.36.362.36.87t-.36.87t-.87.36m6 0q-.508 0-.87-.36q-.36-.362-.36-.87t.36-.87t.87-.36t.87.36q.36.362.36.87t-.36.87t-.87.36"/></svg>
                                    </div>
                                    <span class="font-bold text-[16px] text-[var(--ui-text)]">{{ element.name }}</span>
                                </div>
                            </TableBodyRowItem>
                            <TableBodyRowItem class="capitalize">
                                <span class="me-2 text-[var(--ui-muted)]">{{ $t(capitalizeFirstLetter(element.type)) }}</span>
                                <span v-if="element.type === 'input'" class="ui-chip-neutral px-2 py-1 rounded-lg text-xs font-semibold capitalize">{{ $t(capitalizeFirstLetter(element.value)) }}</span>
                            </TableBodyRowItem>
                            <TableBodyRowItem class="capitalize text-[var(--ui-muted)]">{{ element.required === 0 ? $t('No') : $t('Yes') }}</TableBodyRowItem>
                            <TableBodyRowItem :position="'last'">
                                <Dropdown :align="'right'">
                                <button type="submit" class="settings-field-toolbar-btn">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24">
                                        <path fill="currentColor" d="M12 16a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2a2 2 0 0 1 2-2m0-6a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2a2 2 0 0 1 2-2m0-6a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2a2 2 0 0 1 2-2Z"/>
                                    </svg>
                                </button>
                                <template #items>
                                    <DropdownItemGroup>
                                        <DropdownItem as="button" @click="edit(element.uuid)">{{ $t('Edit') }}</DropdownItem>
                                        <DropdownItem as="button" @click="openAlert(element.uuid)">{{ $t('Delete') }}</DropdownItem>
                                    </DropdownItemGroup>
                                </template>
                                </Dropdown>
                            </TableBodyRowItem>
                        </tr>
                    </template>
                </draggable>
            </table>
        </div>
        <Pagination class="mt-3" :pagination="rows.meta"/>
    </div>

    <!-- Alert Modal Component-->
    <AlertModal
        v-model="isOpenAlert"
        @confirm="() => confirmAlert(deleteAction)"
        :label = "$t('Delete row')"
        :description = "$t('Are you sure you want to delete this row? This action can not be undone')"
    />
</template>

<style scoped>
.settings-field-toolbar-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.1rem;
    height: 2.1rem;
    border-radius: 999px;
    color: var(--ui-muted);
    background: transparent;
    cursor: pointer;
    transition: background-color 160ms ease, color 160ms ease;
}

.settings-field-toolbar-btn:hover {
    background: var(--ui-surface-soft);
    color: var(--ui-text);
}
</style>
  

