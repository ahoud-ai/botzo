<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame ui-text-main">
            <UiPageHeader :title="$t('Reviews')" :subtitle="$t('View, add, edit or delete reviews')">
                <template #actions>
                    <Link v-if="adminCan('settings', 'frontend')" href="/admin/testimonials/create" class="tst-btn tst-btn--solid">
                        {{ $t('Add review') }}
                    </Link>
                </template>
            </UiPageHeader>

            <div class="mt-6">
                <TestimonialTable :rows="props.rows" :filters="props.filters" />
            </div>
        </div>
    </AppLayout>
</template>
<script setup>
    import AppLayout from "./../Layout/App.vue";
    import { Link } from "@inertiajs/vue3";
    import TestimonialTable from '@/Components/Tables/TestimonialTable.vue';
    import UiPageHeader from '@/Components/UI/UiPageHeader.vue';
    import { useAdminPermission } from "@/Composables/useAdminPermission";

    const props = defineProps({ title: String, rows: Object, filters: Object });
    const { adminCan } = useAdminPermission();
</script>

<style scoped>
.tst-btn {
    display: inline-flex;
    align-items: center;
    border-radius: 0.85rem;
    padding: 0.6rem 1.1rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: filter 160ms ease;
}

.tst-btn--solid {
    background: var(--ui-secondary);
    color: #fff;
    box-shadow: var(--ui-shadow-1);
}

.tst-btn--solid:hover {
    filter: brightness(1.05);
}
</style>
