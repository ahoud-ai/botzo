<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame ui-text-main min-h-full">
            <UiPageHeader :title="$t('Support tickets')" :subtitle="$t('View and respond to customer queries')">
                <template #actions>
                    <Link v-if="adminCan('support', 'create')" href="/admin/support/create" class="tkt-btn tkt-btn--solid">
                        {{ $t('Create ticket') }}
                    </Link>
                </template>
            </UiPageHeader>
            <div class="mt-6">
                <TicketTable :rows="props.rows"/>
            </div>
        </div>
    </AppLayout>
</template>
<script setup>
    import AppLayout from "./../Layout/App.vue";
    import { Link } from "@inertiajs/vue3";
    import TicketTable from '@/Components/Tables/TicketTable.vue';
    import UiPageHeader from '@/Components/UI/UiPageHeader.vue';
    import { useAdminPermission } from "@/Composables/useAdminPermission";

    const { adminCan } = useAdminPermission();

    const props = defineProps({
        allowCreate: {
            type: Boolean,
            default: true
        },
        title: String,
        rows: Object
    });
</script>

<style scoped>
.tkt-btn {
    display: inline-flex;
    align-items: center;
    border-radius: 0.85rem;
    padding: 0.6rem 1.1rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: filter 160ms ease;
}

.tkt-btn--solid {
    background: var(--ui-secondary);
    color: #fff;
    box-shadow: var(--ui-shadow-1);
}

.tkt-btn--solid:hover {
    filter: brightness(1.05);
}
</style>

