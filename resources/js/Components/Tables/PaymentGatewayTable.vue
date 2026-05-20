<script setup>
    import Table from '@/Components/Table.vue';
    import TableBody from '@/Components/TableBody.vue';
    import TableBodyRow from '@/Components/TableBodyRow.vue';
    import TableBodyRowItem from '@/Components/TableBodyRowItem.vue';
    import { Link } from '@inertiajs/vue3';

    const props = defineProps({
        rows: {
            type: Object,
            required: true,
        },
    });

    const normalizeGatewayName = (value) => String(value ?? '').trim().toLowerCase();

    const pageEditHref = (item) => `/admin/payment-gateways/${encodeURIComponent(normalizeGatewayName(item?.name))}`;

    const isLastRow = (index) => {
      return index === props.rows.data.length - 1;
    }
</script>
<template>
    <Table :rows="rows">
        <TableBody>
            <TableBodyRow v-for="(item, index) in rows.data" :key="index" :class="[index === 0? 'border-t-[0px]' : '', !isLastRow(index) ? 'border-b' : '']">
                <TableBodyRowItem :position="'first'" class="py-2">
                    {{ item.name }}
                    <span class="bg-gray-200 text-[11px] p-1 rounded-md">{{ item.is_active == '1' ? $t('Active') : $t('Inactive') }}</span>
                </TableBodyRowItem>
                <TableBodyRowItem :position="'last'">
                    <div class="flex items-center h-100 py-2">
                        <Link
                            :href="pageEditHref(item)"
                            class="inline-flex justify-center rounded-md border border-transparent bg-primary px-2 py-1 text-[12px] text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2"
                        >
                            {{ $t('Edit') }}
                        </Link>
                    </div>
                </TableBodyRowItem>
            </TableBodyRow>
        </TableBody>
    </Table>
</template>
  
