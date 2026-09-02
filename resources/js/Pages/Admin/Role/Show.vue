<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame ui-text-main min-h-full">
            <UiPageHeader :title="$t(props.title)" :subtitle="$t('Create roles for administrative users')">
                <template #actions>
                    <Link href="/admin/team/roles" class="rl-btn rl-btn--ghost">{{ $t('Back') }}</Link>
                </template>
            </UiPageHeader>

            <form @submit.prevent="submitForm()" class="mt-6 space-y-6">
                <UiSectionCard :title="$t('Name')">
                    <div class="max-w-md">
                        <FormInput v-model="form.name" :type="'text'" :error="form.errors.name" :class="'w-full'" :labelClass="'mb-0'" :disabled="isSystemRole"/>
                        <p v-if="isSystemRole" class="rl-note mt-3">
                            {{ $t('System admin role cannot be modified.') }}
                        </p>
                    </div>
                </UiSectionCard>

                <UiSectionCard :title="$t('Permissions')" :subtitle="$t('Choose the appropriate permissions for this role')">
                    <div class="space-y-5">
                        <div v-for="(item, index) in props.modules" :key="index" class="rl-module">
                            <p class="rl-module-title">{{ $t(toTranslationKey(item.name)) }}</p>
                            <div class="rl-permission-grid">
                                <label
                                    v-for="value in separateValues(item.actions)"
                                    :key="value"
                                    class="rl-permission-item"
                                    :class="{ 'rl-permission-item--active': form.permissions[item.name][value], 'rl-permission-item--disabled': isSystemRole }"
                                >
                                    <input
                                        v-model="form.permissions[item.name][value]"
                                        :name="'permission['+ item.name + '|' + value + ']'"
                                        type="checkbox"
                                        :disabled="isSystemRole"
                                        class="ui-checkbox-input"
                                    >
                                    <span>{{ $t(toTranslationKey(value)) }}</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </UiSectionCard>

                <div class="flex justify-end">
                    <button type="submit" :disabled="isSystemRole || form.processing" class="rl-btn rl-btn--solid">
                        {{ $t('Save') }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
<script setup>
    import AppLayout from "./../Layout/App.vue";
    import { computed, onMounted } from 'vue';
    import { Link, useForm } from "@inertiajs/vue3";
    import FormInput from '@/Components/FormInput.vue';
    import UiPageHeader from '@/Components/UI/UiPageHeader.vue';
    import UiSectionCard from '@/Components/UI/UiSectionCard.vue';

    const props = defineProps({ title: String, role: Object, modules: Object, permissions: Object });
    const isSystemRole = computed(() => String(props.role?.name ?? '').trim().toLowerCase() === 'admin');

    const form = useForm({
        name: props.role?.name,
        permissions: Object.fromEntries(props.modules.map(item => [item.name, {}]))
    })

    const separateValues = (value) => {
        return String(value || '')
            .split(',')
            .map((item) => item.trim())
            .filter(Boolean);
    };

    const toTranslationKey = (inputString) => {
        const normalized = String(inputString || '')
            .trim()
            .replace(/[_\-.]+/g, ' ')
            .replace(/\s+/g, ' ');

        if (!normalized) {
            return '';
        }

        return normalized.charAt(0).toUpperCase() + normalized.slice(1).toLowerCase();
    };

    const isPermissionChecked = (moduleName, actionName) => {
        const value = props.permissions.some(
            (permission) =>
                permission.module === moduleName &&
                permission.action === actionName &&
                permission.role_id === props.role?.id
        );

        form.permissions[moduleName][actionName] = value;
    };

    const initializeCheckboxValues = () => {
        props.modules.forEach(item => {
            const moduleName = item.name;
            const modulePermissions = props.permissions.filter(permission => permission.module === moduleName);

            modulePermissions.forEach(permission => {
                const actionName = permission.action;
                form.permissions[moduleName][actionName] = true;
            });
        });
    };

    const submitForm = async () => {
        if (isSystemRole.value) {
            return;
        }

        const url = props.role ? window.location.pathname : '/admin/team/roles';

        form[props.role ? 'put' : 'post'](url, {
            preserveScroll: true,
        });
    };

    onMounted(() => {
        initializeCheckboxValues();
    });
</script>

<style scoped>
.rl-btn {
    display: inline-flex;
    align-items: center;
    border-radius: 0.85rem;
    padding: 0.6rem 1.1rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: background-color 160ms ease, border-color 160ms ease, filter 160ms ease, opacity 160ms ease;
}

.rl-btn--ghost {
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    color: var(--ui-text);
}

.rl-btn--ghost:hover {
    background: var(--ui-surface-soft);
    border-color: var(--ui-border-strong);
}

.rl-btn--solid {
    border: 1px solid transparent;
    background: var(--ui-secondary);
    color: #fff;
    box-shadow: var(--ui-shadow-1);
}

.rl-btn--solid:hover:not(:disabled) {
    filter: brightness(1.05);
}

.rl-btn--solid:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}

.rl-note {
    font-size: 0.78rem;
    color: var(--ui-muted);
}

.rl-module {
    border-radius: 1rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface-soft);
    padding: 1rem 1.1rem;
}

.rl-module-title {
    font-size: 0.85rem;
    font-weight: 800;
    color: var(--ui-text);
    margin-bottom: 0.75rem;
}

.rl-permission-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.55rem;
}

@media (min-width: 640px) {
    .rl-permission-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

.rl-permission-item {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    border-radius: 0.75rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    padding: 0.55rem 0.75rem;
    font-size: 0.85rem;
    color: var(--ui-text);
    cursor: pointer;
    transition: border-color 160ms ease, background-color 160ms ease;
}

.rl-permission-item:hover {
    border-color: var(--ui-border-strong);
}

.rl-permission-item--active {
    border-color: color-mix(in srgb, var(--ui-secondary) 50%, var(--ui-border));
    background: color-mix(in srgb, var(--ui-secondary) 9%, var(--ui-surface));
    font-weight: 600;
}

.rl-permission-item--disabled {
    cursor: not-allowed;
    opacity: 0.7;
}
</style>
