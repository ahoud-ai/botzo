<script setup>
    import { computed, ref } from 'vue';
    import debounce from 'lodash/debounce';
    import { Link, router, useForm } from '@inertiajs/vue3';
    import { useI18n } from 'vue-i18n';
    import AlertModal from '@/Components/AlertModal.vue';
    import { useAlertModal } from '@/Composables/useAlertModal';
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
    const form = useForm({ test: null });
    const isSearching = ref(false);
    const { t } = useI18n();

    const params = ref({
        search: props.filters?.search ?? null,
        organization_type: props.filters?.organization_type ?? null,
        subscription_status: props.filters?.subscription_status ?? null,
        plan_uuid: props.filters?.plan_uuid ?? null,
        branches_scope: props.filters?.branches_scope ?? null,
    });

    const isDirectoryVariant = computed(() => props.variant === 'directory');
    const statusOptions = computed(() => ([
        { value: null, label: t('All subscription statuses') },
        { value: 'active', label: t('Active') },
        { value: 'trial', label: t('Trial period') },
        { value: 'expired', label: t('Expired') },
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
    const subscriptionStatusClass = (item) => item.subscription_display?.status === 'expired'
        ? 'bg-rose-100 text-rose-700'
        : item.subscription_display?.status === 'trial'
            ? 'bg-amber-100 text-amber-700'
            : item.subscription_display?.status === 'active'
                ? 'bg-emerald-100 text-emerald-700'
                : 'bg-slate-100 text-slate-600';
    const organizationTypeClass = (item) => item.organization_type === 'branch'
        ? 'bg-sky-100 text-sky-700'
        : 'bg-indigo-100 text-indigo-700';
    const organizationTypeLabel = (item) => item.organization_type === 'branch'
        ? t('Branch')
        : t('Main organization');
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
    <div class="mb-5 grid gap-3 xl:grid-cols-[minmax(0,1.35fr)_repeat(3,minmax(0,0.72fr))_auto]">
        <div class="ui-table-search mb-0 w-full max-w-none">
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
        <select v-model="params.subscription_status" @change="changeFilter" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-primary/40 focus:ring-2 focus:ring-primary/10">
            <option v-for="option in statusOptions" :key="option.label" :value="option.value">{{ $t(option.label) }}</option>
        </select>
        <select v-model="params.plan_uuid" @change="changeFilter" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-primary/40 focus:ring-2 focus:ring-primary/10">
            <option :value="null">{{ $t('All plans') }}</option>
            <option v-for="plan in props.plans" :key="plan.uuid" :value="plan.uuid">{{ plan.name }}</option>
        </select>
        <select v-if="params.organization_type !== 'branch'" v-model="params.branches_scope" @change="changeFilter" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-primary/40 focus:ring-2 focus:ring-primary/10">
            <option v-for="option in branchesScopeOptions" :key="option.label" :value="option.value">{{ $t(option.label) }}</option>
        </select>
        <button v-if="hasAdvancedFilters" type="button" @click="clearAllFilters" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">
            {{ $t('Clear filters') }}
        </button>
    </div>

    <div v-if="isDirectoryVariant" class="mt-6 space-y-4">
        <template v-if="rows.data.length">
            <article
                v-for="item in rows.data"
                :key="item.uuid"
                class="rounded-[1.1rem] border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/55"
            >
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0 flex-1 space-y-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-base font-semibold text-slate-900 lg:text-lg">{{ item.name }}</h3>
                            <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="organizationTypeClass(item)">
                                {{ organizationTypeLabel(item) }}
                            </span>
                            <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="subscriptionStatusClass(item)">
                                {{ subscriptionStatusLabel(item) }}
                            </span>
                            <span v-if="item.subscription_display?.managed_by_parent" class="rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-700">
                                {{ $t('Billing') }}
                            </span>
                        </div>

                        <p v-if="organizationContext(item)" class="text-sm text-slate-500">
                            {{ organizationContext(item) }}
                        </p>
                    </div>
                    <div class="flex items-start gap-2 self-start">
                        <Link :href="'/admin/organizations/' + item.uuid" class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                            {{ $t('View') }}
                        </Link>
                        <Dropdown :align="'right'" class="self-start">
                            <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 hover:text-slate-700">
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

                <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-3.5 py-3">
                        <p class="text-xs font-medium text-slate-500">{{ $t('Owner') }}</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ ownerName(item) }}</p>
                        <p class="mt-1 break-all text-xs text-slate-500">{{ ownerEmail(item) }}</p>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-3.5 py-3">
                        <p class="text-xs font-medium text-slate-500">{{ $t('Plan') }}</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ subscriptionName(item) }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ subscriptionRenewal(item) }}</p>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-3.5 py-3">
                        <p class="text-xs font-medium text-slate-500">{{ structureLabel(item) }}</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ structurePrimary(item) }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ memberCountLabel(item) }}</p>
                        <div v-if="item.organization_type === 'main' && branchPreview(item).length" class="mt-2 flex flex-wrap gap-2">
                            <span
                                v-for="branch in branchPreview(item)"
                                :key="branch.uuid"
                                class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[11px] text-slate-600"
                            >
                                {{ branch.name }}
                            </span>
                            <span
                                v-if="extraBranchesCount(item) > 0"
                                class="rounded-full border border-dashed border-slate-300 bg-white px-2.5 py-1 text-[11px] text-slate-500"
                            >
                                +{{ extraBranchesCount(item) }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="mt-3 flex items-center justify-end border-t border-slate-100 pt-3 text-xs text-slate-500">
                    <span>{{ item.updated_at }}</span>
                </div>
            </article>

            <Pagination class="mt-4" :pagination="rows.meta" />
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
                            <div v-if="item.organization_type === 'branch'" class="text-xs text-slate-500">
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
                        <span class="py-1 rounded-md text-xs px-3 ui-chip-neutral text-slate-700">
                            {{ item.organization_type === 'branch' ? $t('Branch') : $t('Main organization') }}
                        </span>
                    </TableBodyRowItem>
                    <TableBodyRowItem class="hidden sm:table-cell">
                        <span>{{ item.parent_organization?.name ?? $t('Not linked') }}</span>
                    </TableBodyRowItem>
                    <TableBodyRowItem class="hidden sm:table-cell">
                        <div class="space-y-1">
                            <span class="py-1 rounded-md text-xs px-3 ui-chip-neutral text-slate-700">
                                {{ item.subscription_display?.plan_name ?? $t('Not set') }}
                            </span>
                            <div v-if="item.subscription_display?.managed_by_parent" class="text-xs text-slate-500">
                                {{ $t('Inherited from') }}: {{ item.subscription_display?.managed_by ?? $t('Parent organization') }}
                            </div>
                        </div>
                    </TableBodyRowItem>
                    <TableBodyRowItem class="capitalize">
                        <span class="py-1 rounded-md text-xs px-3 ui-chip-neutral text-slate-700">
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
                                    <DropdownItem as="button" @click="openAlert(item.uuid)">{{ $t('Delete') }}</DropdownItem>
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
