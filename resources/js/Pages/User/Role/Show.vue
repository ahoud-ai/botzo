<template>
    <SettingLayout :modules="props.modules" :title="$t(props.title)" :subtitle="$t('Choose the appropriate permissions for this role')">
        <template #actions>
            <Link href="/settings/team/roles" class="role-form-btn role-form-btn--ghost">{{ $t('Back') }}</Link>
        </template>

        <div v-if="isOwnerRole" class="role-locked-banner">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"><rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></g></svg>
            <span>{{ $t('This is the universal Owner role and cannot be edited or restricted.') }}</span>
        </div>

        <form @submit.prevent="submitForm()">
            <div class="role-name-card">
                <label class="ui-form-label">{{ $t('Name') }}</label>
                <FormInput
                    v-model="form.name"
                    :type="'text'"
                    :error="form.errors.name"
                    :className="'max-w-md'"
                    :hideLabel="true"
                    :disabled="isOwnerRole"
                />
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <UiSectionCard v-for="(actions, moduleName) in props.permissions" :key="moduleName" :title="translateDynamic(formatModuleName(moduleName))">
                    <template #icon>
                        <span class="role-module-icon">
                            <svg v-if="moduleName === 'contacts'" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/><circle cx="9" cy="7" r="4" stroke="currentColor" stroke-width="1.75"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            <svg v-else-if="moduleName === 'chats'" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            <svg v-else-if="moduleName === 'campaigns'" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="m3 11 18-5v12L3 14v-3z" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            <svg v-else-if="moduleName === 'message_templates'" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7z" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 2v4a2 2 0 0 0 2 2h4M9 13h6M9 17h6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            <svg v-else-if="moduleName === 'automations'" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            <svg v-else-if="moduleName === 'automations.flows'" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"><circle cx="5" cy="6" r="2.5" stroke="currentColor" stroke-width="1.75"/><circle cx="19" cy="6" r="2.5" stroke="currentColor" stroke-width="1.75"/><circle cx="12" cy="18" r="2.5" stroke="currentColor" stroke-width="1.75"/><path d="M7.2 7.3 10.5 16M16.8 7.3 13.5 16" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/></svg>
                            <svg v-else-if="moduleName === 'settings'" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M21 4h-7M10 4H3M21 12h-9M8 12H3M21 20h-5M12 20H3" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/><circle cx="10" cy="4" r="2" stroke="currentColor" stroke-width="1.75"/><circle cx="8" cy="12" r="2" stroke="currentColor" stroke-width="1.75"/><circle cx="12" cy="20" r="2" stroke="currentColor" stroke-width="1.75"/></svg>
                            <svg v-else-if="moduleName === 'developer_tools'" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="m16 18 6-6-6-6M8 6l-6 6 6 6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            <svg v-else xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"><rect x="3" y="3" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.75"/><rect x="14" y="3" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.75"/><rect x="3" y="14" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.75"/><rect x="14" y="14" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.75"/></svg>
                        </span>
                    </template>

                    <button
                        v-if="!isOwnerRole"
                        type="button"
                        class="role-select-all-btn"
                        @click="toggleAllInModule(moduleName)"
                    >
                        {{ isModuleFullyChecked(moduleName) ? $t('Clear All') : $t('Select all') }}
                    </button>

                    <div class="grid grid-cols-1 gap-2.5 mt-2">
                        <div
                            v-for="(label, actionName) in actions"
                            :key="actionName"
                            v-show="!((moduleName === 'contacts' || moduleName === 'chats') && actionName === 'view_assigned_only' && !form.permissions[moduleName]?.view_all)"
                            class="role-permission-row"
                            :class="{ 'role-permission-row--disabled': isOwnerRole }"
                            @click="!isOwnerRole && handlePermissionClick(moduleName, actionName)"
                        >
                            <span
                                class="role-checkbox"
                                :class="{ 'role-checkbox--checked': form.permissions[moduleName][actionName] }"
                            >
                                <svg v-if="form.permissions[moduleName][actionName]" xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 20 20" fill="none"><path d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" fill="currentColor"/></svg>
                            </span>
                            <input
                                v-model="form.permissions[moduleName][actionName]"
                                :name="`permission[${moduleName}][${actionName}]`"
                                type="checkbox"
                                :disabled="isOwnerRole"
                                class="hidden"
                                tabindex="-1"
                                aria-hidden="true"
                            >
                            <span class="role-permission-label">
                                {{ translateDynamic(label) }}
                            </span>
                        </div>
                    </div>
                </UiSectionCard>
            </div>

            <div class="mt-6 flex justify-end">
                <button type="submit" class="role-form-btn role-form-btn--solid" :disabled="isOwnerRole || form.processing">
                    <svg v-if="form.processing" class="animate-spin" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".5"/><path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z"/></svg>
                    <span v-else>{{ $t('Save') }}</span>
                </button>
            </div>
        </form>
    </SettingLayout>
</template>

<script setup>
    import SettingLayout from "./../Settings/Layout.vue";
    import { computed, onMounted } from 'vue';
    import { useI18n } from 'vue-i18n';
    import { buildI18nLookupCandidates } from '@/Utils/i18nLookup';
    import { Link, useForm } from "@inertiajs/vue3";
    import FormInput from '@/Components/FormInput.vue';
    import UiSectionCard from '@/Components/UI/UiSectionCard.vue';
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

    const isModuleFullyChecked = (moduleName) => {
        return Object.keys(form.permissions[moduleName] ?? {}).every((actionName) => form.permissions[moduleName][actionName]);
    };

    const toggleAllInModule = (moduleName) => {
        const nextValue = !isModuleFullyChecked(moduleName);

        Object.keys(form.permissions[moduleName] ?? {}).forEach((actionName) => {
            form.permissions[moduleName][actionName] = nextValue;
        });
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

<style scoped>
.role-form-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    border-radius: 0.7rem;
    padding: 0.6rem 1.2rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: background-color 160ms ease, filter 160ms ease;
}

.role-form-btn--ghost {
    color: var(--ui-text);
    background: var(--ui-surface-soft);
    border: 1px solid var(--ui-border);
}

.role-form-btn--ghost:hover {
    background: var(--ui-border);
}

.role-form-btn--solid {
    color: #fff;
    background: var(--ui-secondary);
    border: none;
}

.role-form-btn--solid:hover:not(:disabled) {
    filter: brightness(1.05);
}

.role-form-btn--solid:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.role-locked-banner {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin-bottom: 1.25rem;
    padding: 0.85rem 1.1rem;
    border-radius: var(--ui-radius-md);
    background: color-mix(in srgb, var(--ui-warning) 12%, var(--ui-surface));
    border: 1px solid color-mix(in srgb, var(--ui-warning) 30%, var(--ui-border));
    color: color-mix(in srgb, var(--ui-warning) 75%, var(--ui-text));
    font-size: 0.88rem;
}

.role-locked-banner svg {
    flex-shrink: 0;
    color: var(--ui-warning);
}

.role-name-card {
    margin-bottom: 1.25rem;
    padding: 1.1rem 1.25rem;
    border-radius: var(--ui-radius-md);
    background: var(--ui-surface);
    border: 1px solid var(--ui-border);
}

.role-module-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.2rem;
    height: 2.2rem;
    border-radius: 0.7rem;
    color: var(--ui-secondary);
    background: color-mix(in srgb, var(--ui-secondary) 12%, transparent);
}

.role-select-all-btn {
    font-size: 0.76rem;
    font-weight: 700;
    color: var(--ui-secondary);
    margin-top: -0.5rem;
    margin-bottom: 0.5rem;
    transition: opacity 160ms ease;
}

.role-select-all-btn:hover {
    opacity: 0.75;
}

.role-permission-row {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    cursor: pointer;
    padding: 0.4rem 0.5rem;
    border-radius: 0.55rem;
    transition: background-color 140ms ease;
}

.role-permission-row:hover {
    background: var(--ui-surface-soft);
}

.role-permission-row--disabled {
    cursor: not-allowed;
    opacity: 0.55;
}

.role-checkbox {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 1.2rem;
    height: 1.2rem;
    border-radius: 0.35rem;
    border: 1.5px solid var(--ui-border-strong);
    color: #fff;
    transition: background-color 160ms ease, border-color 160ms ease;
}

.role-checkbox--checked {
    background: var(--ui-secondary);
    border-color: var(--ui-secondary);
}

.role-permission-label {
    font-size: 0.88rem;
    color: var(--ui-text);
    cursor: pointer;
    user-select: none;
}
</style>
