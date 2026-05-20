<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame ui-text-main">
            <div class="flex justify-between">
                <div>
                    <h1 class="text-xl mb-1">{{ $t('Reviews') }}</h1>
                    <p class="mb-6 flex items-center text-sm leading-6 text-gray-600">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                        <span class="ms-1 mt-1">{{ $t('View, add, edit or delete reviews') }}</span>
                    </p>
                </div>
                <div v-if="adminCan('settings', 'frontend')">
                    <Link href="/admin/testimonials/create" class="rounded-md bg-indigo-600 px-3 py-2 text-sm text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">{{ $t('Add review') }}</Link>
                </div>
            </div>

            <TestimonialTable :rows="props.rows" :filters="props.filters" />
        </div>
    </AppLayout>
</template>
<script setup>
    import AppLayout from "./../Layout/App.vue";
    import { Link } from "@inertiajs/vue3";
    import TestimonialTable from '@/Components/Tables/TestimonialTable.vue';
    import { useAdminPermission } from "@/Composables/useAdminPermission";

    const props = defineProps({ title: String, rows: Object, filters: Object });
    const { adminCan } = useAdminPermission();
</script>
