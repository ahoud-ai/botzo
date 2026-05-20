<script setup>
    import { Link, usePage } from "@inertiajs/vue3";
    import { ref, computed } from 'vue';
    import { useI18n } from 'vue-i18n';
    import { buildI18nLookupCandidates } from '@/Utils/i18nLookup';
    import debounce from 'lodash/debounce';
    import { router, useForm } from '@inertiajs/vue3';
    import AlertModal from '@/Components/AlertModal.vue';
    import { useAlertModal } from '@/Composables/useAlertModal';
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

    const props = defineProps({
        rows: {
            type: Object,
            required: true,
        },
        filters: {
            type: Object
        }
    });

    const { isOpenAlert, openAlert, confirmAlert } = useAlertModal();
    const { t, te } = useI18n();

    // Helper function to check permissions
    const hasPermission = (permission) => {
        const pageProps = usePage().props;
        const permissions = pageProps?.permissions;
        if (!permissions || !Array.isArray(permissions)) {
            return false;
        }
        return permissions.includes('*') || permissions.includes(permission);
    };

    const canViewAll = computed(() => hasPermission('campaigns.view_all'));
    const canView = computed(() => hasPermission('campaigns.view'));
    const canDelete = computed(() => hasPermission('campaigns.delete'));
    const canAdd = computed(() => hasPermission('campaigns.add'));

    const form = useForm({'test': null});

    const deleteAction = (key) => {
        if (!canDelete.value) {
            return;
        }
        form.delete('/campaigns/' + key);
    }
    
    const params = ref({
        search: props.filters.search,
    });

    const isLastRow = (index) => {
      return index === props.rows.data.length - 1;
    }

    const isSearching = ref(false);
    const emit = defineEmits(['delete']);

    const formatPercentageRate = (numeratorCount, contactCount, contactGroupCount) => {
        if(contactCount > 0){
            return (numeratorCount/contactCount * 100).toFixed(2) + '%';
        } else {
            return (numeratorCount/contactGroupCount * 100).toFixed(2) + '%';
        }
    };

    const formatStats = (numeratorCount, contactCount, contactGroupCount) => {
        if(contactCount > 0){
            return numeratorCount + '/' + contactCount;
        } else {
            return numeratorCount + '/' + contactGroupCount;
        }
    }

    const clearSearch = () => {
        params.value.search = null;
        runSearch();
    }

    const search = debounce(() => {
        isSearching.value = true;
        runSearch();
    }, 1000);

    const runSearch = () => {
        router.visit('/campaigns', {
            method: 'get',
            data: params.value,
        })
    }

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
</script>
<template>
    <div class="ui-table-search">
        <span class="ui-table-search-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 15l6 6m-11-4a7 7 0 1 1 0-14a7 7 0 0 1 0 14Z"/></svg>
        </span>
        <input @input="search" v-model="params.search" type="text" class="ui-table-search-input" :placeholder="$t('Search campaigns')">
        <button v-if="isSearching === false && params.search" @click="clearSearch" type="button" class="ui-table-search-action">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10s10-4.5 10-10S17.5 2 12 2zm3.7 12.3c.4.4.4 1 0 1.4c-.4.4-1 .4-1.4 0L12 13.4l-2.3 2.3c-.4.4-1 .4-1.4 0c-.4-.4-.4-1 0-1.4l2.3-2.3l-2.3-2.3c-.4-.4-.4-1 0-1.4c.4-.4 1-.4 1.4 0l2.3 2.3l2.3-2.3c.4-.4 1-.4 1.4 0c.4.4.4 1 0 1.4L13.4 12l2.3 2.3z"/></svg>
        </button>
        <span v-if="isSearching" class="ui-table-search-action">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><circle cx="12" cy="3.5" r="1.5" fill="currentColor" opacity="0"><animateTransform attributeName="transform" calcMode="discrete" dur="2.4s" repeatCount="indefinite" type="rotate" values="0 12 12;90 12 12;180 12 12;270 12 12"/><animate attributeName="opacity" dur="0.6s" keyTimes="0;0.5;1" repeatCount="indefinite" values="1;1;0"/></circle><circle cx="12" cy="3.5" r="1.5" fill="currentColor" opacity="0"><animateTransform attributeName="transform" begin="0.2s" calcMode="discrete" dur="2.4s" repeatCount="indefinite" type="rotate" values="30 12 12;120 12 12;210 12 12;300 12 12"/><animate attributeName="opacity" begin="0.2s" dur="0.6s" keyTimes="0;0.5;1" repeatCount="indefinite" values="1;1;0"/></circle><circle cx="12" cy="3.5" r="1.5" fill="currentColor" opacity="0"><animateTransform attributeName="transform" begin="0.4s" calcMode="discrete" dur="2.4s" repeatCount="indefinite" type="rotate" values="60 12 12;150 12 12;240 12 12;330 12 12"/><animate attributeName="opacity" begin="0.4s" dur="0.6s" keyTimes="0;0.5;1" repeatCount="indefinite" values="1;1;0"/></circle></svg>
        </span>
    </div>
    <Table :rows="rows">
        <TableHeader>
            <TableHeaderRow>
                <TableHeaderRowItem :position="'first'">{{ $t('Campaign name') }}</TableHeaderRowItem>
                <TableHeaderRowItem class="hidden md:table-cell">{{ $t('Template') }}</TableHeaderRowItem>
                <TableHeaderRowItem>{{ $t('Delivery rate') }}</TableHeaderRowItem>
                <TableHeaderRowItem>{{ $t('Read rate') }}</TableHeaderRowItem>
                <TableHeaderRowItem>{{ $t('Status') }}</TableHeaderRowItem>
                <TableHeaderRowItem :position="'last'"></TableHeaderRowItem>
            </TableHeaderRow>
        </TableHeader>
        <TableBody>
            <TableBodyRow v-for="(item, index) in rows.data" :key="index" :class="!isLastRow(index) ? 'border-b' : ''">
                <TableBodyRowItem :position="'first'">
                    <Link v-if="canView || canViewAll" :href="'/campaigns/' + item.uuid" class="block w-full">
                        {{ item.name }}
                    </Link>
                    <span v-else class="block w-full">{{ item.name }}</span>
                </TableBodyRowItem>
                <TableBodyRowItem class="hidden md:table-cell">
                    <Link v-if="canView || canViewAll" :href="'/campaigns/' + item.uuid" class="block w-full">
                        {{ item.template.name }}
                    </Link>
                    <span v-else class="block w-full">{{ item.template.name }}</span>
                </TableBodyRowItem>
                <TableBodyRowItem class="hidden sm:table-cell">
                    <Link v-if="canView || canViewAll" :href="'/campaigns/' + item.uuid" class="block w-full">
                        <div v-if="item.status == 'scheduled'">
                            {{ $t('N/A') }}
                        </div>
                        <div v-else>
                            <span class="bg-slate-200 px-1 py-1 rounded-lg me-1 hidden md:inline-block">
                                {{ formatPercentageRate(item.delivery_count, item.contacts_count, item.contact_group_count) }}
                            </span>
                            {{ formatStats(item.delivery_count, item.contacts_count, item.contact_group_count) }}
                        </div>
                    </Link>
                    <div v-else class="block w-full">
                        <div v-if="item.status == 'scheduled'">
                            {{ $t('N/A') }}
                        </div>
                        <div v-else>
                            <span class="bg-slate-200 px-1 py-1 rounded-lg me-1 hidden md:inline-block">
                                {{ formatPercentageRate(item.delivery_count, item.contacts_count, item.contact_group_count) }}
                            </span>
                            {{ formatStats(item.delivery_count, item.contacts_count, item.contact_group_count) }}
                        </div>
                    </div>
                </TableBodyRowItem>
                <TableBodyRowItem class="hidden sm:table-cell">
                    <Link v-if="canView || canViewAll" :href="'/campaigns/' + item.uuid" class="block w-full">
                        <div v-if="item.status == 'scheduled'">
                            {{ $t('N/A') }}
                        </div>
                        <div v-else>
                            <span class="bg-slate-200 px-1 py-1 rounded-lg me-1 hidden md:inline-block">
                                {{ formatPercentageRate(item.read_count, item.contacts_count, item.contact_group_count) }}
                            </span>
                            {{ formatStats(item.read_count, item.contacts_count, item.contact_group_count) }}
                        </div>
                    </Link>
                    <div v-else class="block w-full">
                        <div v-if="item.status == 'scheduled'">
                            {{ $t('N/A') }}
                        </div>
                        <div v-else>
                            <span class="bg-slate-200 px-1 py-1 rounded-lg me-1 hidden md:inline-block">
                                {{ formatPercentageRate(item.read_count, item.contacts_count, item.contact_group_count) }}
                            </span>
                            {{ formatStats(item.read_count, item.contacts_count, item.contact_group_count) }}
                        </div>
                    </div>
                </TableBodyRowItem>
                <TableBodyRowItem>
                    <Link v-if="canView || canViewAll" :href="'/campaigns/' + item.uuid" class="flex items-center w-full capitalize">
                        {{ translateDynamic(item.status) }}
                    </Link>
                    <span v-else class="flex items-center w-full capitalize">{{ translateDynamic(item.status) }}</span>
                </TableBodyRowItem>
                <TableBodyRowItem :position="'last'">
                    <Dropdown v-if="canView || canDelete" :align="'right'" class="mt-2">
                        <button type="submit" class="inline-flex w-full justify-center rounded-md text-sm font-medium text-black hover:bg-opacity-30 focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-opacity-75">
                            <span class="hover-ui-bg-soft hover:rounded-full w-[fit-content] p-2">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                                    <path fill="currentColor" d="M12 16a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2a2 2 0 0 1 2-2m0-6a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2a2 2 0 0 1 2-2m0-6a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2a2 2 0 0 1 2-2Z"/>
                                </svg>
                            </span>
                        </button>
                        <template #items>
                            <DropdownItemGroup>
                                <DropdownItem v-if="canView" :href="'/campaigns/' + item.uuid">{{ $t('View') }}</DropdownItem>
                                <DropdownItem v-if="canDelete" as="button" @click="openAlert(item.uuid)">{{ $t('Delete') }}</DropdownItem>
                            </DropdownItemGroup>
                        </template>
                    </Dropdown>
                </TableBodyRowItem>
            </TableBodyRow>
        </TableBody>
    </Table>

    <div v-if="rows.data.length == 0" class="bg-white rounded-xl">
        <div class="p-4 py-8">
            <div class="flex justify-center mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" width="72" height="72" viewBox="0 0 256 256"><path fill="currentColor" d="M216 80h-32V48a16 16 0 0 0-16-16H40a16 16 0 0 0-16 16v128a8 8 0 0 0 13 6.22L72 154v30a16 16 0 0 0 16 16h93.59L219 230.22a8 8 0 0 0 5 1.78a8 8 0 0 0 8-8V96a16 16 0 0 0-16-16M66.55 137.78L40 159.25V48h128v88H71.58a8 8 0 0 0-5.03 1.78M216 207.25l-26.55-21.47a8 8 0 0 0-5-1.78H88v-32h80a16 16 0 0 0 16-16V96h32Z"></path></svg>
            </div>
            <h3 class="text-center text-lg font-medium mb-4">{{ $t('You don\'t have any campaigns') }}</h3>
            <div class="flex justify-center">
                <Link v-if="canAdd" href="/campaigns/create" class="rounded-md px-3 py-2 text-sm hover:shadow-md text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 bg-primary" :disabled="isLoading">
                    <span v-if="!isLoading">{{ $t('Create campaign') }}</span>
                </Link>
                <button type="submit" v-else disabled class="rounded-md px-3 py-2 text-sm text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 bg-gray-400 cursor-not-allowed">
                    <span>{{ $t('Create campaign') }}</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Alert Modal Component-->
    <AlertModal 
        v-model="isOpenAlert" 
        @confirm="() => confirmAlert(deleteAction)"
        :label = "$t('Delete row')" 
        :description = "$t('Are you sure you want to delete this campaign? This action will only delete the campaign; sent messages will not be deleted from the chat history.')"
    />
</template>
  

