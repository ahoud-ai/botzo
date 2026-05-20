import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export const useWorkspaceAccess = () => {
    const page = usePage();

    const workspaceAccess = computed(() => page.props?.workspaceAccess ?? {});
    const permissions = computed(() => page.props?.permissions ?? []);
    const isWorkspaceOwner = computed(() => Boolean(workspaceAccess.value?.isOwner));
    const isBranchWorkspace = computed(() => Boolean(workspaceAccess.value?.isBranch));
    const hasInheritedParentAuthority = computed(() => workspaceAccess.value?.authoritySource === 'inherited_parent_owner');
    const canManageTeam = computed(() => Boolean(workspaceAccess.value?.canManageTeam));
    const canManageRoles = computed(() => Boolean(workspaceAccess.value?.canManageRoles));
    const canManageCompanyProfile = computed(() => Boolean(workspaceAccess.value?.canManageCompanyProfile));
    const canViewBilling = computed(() => Boolean(workspaceAccess.value?.canViewBilling));
    const companyProfileManagedByParent = computed(() => Boolean(workspaceAccess.value?.companyProfileManagedByParent));

    const hasPermission = (permission) => {
        if (isWorkspaceOwner.value) {
            return true;
        }

        if (!Array.isArray(permissions.value)) {
            return false;
        }

        return permissions.value.includes('*') || permissions.value.includes(permission);
    };

    return {
        workspaceAccess,
        permissions,
        isWorkspaceOwner,
        isBranchWorkspace,
        hasInheritedParentAuthority,
        canManageTeam,
        canManageRoles,
        canManageCompanyProfile,
        canViewBilling,
        companyProfileManagedByParent,
        hasPermission,
    };
};
