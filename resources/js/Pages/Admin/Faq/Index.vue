<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame ui-text-main min-h-full">
            <UiPageHeader :title="$t('FAQs')" :subtitle="$t('View, add, edit or delete frequently asked questions')">
                <template #actions>
                    <Link v-if="adminCan('settings', 'frontend')" href="/admin/faqs/create" type="button" class="fq-btn fq-btn--solid">
                        {{ $t('Add FAQ') }}
                    </Link>
                </template>
            </UiPageHeader>

            <div class="mt-6">
                <FaqTable :rows="props.rows" :filters="props.filters" />
            </div>
        </div>
    </AppLayout>
</template>
<script setup>
    import AppLayout from "./../Layout/App.vue";
    import axios from "axios";
    import { ref } from 'vue';
    import { Link, useForm } from "@inertiajs/vue3";
    import FaqTable from '@/Components/Tables/FaqTable.vue';
    import UiPageHeader from '@/Components/UI/UiPageHeader.vue';
    import { useAdminPermission } from "@/Composables/useAdminPermission";

    const props = defineProps({ title: String, rows: Object, filters: Object });
    const { adminCan } = useAdminPermission();
</script>

<style scoped>
.fq-btn {
    display: inline-flex;
    align-items: center;
    border-radius: 0.85rem;
    padding: 0.6rem 1.1rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: filter 160ms ease;
}

.fq-btn--solid {
    background: var(--ui-secondary);
    color: #fff;
    box-shadow: var(--ui-shadow-1);
}

.fq-btn--solid:hover {
    filter: brightness(1.05);
}
</style>

