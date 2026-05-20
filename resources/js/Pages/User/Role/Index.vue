<template>
    <SettingLayout :modules="props.modules">
        <div class="min-h-0">
            <div class="flex justify-center items-center">
                <div class="md:w-[60em] mb-20">
                    <div class="bg-white border border-slate-200 rounded-lg py-2 text-sm mb-4 pb-4 px-4">
                        <div class="w-full py-2 mb-2 mt-2">
                            <div class="flex w-full mb-4">
                                <div class="text-md">
                                    <h4 class="text-[16px]">{{ $t('Roles & Permissions') }}</h4>
                                    <span class="flex items-center mt-1 text-slate-500">
                                        {{ $t('Manage roles and permissions for this workspace only') }}
                                    </span> 
                                </div>
                                <div class="ms-auto">
                                    <Link href="/settings/team/roles/create" class="rounded-md bg-primary px-3 py-2 text-sm text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">{{ $t('Add Role') }}</Link>
                                </div>
                            </div>
                            <div v-if="hasInheritedParentAuthority" class="mb-4 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                                {{ $t('You are managing this branch through the parent organization owner access. Any role you create here applies to this branch workspace only.') }}
                            </div>
                            <div class="mb-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                                {{ $t('System ownership is handled automatically. Custom roles here are for workspace members and branch staff only.') }}
                            </div>
                            <div class="w-5/5">
                                <!-- Table Component-->
                                <RoleTable :rows="props.rows" :filters="props.filters"/>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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



