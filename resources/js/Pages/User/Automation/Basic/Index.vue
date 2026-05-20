<template>
    <SettingLayout :aimodule="aimodule">
        <div class="min-h-0">
            <div class="flex justify-center items-center">
                <div class="md:w-[60em]">
                    <div class="bg-white border border-slate-200 rounded-lg py-2 text-sm pb-4 px-4 mb-20">
                        <div class="w-full py-2 mb-2 mt-2">
                            <div class="flex w-full mb-4">
                                <div class="text-md">
                                    <h4 class="text-[16px]">{{ $t('Basic Replies') }}</h4>
                                    <span class="flex items-center mt-1 text-slate-500">
                                        {{ $t('Respond automatically to messages based on your own criteria') }}
                                    </span> 
                                </div>
                                <div class="ms-auto">
                                    <Link v-if="canAdd" href="/automation/basic/create" class="rounded-md bg-primary px-3 py-2 text-sm text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">{{ $t('Create') }}</Link>
                                    <button type="submit" v-else disabled class="rounded-md bg-gray-400 px-3 py-2 text-sm text-white shadow-sm cursor-not-allowed focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">{{ $t('Create') }}</button>
                                </div>
                            </div>
                            <div class="w-5/5">
                                <!-- Table Component-->
                                <AutoReplyTable :rows="props.rows" :filters="props.filters"/>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </SettingLayout>
</template>
<script setup>
    import SettingLayout from "./../Layout.vue";
    import { Link, usePage } from "@inertiajs/vue3";
    import { computed } from 'vue';
    import AutoReplyTable from '@/Components/Tables/AutoReplyTable.vue';

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


