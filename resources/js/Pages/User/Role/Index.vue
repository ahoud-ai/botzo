<template>
    <SettingLayout :modules="props.modules" :title="$t('Roles & Permissions')" :subtitle="$t('Manage roles and permissions for this workspace only')">
        <template #actions>
            <Link href="/settings/team/roles/create" class="role-index-btn">{{ $t('Add Role') }}</Link>
        </template>

        <div v-if="hasInheritedParentAuthority" class="role-index-banner role-index-banner--info">
            {{ $t('You are managing this branch through the parent organization owner access. Any role you create here applies to this branch workspace only.') }}
        </div>
        <div class="role-index-banner">
            {{ $t('System ownership is handled automatically. Custom roles here are for workspace members and branch staff only.') }}
        </div>

        <RoleTable :rows="props.rows" :filters="props.filters"/>
    </SettingLayout>
</template>

<script setup>
    import SettingLayout from "./../Settings/Layout.vue";
    import RoleTable from '@/Components/Tables/RoleTable.vue';
    import { Link } from "@inertiajs/vue3";
    import { useWorkspaceAccess } from '@/Composables/useWorkspaceAccess';

    const { hasInheritedParentAuthority } = useWorkspaceAccess();

    const props = defineProps({
        title: String,
        rows: Object,
        filters: Object,
        modules: {
            type: Array,
            default: () => []
        }
    });
</script>

<style scoped>
.role-index-btn {
    display: inline-flex;
    align-items: center;
    border-radius: 0.7rem;
    padding: 0.6rem 1.2rem;
    font-size: 0.85rem;
    font-weight: 600;
    color: #fff;
    background: var(--ui-secondary);
    transition: filter 160ms ease;
}

.role-index-btn:hover {
    filter: brightness(1.05);
}

.role-index-banner {
    margin-bottom: 1rem;
    padding: 0.85rem 1.1rem;
    border-radius: var(--ui-radius-md);
    background: var(--ui-surface-soft);
    border: 1px solid var(--ui-border);
    color: var(--ui-muted);
    font-size: 0.86rem;
    line-height: 1.5;
}

.role-index-banner--info {
    background: color-mix(in srgb, var(--ui-secondary) 8%, var(--ui-surface));
    border-color: color-mix(in srgb, var(--ui-secondary) 20%, var(--ui-border));
    color: var(--ui-text);
}
</style>
