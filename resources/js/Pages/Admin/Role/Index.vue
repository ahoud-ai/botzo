<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame ui-text-main min-h-full">
            <UiPageHeader :title="$t('Admin roles')" :subtitle="$t('Manage roles')">
                <template #actions>
                    <Link v-if="adminCan('roles', 'create')" href="/admin/team/roles/create" class="rl-btn rl-btn--solid">
                        {{ $t('Add role') }}
                    </Link>
                </template>
            </UiPageHeader>
            <div class="mt-6">
                <AdminRoleTable :rows="props.rows" :filters="props.filters" />
            </div>
        </div>
    </AppLayout>
</template>
<script setup>
    import AppLayout from "./../Layout/App.vue";
    import { Link } from "@inertiajs/vue3";
    import AdminRoleTable from '@/Components/Tables/AdminRoleTable.vue';
    import UiPageHeader from '@/Components/UI/UiPageHeader.vue';
    import { useAdminPermission } from "@/Composables/useAdminPermission";

    const props = defineProps({ title: String, rows: Object, filters: Object });
    const { adminCan } = useAdminPermission();
</script>

<style scoped>
.rl-btn {
    display: inline-flex;
    align-items: center;
    border-radius: 0.85rem;
    padding: 0.6rem 1.1rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: filter 160ms ease;
}

.rl-btn--solid {
    background: var(--ui-secondary);
    color: #fff;
    box-shadow: var(--ui-shadow-1);
}

.rl-btn--solid:hover {
    filter: brightness(1.05);
}
</style>

