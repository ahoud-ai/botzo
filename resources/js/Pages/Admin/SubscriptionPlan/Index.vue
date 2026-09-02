<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame ui-text-main min-h-full">
            <UiPageHeader :title="$t('Subscription plans')" :subtitle="$t('Manage subscription plans')">
                <template #actions>
                    <Link v-if="adminCan('subscription_plans', 'create')" href="/admin/plans/create" class="pl-btn pl-btn--solid">
                        {{ $t('Create plan') }}
                    </Link>
                </template>
            </UiPageHeader>
            <div class="mt-6">
                <SubscriptionPlanTable :rows="props.rows" :filters="props.filters" />
            </div>
        </div>
    </AppLayout>
</template>
<script setup>
    import AppLayout from "./../Layout/App.vue";
    import { Link } from "@inertiajs/vue3";
    import SubscriptionPlanTable from '@/Components/Tables/SubscriptionPlanTable.vue';
    import UiPageHeader from '@/Components/UI/UiPageHeader.vue';
    import { useAdminPermission } from "@/Composables/useAdminPermission";

    const { adminCan } = useAdminPermission();

    const props = defineProps({
        allowCreate: {
            type: Boolean,
            default: true
        },
        title: String,
        rows: Object,
        filters: Object
    });
</script>

<style scoped>
.pl-btn {
    display: inline-flex;
    align-items: center;
    border-radius: 0.85rem;
    padding: 0.6rem 1.1rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: filter 160ms ease;
}

.pl-btn--solid {
    background: var(--ui-secondary);
    color: #fff;
    box-shadow: var(--ui-shadow-1);
}

.pl-btn--solid:hover {
    filter: brightness(1.05);
}
</style>

