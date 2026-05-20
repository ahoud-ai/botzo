<template>
    <SettingLayout :modules="props.modules">
        <div class="min-h-0">
            <div class="flex justify-center items-center">
                <div class="mb-20 w-full max-w-5xl">
                    <form @submit.prevent="submitForm()" class="bg-white border border-slate-200 rounded-lg py-5 px-5">
                        <div class="mb-4 flex flex-col gap-3 border-b pb-4 md:flex-row md:items-center md:justify-between">
                            <div>
                                <h1 class="text-xl mb-1">{{ $t(props.title) }}</h1>
                            </div>
                            <div>
                                <Link href="/settings/team/roles" class="rounded-md bg-indigo-600 px-3 py-2 text-sm text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">{{ $t('Back') }}</Link>
                            </div>
                        </div>
                <div class="border-b py-5 sm:flex sm:gap-8">
                    <div class="mb-3 sm:mb-1 sm:w-[40%]">
                        <h1 class="text-sm text-gray-500 tracking-[0px]">{{ $t('Name') }}</h1>
                    </div>
                    <div class="sm:w-[60%] sm:flex gap-x-6">
                        <div class="w-full sm:w-[80%]">
                            <FormInput 
                                v-model="form.name" 
                                :type="'text'" 
                                :error="form.errors.name" 
                                :class="'w-full'" 
                                :labelClass="'mb-0'"
                                :disabled="isOwnerRole"
                            />
                        </div>
                    </div>
                </div>
                <div class="flex flex-col gap-4 py-5 sm:flex-row sm:gap-8">
                    <div class="sm:w-[40%] mb-1">
                        <h1 class="text-sm text-gray-500 tracking-[0px]">{{ $t('Permissions') }}</h1>
                        <span class="flex text-xs mt-2">
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                            {{ $t('Choose the appropriate permissions for this role') }}
                        </span>
                    </div>
                    <div class="sm:w-[60%] flex gap-x-6">
                        <div class="w-full sm:w-[80%]">
                            <div class="mb-6" v-for="(actions, moduleName) in props.permissions" :key="moduleName">
                                <label class="text-sm font-semibold text-gray-800 mb-3 block">{{ translateDynamic(formatModuleName(moduleName)) }}</label>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div v-for="(label, actionName) in actions" :key="actionName" 
                                        v-show="!((moduleName === 'contacts' || moduleName === 'chats') && actionName === 'view_assigned_only' && !form.permissions[moduleName]?.view_all)"
                                        class="flex items-center"
                                    >
                                        <div 
                                            @click="!isOwnerRole && handlePermissionClick(moduleName, actionName)"
                                            class="relative w-5 h-5 flex items-center justify-center cursor-pointer me-2 rounded border-2 transition-all"
                                            :class="[
                                                form.permissions[moduleName][actionName] 
                                                ? 'bg-primary border-primary' 
                                                : 'bg-white border-gray-300 hover:border-primary/50',
                                                isOwnerRole ? 'opacity-50 cursor-not-allowed' : ''
                                            ]"
                                        >
                                            <svg 
                                                v-if="form.permissions[moduleName][actionName]"
                                                xmlns="http://www.w3.org/2000/svg" 
                                                class="w-3.5 h-3.5 text-white" 
                                                viewBox="0 0 20 20" 
                                                fill="currentColor"
                                            >
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                            </svg>
                                        </div>
                                        <input 
                                            v-model="form.permissions[moduleName][actionName]" 
                                            :id="`permission-${moduleName}-${actionName}`" 
                                            :name="`permission[${moduleName}][${actionName}]`"
                                            type="checkbox" 
                                            :disabled="isOwnerRole"
                                            class="hidden"
                                        >
                                        <label 
                                            :for="`permission-${moduleName}-${actionName}`" 
                                            :class="[
                                                'text-sm text-gray-700 cursor-pointer select-none flex-1',
                                                isOwnerRole ? 'cursor-not-allowed' : ''
                                            ]"
                                        >
                                            {{ translateDynamic(label) }}
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="py-6">
                    <button 
                        type="submit" 
                        :disabled="isOwnerRole || form.processing"
                        :class="[
                            'ms-auto flex items-center gap-x-4 rounded-md px-3 py-2 text-sm text-white shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600',
                            isOwnerRole ? 'bg-gray-400 cursor-not-allowed' : 'bg-black hover:bg-slate-600'
                        ]">
                        <svg v-if="form.processing" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".5"/><path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z"><animateTransform attributeName="transform" dur="1s" from="0 12 12" repeatCount="indefinite" to="360 12 12" type="rotate"/></path></svg>
                        <span v-else>{{ $t('Save') }}</span>
                    </button>
                </div>
                    </form>
                </div>
            </div>
        </div>
    </SettingLayout>
</template>

<script setup>
    import SettingLayout from "./../Settings/Layout.vue";
    import { computed, onMounted } from 'vue';
    import { useI18n } from 'vue-i18n';
    import { buildI18nLookupCandidates } from '@/Utils/i18nLookup';
    import { Link, useForm, router } from "@inertiajs/vue3";
    import FormInput from '@/Components/FormInput.vue';
    const { t, te } = useI18n();

    const props = defineProps({ 
        title: String, 
        role: Object, 
        permissions: Object,
        modules: {
            type: Array,
            default: () => []
        }
    });

    const isOwnerRole = computed(() => {
        return props.role && !props.role.organization_id && props.role.name === 'Owner';
    });

    // Initialize form with permissions structure
    const initializePermissions = () => {
        const perms = {};
        Object.keys(props.permissions).forEach(moduleName => {
            perms[moduleName] = {};
            Object.keys(props.permissions[moduleName]).forEach(actionName => {
                perms[moduleName][actionName] = false;
            });
        });
        return perms;
    };

    const form = useForm({
        name: props.role?.name || '',
        permissions: initializePermissions()
    });

    const formatModuleName = (name) => {
        return name.split(/[_.]+/).map(word => 
            word.charAt(0).toUpperCase() + word.slice(1)
        ).join(' ');
    };

    const translateDynamic = (value) => {
        const raw = String(value ?? '').trim();
        if (!raw) {
            return '';
        }

        for (const candidate of buildI18nLookupCandidates(raw)) {
            if (te(candidate)) {
                return t(candidate);
            }
        }

        return raw;
    };

    // Handle permission checkbox click
    const handlePermissionClick = (moduleName, actionName) => {
        form.permissions[moduleName][actionName] = !form.permissions[moduleName][actionName];
        
        // If unchecking view_all, also uncheck view_assigned_only
        if ((moduleName === 'contacts' || moduleName === 'chats') && actionName === 'view_all' && !form.permissions[moduleName][actionName]) {
            form.permissions[moduleName]['view_assigned_only'] = false;
        }
    };

    // Initialize checkboxes based on existing role permissions
    const initializeCheckboxValues = () => {
        if (props.role && props.role.permissions) {
            props.role.permissions.forEach(permission => {
                const [moduleName, actionName] = permission.split('.');
                if (form.permissions[moduleName] && form.permissions[moduleName][actionName] !== undefined) {
                    form.permissions[moduleName][actionName] = true;
                }
            });
        }
    };

    const submitForm = async () => {
        if (isOwnerRole.value) {
            return; // Prevent editing owner role
        }

        const url = props.role ? `/settings/team/roles/${props.role.uuid}` : '/settings/team/roles';

        if (props.role) {
            form.put(url, {
                preserveScroll: true,
                onError: (errors) => {
                    console.error('Validation errors:', errors);
                }
            });
        } else {
            form.post(url, {
                preserveScroll: true,
                onError: (errors) => {
                    console.error('Validation errors:', errors);
                }
            });
        }
    };

    onMounted(() => {
        initializeCheckboxValues();
    });
</script>



