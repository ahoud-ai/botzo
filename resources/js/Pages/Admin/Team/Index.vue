<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame ui-text-main min-h-full">
            <UiPageHeader :title="$t('Users')" :subtitle="$t('Manage admin accounts')">
                <template #actions>
                    <Link v-if="adminCan('team', 'create')" href="/admin/team/users/create" class="tm-btn tm-btn--solid">
                        {{ $t('Add user') }}
                    </Link>
                </template>
            </UiPageHeader>
            <div class="mt-6">
                <UserTable :rows="props.rows" :filters="props.filters" :type="'admin'"/>
            </div>
        </div>
    </AppLayout>
</template>
<script setup>
    import AppLayout from "./../Layout/App.vue";
    import { Link } from "@inertiajs/vue3";
    import UserTable from '@/Components/Tables/UserTable.vue';
    import UiPageHeader from '@/Components/UI/UiPageHeader.vue';
    import { useAdminPermission } from "@/Composables/useAdminPermission";

    const props = defineProps({ title: String, rows: Object, filters: Object });
    const { adminCan } = useAdminPermission();
</script>

<style scoped>
.tm-btn {
    display: inline-flex;
    align-items: center;
    border-radius: 0.85rem;
    padding: 0.6rem 1.1rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: filter 160ms ease;
}

.tm-btn--solid {
    background: var(--ui-secondary);
    color: #fff;
    box-shadow: var(--ui-shadow-1);
}

.tm-btn--solid:hover {
    filter: brightness(1.05);
}
</style>

