import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const normalizeKey = (value) => String(value ?? '').trim().toLowerCase();

export const useAdminPermission = () => {
    const page = usePage();

    const payload = computed(() => page.props?.adminPermissions ?? {});
    const isSuperAdmin = computed(() => Boolean(payload.value?.isSuperAdmin));
    const moduleActions = computed(() => payload.value?.moduleActions ?? {});

    const adminCan = (moduleName, actionName = null) => {
        if (isSuperAdmin.value) {
            return true;
        }

        const moduleKey = normalizeKey(moduleName);
        if (!moduleKey) {
            return false;
        }

        const actions = moduleActions.value[moduleKey];
        if (!Array.isArray(actions) || actions.length === 0) {
            return false;
        }

        if (!actionName) {
            return true;
        }

        const actionKey = normalizeKey(actionName);
        return actions.some((action) => normalizeKey(action) === actionKey);
    };

    const adminCanAny = (moduleName, actionNames = []) => {
        if (isSuperAdmin.value) {
            return true;
        }

        if (!Array.isArray(actionNames) || actionNames.length === 0) {
            return adminCan(moduleName);
        }

        return actionNames.some((actionName) => adminCan(moduleName, actionName));
    };

    return {
        adminPermissions: payload,
        adminCan,
        adminCanAny,
        isAdminSuperAdmin: isSuperAdmin,
    };
};
