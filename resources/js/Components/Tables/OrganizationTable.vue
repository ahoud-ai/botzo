<script setup>
    import { computed, ref } from 'vue';
    import debounce from 'lodash/debounce';
    import { Link, router, useForm } from '@inertiajs/vue3';
    import { useI18n } from 'vue-i18n';
    import AlertModal from '@/Components/AlertModal.vue';
    import { useAlertModal } from '@/Composables/useAlertModal';
    import { useAdminPermission } from '@/Composables/useAdminPermission';
    import 'vue3-toastify/dist/index.css';
    import Table from '@/Components/Table.vue';
    import TableHeader from '@/Components/TableHeader.vue';
    import TableHeaderRow from '@/Components/TableHeaderRow.vue';
    import TableHeaderRowItem from '@/Components/TableHeaderRowItem.vue';
    import TableBody from '@/Components/TableBody.vue';
    import TableBodyRow from '@/Components/TableBodyRow.vue';
    import TableBodyRowItem from '@/Components/TableBodyRowItem.vue';
    import Dropdown from '@/Components/Dropdown.vue';
    import DropdownItemGroup from '@/Components/DropdownItemGroup.vue';
    import DropdownItem from '@/Components/DropdownItem.vue';
    import Pagination from '@/Components/Pagination.vue';
    import UiEmptyState from '@/Components/UI/UiEmptyState.vue';

    const props = defineProps({
        rows: {
            type: Object,
            required: true,
        },
        filters: {
            type: Object,
            default: () => ({}),
        },
        variant: {
            type: String,
            default: 'default',
        },
        plans: {
            type: Array,
            default: () => [],
        },
    });

    const { isOpenAlert, openAlert, confirmAlert } = useAlertModal();
    const { adminCan } = useAdminPermission();
    const form = useForm({ test: null });
    const isSearching = ref(false);
    const { t } = useI18n();

    const params = ref({
        search: props.filters?.search ?? null,
        organization_type: props.filters?.organization_type ?? 'main',
        subscription_status: props.filters?.subscription_status ?? null,
        plan_uuid: props.filters?.plan_uuid ?? null,
        branches_scope: props.filters?.branches_scope ?? null,
    });

    const isDirectoryVariant = computed(() => props.variant === 'directory');
    const canDelete = computed(() => adminCan('organizations', 'delete'));

    const typeOptions = computed(() => ([
        { value: 'main', label: t('Main organizations') },
        { value: 'branch', label: t('Branches only') },
        { value: 'all', label: t('Show all') },
    ]));
    const statusOptions = computed(() => ([
        { value: null, label: t('All subscription statuses') },
        { value: 'active', label: t('Active') },
        { value: 'trial', label: t('Trial period') },
        { value: 'expired', label: t('Expired') },
        { value: 'no_plan', label: t('No plan yet') },
    ]));
    const branchesScopeOptions = computed(() => ([
        { value: null, label: t('Any branch coverage') },
        { value: 'with_branches', label: t('With branches') },
        { value: 'without_branches', label: t('Without branches') },
    ]));
    const hasAdvancedFilters = computed(() => Boolean(
        params.value.search
        || params.value.subscription_status
        || params.value.plan_uuid
        || params.value.branches_scope
    ));

    const deleteAction = (key) => {
        form.delete('/admin/organizations/' + key);
    };

    const isLastRow = (index) => index === props.rows.data.length - 1;

    const clearSearch = () => {
        params.value.search = null;
        runSearch();
    };

    const clearAllFilters = () => {
        params.value.search = null;
        params.value.subscription_status = null;
        params.value.plan_uuid = null;
        params.value.branches_scope = null;
        runSearch();
    };

    const search = debounce(() => {
        isSearching.value = true;
        runSearch();
    }, 500);

    const changeFilter = () => {
        isSearching.value = true;
        runSearch();
    };

    const applyType = (value) => {
        params.value.organization_type = value;
        changeFilter();
    };

    const applyStatus = (value) => {
        params.value.subscription_status = value;
        changeFilter();
    };

    const runSearch = () => {
        router.visit(window.location.pathname, {
            method: 'get',
            data: params.value,
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                isSearching.value = false;
            },
        });
    };

    const ownerName = (item) => item.owner?.user?.full_name ?? t('Not set');
    const ownerEmail = (item) => item.owner?.user?.email ?? t('Not set');
    const subscriptionName = (item) => item.subscription_display?.plan_name ?? t('Not set');
    const subscriptionRenewal = (item) => item.subscription_display?.valid_until ?? t('Not set');
    const subscriptionStatusLabel = (item) => item.subscription_display?.status_label ?? t('Not set');
    const subscriptionStatusChip = (item) => {
        switch (item.subscription_display?.status) {
            case 'active':
                return 'ui-chip-success';
            case 'trial_active':
            case 'billing_pending':
                return 'ui-chip-warning';
            case 'trial_expired':
            case 'payment_required':
                return 'ui-chip-danger';
            case 'inherited':
                return 'ui-chip-info';
            default:
                return 'ui-chip-neutral';
        }
    };
    const organizationTypeChip = (item) => item.organization_type === 'branch' ? 'ui-chip-neutral' : 'ui-chip-info';
    const organizationTypeLabel = (item) => item.organization_type === 'branch'
        ? t('Branch')
        : t('Main organization');
    const statusToneVar = (item) => {
        switch (item.subscription_display?.status) {
            case 'active':
                return '--ui-success';
            case 'trial_active':
            case 'billing_pending':
                return '--ui-warning';
            case 'trial_expired':
            case 'payment_required':
                return '--ui-danger';
            case 'inherited':
                return '--ui-primary';
            default:
                return '--ui-muted';
        }
    };
    const cardAccentStyle = (item) => ({ '--org-tone': `var(${statusToneVar(item)})` });
    const orgInitial = (item) => (item.name ?? '').trim().charAt(0).toUpperCase() || '#';
    const structureLabel = (item) => item.organization_type === 'main'
        ? t('Branch workspaces')
        : t('Parent organization');
    const branchCountLabel = (item) => {
        const count = Number(item.branch_summary?.count ?? 0);

        return `${count} ${count === 1 ? t('Branch') : t('Branches')}`;
    };
    const memberCountLabel = (item) => `${item.teams_count ?? 0} ${t('members')}`;
    const structurePrimary = (item) => item.organization_type === 'main'
        ? ((item.branch_summary?.count ?? 0) > 0 ? branchCountLabel(item) : t('No branches yet'))
        : (item.parent_organization?.name ?? t('Not linked'));
    const organizationContext = (item) => {
        if (item.organization_type === 'branch' && item.billing_owner?.name && item.billing_owner.name !== (item.parent_organization?.name ?? null)) {
            return `${t('Billing owner')}: ${item.billing_owner.name}`;
        }

        if (item.subscription_display?.managed_by_parent) {
            return `${t('Inherited from')}: ${item.subscription_display?.managed_by ?? t('Parent organization')}`;
        }

        if (item.billing_owner?.name && item.billing_owner.name !== item.name) {
            return `${t('Billing owner')}: ${item.billing_owner.name}`;
        }

        return null;
    };
    const branchPreview = (item) => Array.isArray(item.branch_summary?.preview) ? item.branch_summary.preview.slice(0, 2) : [];
    const extraBranchesCount = (item) => Math.max(0, Number(item.branch_summary?.count ?? 0) - branchPreview(item).length);
</script>

<template>
    <div class="org-toolbar">
        <div class="org-toolbar-row org-toolbar-row--primary">
            <div class="ui-table-search org-toolbar-search">
                <span class="ui-table-search-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 15l6 6m-11-4a7 7 0 1 1 0-14a7 7 0 0 1 0 14Z"/></svg>
                </span>
                <input @input="search" v-model="params.search" type="text" class="ui-table-search-input" :placeholder="$t('Search by organization, owner name, email, or phone')">
                <button v-if="isSearching === false && params.search" @click="clearSearch" type="button" class="ui-table-search-action">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10s10-4.5 10-10S17.5 2 12 2zm3.7 12.3c.4.4.4 1 0 1.4c-.4.4-.4 1 0 1.4c-.4.4-1 .4-1.4 0L12 13.4l-2.3 2.3c-.4.4-1 .4-1.4 0c-.4-.4-.4-1 0-1.4l2.3-2.3l-2.3-2.3c-.4-.4-.4-1 0-1.4c.4-.4 1-.4 1.4 0l2.3 2.3l2.3-2.3c.4-.4 1-.4 1.4 0c.4.4.4 1 0 1.4L13.4 12l2.3 2.3z"/></svg>
                </button>
                <span v-if="isSearching" class="ui-table-search-action">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><circle cx="12" cy="3.5" r="1.5" fill="currentColor" opacity="0"><animateTransform attributeName="transform" calcMode="discrete" dur="2.4s" repeatCount="indefinite" type="rotate" values="0 12 12;90 12 12;180 12 12;270 12 12"/><animate attributeName="opacity" dur="0.6s" keyTimes="0;0.5;1" repeatCount="indefinite" values="1;1;0"/></circle><circle cx="12" cy="3.5" r="1.5" fill="currentColor" opacity="0"><animateTransform attributeName="transform" begin="0.2s" calcMode="discrete" dur="2.4s" repeatCount="indefinite" type="rotate" values="30 12 12;120 12 12;210 12 12;300 12 12"/><animate attributeName="opacity" begin="0.2s" dur="0.6s" keyTimes="0;0.5;1" repeatCount="indefinite" values="1;1;0"/></circle><circle cx="12" cy="3.5" r="1.5" fill="currentColor" opacity="0"><animateTransform attributeName="transform" begin="0.4s" calcMode="discrete" dur="2.4s" repeatCount="indefinite" type="rotate" values="60 12 12;150 12 12;240 12 12;330 12 12"/><animate attributeName="opacity" begin="0.4s" dur="0.6s" keyTimes="0;0.5;1" repeatCount="indefinite" values="1;1;0"/></circle></svg>
                </span>
            </div>

            <div class="org-filter-group org-filter-group--segmented">
                <button
                    v-for="option in typeOptions"
                    :key="option.value"
                    type="button"
                    class="org-filter-pill org-filter-pill--segmented"
                    :class="{ 'org-filter-pill--active': params.organization_type === option.value }"
                    @click="applyType(option.value)"
                >
                    {{ option.label }}
                </button>
            </div>
        </div>

        <div class="org-toolbar-divider"></div>

        <div class="org-toolbar-row org-toolbar-row--secondary">
            <div class="org-filter-group">
                <button
                    v-for="option in statusOptions"
                    :key="option.label"
                    type="button"
                    class="org-filter-pill"
                    :class="{ 'org-filter-pill--active': params.subscription_status === option.value }"
                    @click="applyStatus(option.value)"
                >
                    <span class="org-filter-pill-dot" :class="'org-filter-pill-dot--' + (option.value ?? 'all')"></span>
                    {{ option.label }}
                </button>
            </div>

            <div class="org-toolbar-selects">
                <select v-model="params.plan_uuid" @change="changeFilter" class="org-toolbar-select">
                    <option :value="null">{{ $t('All plans') }}</option>
                    <option v-for="plan in props.plans" :key="plan.uuid" :value="plan.uuid">{{ plan.name }}</option>
                </select>
                <select v-if="params.organization_type !== 'branch'" v-model="params.branches_scope" @change="changeFilter" class="org-toolbar-select">
                    <option v-for="option in branchesScopeOptions" :key="option.label" :value="option.value">{{ $t(option.label) }}</option>
                </select>
                <button v-if="hasAdvancedFilters" type="button" @click="clearAllFilters" class="org-toolbar-clear">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7h16M8 7V4h8v3m-9 0 1 13h8l1-13"/></svg>
                    {{ $t('Clear filters') }}
                </button>
            </div>
        </div>
    </div>

    <div v-if="isDirectoryVariant" class="org-list">
        <template v-if="rows.data.length">
            <div class="org-grid">
            <article
                v-for="item in rows.data"
                :key="item.uuid"
                class="org-card"
                :style="cardAccentStyle(item)"
            >
                <div class="org-card-top">
                    <div class="org-card-identity">
                        <span class="org-avatar" :class="item.organization_type === 'branch' ? 'org-avatar--branch' : 'org-avatar--main'">
                            {{ orgInitial(item) }}
                        </span>
                        <div class="min-w-0 flex-1 space-y-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="org-card-name">{{ item.name }}</h3>
                                <span class="org-chip" :class="organizationTypeChip(item)">
                                    {{ organizationTypeLabel(item) }}
                                </span>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="org-chip org-chip--status" :class="subscriptionStatusChip(item)">
                                    <span class="org-chip-dot"></span>
                                    {{ subscriptionStatusLabel(item) }}
                                </span>
                                <span v-if="item.subscription_display?.managed_by_parent" class="org-chip ui-chip-warning">
                                    {{ $t('Billing') }}
                                </span>
                            </div>
                            <p v-if="organizationContext(item)" class="org-card-context">
                                {{ organizationContext(item) }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-start gap-2 self-start">
                        <Link :href="'/admin/organizations/' + item.uuid" class="org-card-view-btn">
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.036 12.322a1.012 1.012 0 0 1 0-.644C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.437 0 .644C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 1 1-6 0a3 3 0 0 1 6 0Z"/></svg>
                            {{ $t('View') }}
                        </Link>
                        <Dropdown v-if="canDelete" :align="'right'" class="self-start">
                            <button type="button" class="org-card-kebab-btn">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24">
                                    <path fill="currentColor" d="M12 16a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2a2 2 0 0 1 2-2m0-6a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2a2 2 0 0 1 2-2m0-6a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2a2 2 0 0 1 2-2Z"/>
                                </svg>
                            </button>
                            <template #items>
                                <DropdownItemGroup>
                                    <DropdownItem :href="'/admin/organizations/' + item.uuid">{{ $t('View organization') }}</DropdownItem>
                                    <DropdownItem as="button" @click="openAlert(item.uuid)">{{ $t('Delete') }}</DropdownItem>
                                </DropdownItemGroup>
                            </template>
                        </Dropdown>
                    </div>
                </div>

                <div class="org-card-tiles">
                    <div class="org-tile">
                        <div class="org-tile-head">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 12a4 4 0 1 0 0-8a4 4 0 0 0 0 8Zm-7 8c0-3.314 3.134-6 7-6s7 2.686 7 6"/></svg>
                            <p class="org-tile-label">{{ $t('Owner') }}</p>
                        </div>
                        <p class="org-tile-value">{{ ownerName(item) }}</p>
                        <p class="org-tile-sub break-all">{{ ownerEmail(item) }}</p>
                    </div>

                    <div class="org-tile">
                        <div class="org-tile-head">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h2m3 0h5M5 5h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z"/></svg>
                            <p class="org-tile-label">{{ $t('Plan') }}</p>
                        </div>
                        <p class="org-tile-value">{{ subscriptionName(item) }}</p>
                        <p class="org-tile-sub">{{ subscriptionRenewal(item) }}</p>
                    </div>

                    <div class="org-tile">
                        <div class="org-tile-head">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 3v8a4 4 0 0 0 4 4h4M6 3a2 2 0 1 0 0 4a2 2 0 0 0 0-4Zm12 14a2 2 0 1 1-4 0a2 2 0 0 1 4 0Zm0-10a2 2 0 1 1-4 0a2 2 0 0 1 4 0Z"/></svg>
                            <p class="org-tile-label">{{ structureLabel(item) }}</p>
                        </div>
                        <p class="org-tile-value">{{ structurePrimary(item) }}</p>
                        <p class="org-tile-sub">{{ memberCountLabel(item) }}</p>
                        <div v-if="item.organization_type === 'main' && branchPreview(item).length" class="mt-2 flex flex-wrap gap-2">
                            <span
                                v-for="branch in branchPreview(item)"
                                :key="branch.uuid"
                                class="org-branch-pill"
                            >
                                {{ branch.name }}
                            </span>
                            <span
                                v-if="extraBranchesCount(item) > 0"
                                class="org-branch-pill org-branch-pill--muted"
                            >
                                +{{ extraBranchesCount(item) }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="org-card-footer">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0a9 9 0 0 1 18 0Z"/></svg>
                    <span>{{ item.updated_at }}</span>
                </div>
            </article>
            </div>

            <Pagination class="org-pagination" :pagination="rows.meta" />
        </template>

        <UiEmptyState
            v-else
            :title="$t('No organizations found')"
            :description="$t('Try adjusting the search or filter to find organizations.')"
        >
            <template #icon>
                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24"><path fill="currentColor" d="M10 3a7 7 0 1 0 4.192 12.606l4.1 4.101l1.415-1.414l-4.1-4.101A7 7 0 0 0 10 3m0 2a5 5 0 1 1 0 10a5 5 0 0 1 0-10"/></svg>
            </template>
        </UiEmptyState>
    </div>

    <template v-else>
        <Table :rows="rows">
            <TableHeader>
                <TableHeaderRow>
                    <TableHeaderRowItem :position="'first'">{{ $t('Name') }}</TableHeaderRowItem>
                    <TableHeaderRowItem class="hidden sm:table-cell">{{ $t('Owner') }}</TableHeaderRowItem>
                    <TableHeaderRowItem class="hidden sm:table-cell">{{ $t('Owner email') }}</TableHeaderRowItem>
                    <TableHeaderRowItem class="hidden sm:table-cell">
                        <span>{{ $t('Seats') }}</span>
                    </TableHeaderRowItem>
                    <TableHeaderRowItem class="hidden sm:table-cell">{{ $t('Organization type') }}</TableHeaderRowItem>
                    <TableHeaderRowItem class="hidden sm:table-cell">{{ $t('Parent organization') }}</TableHeaderRowItem>
                    <TableHeaderRowItem class="hidden sm:table-cell">{{ $t('Plan') }}</TableHeaderRowItem>
                    <TableHeaderRowItem class="hidden sm:table-cell">{{ $t('Next billing date') }}</TableHeaderRowItem>
                    <TableHeaderRowItem class="hidden sm:table-cell">
                        <span class="block text-end">{{ $t('Last updated') }}</span>
                    </TableHeaderRowItem>
                    <TableHeaderRowItem :position="'last'"></TableHeaderRowItem>
                </TableHeaderRow>
            </TableHeader>
            <TableBody>
                <TableBodyRow v-for="(item, index) in rows.data" :key="index" :class="!isLastRow(index) ? 'border-b' : ''">
                    <TableBodyRowItem :position="'first'" class="capitalize">
                        <div class="space-y-1">
                            <div>{{ item.name }}</div>
                            <div v-if="item.organization_type === 'branch'" class="org-table-subtext">
                                {{ $t('Branch of') }}: {{ item.parent_organization?.name ?? $t('Not linked') }}
                            </div>
                        </div>
                    </TableBodyRowItem>
                    <TableBodyRowItem class="hidden sm:table-cell">{{ ownerName(item) }}</TableBodyRowItem>
                    <TableBodyRowItem class="hidden sm:table-cell">{{ ownerEmail(item) }}</TableBodyRowItem>
                    <TableBodyRowItem class="hidden sm:table-cell">
                        <span>{{ item.teams_count }}</span>
                    </TableBodyRowItem>
                    <TableBodyRowItem class="hidden sm:table-cell">
                        <span class="org-chip" :class="organizationTypeChip(item)">
                            {{ organizationTypeLabel(item) }}
                        </span>
                    </TableBodyRowItem>
                    <TableBodyRowItem class="hidden sm:table-cell">
                        <span>{{ item.parent_organization?.name ?? $t('Not linked') }}</span>
                    </TableBodyRowItem>
                    <TableBodyRowItem class="hidden sm:table-cell">
                        <div class="space-y-1">
                            <span class="org-chip ui-chip-neutral">
                                {{ item.subscription_display?.plan_name ?? $t('Not set') }}
                            </span>
                            <div v-if="item.subscription_display?.managed_by_parent" class="org-table-subtext">
                                {{ $t('Inherited from') }}: {{ item.subscription_display?.managed_by ?? $t('Parent organization') }}
                            </div>
                        </div>
                    </TableBodyRowItem>
                    <TableBodyRowItem class="capitalize">
                        <span class="org-chip" :class="subscriptionStatusChip(item)">
                            {{ item.subscription_display?.valid_until ?? $t('Not set') }}
                        </span>
                    </TableBodyRowItem>
                    <TableBodyRowItem class="hidden sm:table-cell">
                        <span class="block text-end">{{ item.updated_at }}</span>
                    </TableBodyRowItem>
                    <TableBodyRowItem :position="'last'">
                        <Dropdown :align="'right'" class="mt-2">
                            <button type="submit" class="inline-flex w-full justify-center rounded-md text-sm font-medium text-black hover:bg-opacity-30 focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-opacity-75">
                                <span class="hover-ui-bg-soft hover:rounded-full w-[fit-content] p-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                                        <path fill="currentColor" d="M12 16a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2a2 2 0 0 1 2-2m0-6a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2a2 2 0 0 1 2-2m0-6a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2a2 2 0 0 1 2-2Z"/>
                                    </svg>
                                </span>
                            </button>
                            <template #items>
                                <DropdownItemGroup>
                                    <DropdownItem :href="'/admin/organizations/' + item.uuid">{{ $t('View/edit') }}</DropdownItem>
                                    <DropdownItem v-if="canDelete" as="button" @click="openAlert(item.uuid)">{{ $t('Delete') }}</DropdownItem>
                                </DropdownItemGroup>
                            </template>
                        </Dropdown>
                    </TableBodyRowItem>
                </TableBodyRow>
            </TableBody>
        </Table>
    </template>

    <AlertModal
        v-model="isOpenAlert"
        @confirm="() => confirmAlert(deleteAction)"
        :label = "$t('Delete row')"
        :description = "$t('Are you sure you want to delete this row? This action can not be undone')"
    />
</template>

<style scoped>
.org-toolbar {
    display: flex;
    flex-direction: column;
    gap: 0.9rem;
    margin-bottom: 1.5rem;
    padding: 1.1rem 1.25rem;
    border: 1px solid var(--ui-border);
    border-radius: var(--ui-radius-xl, 1.25rem);
    background: linear-gradient(160deg, color-mix(in srgb, var(--ui-secondary) 5%, var(--ui-surface)), var(--ui-surface));
    box-shadow: var(--ui-shadow-1);
}

.org-toolbar-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.75rem;
}

.org-toolbar-row--primary {
    justify-content: space-between;
}

.org-toolbar-row--secondary {
    justify-content: space-between;
}

.org-toolbar-divider {
    height: 1px;
    background: var(--ui-border);
}

.org-toolbar-search {
    margin-bottom: 0;
    width: min(100%, 24rem);
    min-height: 2.9rem;
    border-color: var(--ui-border-strong);
}

.org-toolbar-search :deep(.ui-table-search-input) {
    font-size: 0.92rem;
}

.org-toolbar-selects {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
}

.org-filter-group {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}

.org-filter-group--segmented {
    padding: 0.25rem;
    border-radius: 999px;
    background: var(--ui-surface-soft);
    border: 1px solid var(--ui-border);
}

.org-filter-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    border-radius: 999px;
    padding: 0.5rem 0.95rem;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--ui-muted);
    background: var(--ui-surface-soft);
    border: 1px solid var(--ui-border);
    transition: background-color 160ms ease, color 160ms ease, border-color 160ms ease, transform 160ms ease;
}

.org-filter-pill--segmented {
    background: transparent;
    border-color: transparent;
}

.org-filter-pill:hover {
    color: var(--ui-text);
    background: var(--ui-border);
    transform: translateY(-1px);
}

.org-filter-pill--active {
    color: #fff;
    background: linear-gradient(135deg, var(--ui-secondary), color-mix(in srgb, var(--ui-secondary) 78%, var(--ui-primary)));
    border-color: transparent;
    box-shadow: 0 10px 20px -12px color-mix(in srgb, var(--ui-secondary) 60%, rgba(15, 23, 42, 0.5));
}

.org-filter-pill--active:hover {
    background: linear-gradient(135deg, var(--ui-secondary), color-mix(in srgb, var(--ui-secondary) 78%, var(--ui-primary)));
    transform: translateY(-1px);
}

.org-filter-pill-dot {
    width: 6px;
    height: 6px;
    border-radius: 999px;
    background: var(--ui-muted);
    flex-shrink: 0;
}

.org-filter-pill-dot--active { background: var(--ui-success); }
.org-filter-pill-dot--trial { background: var(--ui-warning); }
.org-filter-pill-dot--expired { background: var(--ui-danger); }

.org-filter-pill--active .org-filter-pill-dot {
    background: #fff;
}

.org-toolbar-select {
    border: 1px solid var(--ui-border);
    border-radius: 0.8rem;
    background: var(--ui-surface);
    color: var(--ui-text);
    padding: 0.55rem 0.9rem;
    font-size: 0.88rem;
    outline: none;
    transition: border-color 160ms ease, box-shadow 160ms ease;
}

.org-toolbar-select:focus {
    border-color: color-mix(in srgb, var(--ui-secondary) 40%, var(--ui-border));
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--ui-secondary) 12%, transparent);
}

.org-toolbar-clear {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    border: 1px dashed var(--ui-border-strong);
    border-radius: 0.8rem;
    background: transparent;
    color: var(--ui-muted);
    padding: 0.55rem 0.9rem;
    font-size: 0.88rem;
    font-weight: 600;
    transition: background-color 160ms ease, border-color 160ms ease, color 160ms ease;
}

.org-toolbar-clear:hover {
    background: var(--ui-surface-soft);
    border-color: var(--ui-danger);
    color: var(--ui-danger);
}

.org-list {
    display: flex;
    flex-direction: column;
}

.org-grid {
    display: grid;
    grid-template-columns: repeat(1, 1fr);
    gap: 1.1rem;
}

@media (min-width: 768px) {
    .org-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (min-width: 1280px) {
    .org-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

.org-pagination {
    margin-top: 1.5rem;
    padding-top: 1.25rem;
    border-top: 1px solid var(--ui-border);
}

.org-card {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 1.15rem;
    padding: 1.4rem;
    padding-top: 1.55rem;
    border: 1px solid var(--ui-border);
    border-radius: var(--ui-radius-lg, 1.25rem);
    background: var(--ui-surface);
    overflow: hidden;
    transition: box-shadow 200ms ease, border-color 200ms ease, transform 200ms ease;
}

.org-card::before {
    content: '';
    position: absolute;
    inset-inline: 0;
    top: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--org-tone, var(--ui-muted)), color-mix(in srgb, var(--org-tone, var(--ui-muted)) 40%, transparent));
}

.org-card:hover {
    border-color: color-mix(in srgb, var(--org-tone, var(--ui-border-strong)) 45%, var(--ui-border-strong));
    box-shadow: var(--ui-shadow-2);
    transform: translateY(-3px);
}

.org-card-top {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    align-items: flex-start;
    justify-content: space-between;
}

@media (min-width: 1024px) {
    .org-card-top {
        flex-direction: row;
    }
}

.org-card-identity {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    min-width: 0;
    flex: 1;
}

.org-avatar {
    display: inline-flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    width: 2.75rem;
    height: 2.75rem;
    border-radius: 0.9rem;
    font-size: 1.1rem;
    font-weight: 800;
    color: #fff;
}

.org-avatar--main {
    background: linear-gradient(135deg, var(--ui-secondary), color-mix(in srgb, var(--ui-secondary) 70%, var(--ui-primary)));
    box-shadow: 0 8px 18px -10px color-mix(in srgb, var(--ui-secondary) 70%, transparent);
}

.org-avatar--branch {
    background: color-mix(in srgb, var(--ui-text) 12%, var(--ui-surface-soft));
    color: var(--ui-text);
    border: 1px dashed var(--ui-border-strong);
}

.org-card-name {
    font-size: 1.1rem;
    font-weight: 800;
    letter-spacing: -0.01em;
    color: var(--ui-text);
}

@media (min-width: 1024px) {
    .org-card-name {
        font-size: 1.18rem;
    }
}

.org-card-context {
    font-size: 0.9rem;
    color: var(--ui-muted);
}

.org-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    border-radius: 999px;
    padding: 0.32rem 0.8rem;
    font-size: 0.8rem;
    font-weight: 700;
    white-space: nowrap;
}

.org-chip-dot {
    width: 6px;
    height: 6px;
    border-radius: 999px;
    background: currentColor;
    flex-shrink: 0;
}

.org-card-view-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    border-radius: 0.75rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    color: var(--ui-text);
    padding: 0.55rem 0.9rem;
    font-size: 0.92rem;
    font-weight: 600;
    transition: background-color 160ms ease, border-color 160ms ease;
}

.org-card-view-btn:hover {
    background: var(--ui-surface-soft);
    border-color: var(--ui-border-strong);
}

.org-card-kebab-btn {
    display: inline-flex;
    height: 2.5rem;
    width: 2.5rem;
    align-items: center;
    justify-content: center;
    border-radius: 0.75rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    color: var(--ui-muted);
    transition: background-color 160ms ease, color 160ms ease;
}

.org-card-kebab-btn:hover {
    background: var(--ui-surface-soft);
    color: var(--ui-text);
}

.org-card-tiles {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 0.75rem;
}

.org-tile {
    border-radius: 0.9rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface-soft);
    padding: 0.9rem 1.05rem;
    transition: border-color 160ms ease;
}

.org-tile:hover {
    border-color: var(--ui-border-strong);
}

.org-tile-head {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    color: var(--ui-muted);
}

.org-tile-label {
    font-size: 0.76rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    color: var(--ui-muted);
}

.org-tile-value {
    margin-top: 0.3rem;
    font-size: 0.98rem;
    font-weight: 700;
    color: var(--ui-text);
}

.org-tile-sub {
    margin-top: 0.2rem;
    font-size: 0.8rem;
    color: var(--ui-muted);
}

.org-branch-pill {
    border-radius: 999px;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    padding: 0.22rem 0.7rem;
    font-size: 0.76rem;
    color: var(--ui-muted);
}

.org-branch-pill--muted {
    border-style: dashed;
}

.org-card-footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.35rem;
    margin-top: auto;
    border-top: 1px solid var(--ui-border);
    padding-top: 0.75rem;
    font-size: 0.8rem;
    color: var(--ui-muted);
}

.org-table-subtext {
    font-size: 0.8rem;
    color: var(--ui-muted);
}
</style>
