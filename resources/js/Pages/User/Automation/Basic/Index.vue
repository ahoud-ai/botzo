<template>
    <SettingLayout :aimodule="aimodule">
        <UiPageHeader :title="$t('Basic Replies')" :subtitle="$t('Respond automatically to messages based on your own criteria')">
            <template #actions>
                <Link v-if="canAdd" href="/automation/basic/create" class="automation-create-btn">{{ $t('Create') }}</Link>
                <button v-else type="button" disabled class="automation-create-btn automation-create-btn--disabled">{{ $t('Create') }}</button>
            </template>
        </UiPageHeader>

        <AutoReplyTable :rows="props.rows" :filters="props.filters"/>
    </SettingLayout>
</template>
<script setup>
    import SettingLayout from "./../Layout.vue";
    import { Link, usePage } from "@inertiajs/vue3";
    import { computed } from 'vue';
    import AutoReplyTable from '@/Components/Tables/AutoReplyTable.vue';
    import UiPageHeader from '@/Components/UI/UiPageHeader.vue';

    const props = defineProps(['rows', 'filters', 'aimodule']);

    // Helper function to check permissions
    const hasPermission = (permission) => {
        const pageProps = usePage().props;
        const permissions = pageProps?.permissions;
        if (!permissions || !Array.isArray(permissions)) {
            return false;
        }
        return permissions.includes('*') || permissions.includes(permission);
    };

    const canAdd = computed(() => hasPermission('automations.add'));
</script>

<style scoped>
.automation-create-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.7rem;
    padding: 0.6rem 1.1rem;
    font-size: 0.85rem;
    font-weight: 600;
    color: #fff;
    background: var(--ui-secondary);
    transition: filter 160ms ease;
}

.automation-create-btn:hover:not(:disabled) {
    filter: brightness(1.05);
}

.automation-create-btn--disabled {
    background: var(--ui-border-strong);
    color: var(--ui-muted);
    cursor: not-allowed;
}
</style>
