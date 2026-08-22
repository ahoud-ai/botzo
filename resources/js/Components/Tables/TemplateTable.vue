<script setup>
    import axios from 'axios';
    import AlertModal from '@/Components/AlertModal.vue';
    import { Link, router, usePage } from "@inertiajs/vue3";
    import { useAlertModal } from '@/Composables/useAlertModal';
    import { toast } from 'vue3-toastify';
    import { computed, ref } from 'vue';
    import { useI18n } from 'vue-i18n';
    import { buildI18nLookupCandidates } from '@/Utils/i18nLookup';
    import debounce from 'lodash/debounce';
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
    import UiEmptyState from '@/Components/UI/UiEmptyState.vue';
    import WhatsappTemplate from '@/Components/WhatsappTemplate.vue';

    const props = defineProps({
        rows: {
            type: Object,
            required: true,
        },
        filters: {
            type: Object,
            default: () => ({}),
        },
        languages: {
            type: Array,
            default: () => [],
        },
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

    const canAdd = computed(() => hasPermission('message_templates.add'));
    const canEdit = computed(() => hasPermission('message_templates.edit'));
    const canDelete = computed(() => hasPermission('message_templates.delete'));

    const deleteAction = async (key) => {
        if (!canDelete.value) {
            return;
        }
        try {
            const response = await axios.delete(`/templates/${key}`);

            if (response.status === 200 && response.data.success) {
                const idx = props.rows.data.findIndex((i) => i.uuid === key);
                props.rows.data.splice(idx, 1);
            }

            toast(response.data.message, {
                autoClose: 3000,
            });
        } catch {
            // Template deletion feedback is handled by the surrounding page state.
        }
    };

    const isLastRow = (index) => {
        return index === props.rows.data.length - 1;
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
    }

    const statusChipClass = (status) => {
        switch (String(status ?? '').toUpperCase()) {
            case 'APPROVED':
                return 'ui-chip-success';
            case 'PENDING':
            case 'IN_APPEAL':
                return 'ui-chip-warning';
            case 'REJECTED':
            case 'DISABLED':
                return 'ui-chip-danger';
            case 'PAUSED':
                return 'ui-chip-info';
            default:
                return 'ui-chip-neutral';
        }
    };

    const categoryStyles = {
        MARKETING: { color: '#e11d48', background: 'rgba(225, 29, 72, 0.12)' },
        UTILITY: { color: '#155dfc', background: 'rgba(21, 93, 252, 0.12)' },
        AUTHENTICATION: { color: '#7c3aed', background: 'rgba(124, 58, 237, 0.12)' },
    };

    const categoryStyle = (category) => categoryStyles[String(category ?? '').toUpperCase()] ?? { color: 'var(--ui-muted)', background: 'var(--ui-surface-soft)' };

    const languageMap = computed(() => {
        const map = new Map();
        (props.languages ?? []).forEach((lang) => map.set(lang.value, lang.label));
        return map;
    });

    const languageLabel = (code) => languageMap.value.get(code) ?? code;

    const toPreviewParameters = (metadataString) => {
        const fallback = { header: { format: null, text: null, parameters: [] }, body: { text: '' }, footer: { text: null }, buttons: [] };

        try {
            const parsed = JSON.parse(metadataString);
            const components = parsed?.components ?? [];
            const headerComp = components.find((c) => c.type === 'HEADER');
            const bodyComp = components.find((c) => c.type === 'BODY');
            const footerComp = components.find((c) => c.type === 'FOOTER');
            const buttonsComp = components.find((c) => c.type === 'BUTTONS');

            return {
                header: {
                    format: headerComp?.format ?? null,
                    text: headerComp?.text ?? null,
                    parameters: [],
                },
                body: { text: bodyComp?.text ?? '' },
                footer: { text: footerComp?.text ?? null },
                buttons: buttonsComp?.buttons ?? [],
            };
        } catch {
            return fallback;
        }
    };

    // Search & filters
    const params = ref({
        search: props.filters?.search ?? null,
        status: props.filters?.status ?? 'all',
        category: props.filters?.category ?? 'all',
    });

    const statusOptions = computed(() => ([
        { value: 'all', label: t('All statuses') },
        { value: 'APPROVED', label: t('Approved') },
        { value: 'PENDING', label: t('Pending') },
        { value: 'REJECTED', label: t('Rejected') },
        { value: 'PAUSED', label: t('Paused') },
    ]));

    const categoryOptions = computed(() => ([
        { value: 'all', label: t('All categories') },
        { value: 'MARKETING', label: t('Marketing') },
        { value: 'UTILITY', label: t('Utility') },
        { value: 'AUTHENTICATION', label: t('Authentication') },
    ]));

    const isSearching = ref(false);

    const clearSearch = () => {
        params.value.search = null;
        runSearch();
    };

    const search = debounce(() => {
        isSearching.value = true;
        runSearch();
    }, 600);

    const runSearch = () => {
        const filteredParams = Object.fromEntries(
            Object.entries(params.value).filter(([, value]) => value !== null && value !== 'all')
        );

        router.visit('/templates', {
            method: 'get',
            data: filteredParams,
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                isSearching.value = false;
            },
        });
    };

    const applyStatus = (value) => {
        params.value.status = value;
        runSearch();
    };

    const applyCategory = (value) => {
        params.value.category = value;
        runSearch();
    };
</script>
<template>
    <div class="template-toolbar">
        <div class="ui-table-search">
            <span class="ui-table-search-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 15l6 6m-11-4a7 7 0 1 1 0-14a7 7 0 0 1 0 14Z"/></svg>
            </span>
            <input @input="search" v-model="params.search" type="text" class="ui-table-search-input" :placeholder="$t('Search templates')">
            <button v-if="isSearching === false && params.search" @click="clearSearch" type="button" class="ui-table-search-action">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10s10-4.5 10-10S17.5 2 12 2zm3.7 12.3c.4.4.4 1 0 1.4c-.4.4-1 .4-1.4 0L12 13.4l-2.3 2.3c-.4.4-1 .4-1.4 0c-.4-.4-.4-1 0-1.4l2.3-2.3l-2.3-2.3c-.4-.4-.4-1 0-1.4c.4-.4 1-.4 1.4 0l2.3 2.3l2.3-2.3c.4-.4 1-.4 1.4 0c.4.4.4 1 0 1.4L13.4 12l2.3 2.3z"/></svg>
            </button>
            <span v-if="isSearching" class="ui-table-search-action">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><circle cx="12" cy="3.5" r="1.5" fill="currentColor" opacity="0"><animateTransform attributeName="transform" calcMode="discrete" dur="2.4s" repeatCount="indefinite" type="rotate" values="0 12 12;90 12 12;180 12 12;270 12 12"/><animate attributeName="opacity" dur="0.6s" keyTimes="0;0.5;1" repeatCount="indefinite" values="1;1;0"/></circle><circle cx="12" cy="3.5" r="1.5" fill="currentColor" opacity="0"><animateTransform attributeName="transform" begin="0.2s" calcMode="discrete" dur="2.4s" repeatCount="indefinite" type="rotate" values="30 12 12;120 12 12;210 12 12;300 12 12"/><animate attributeName="opacity" begin="0.2s" dur="0.6s" keyTimes="0;0.5;1" repeatCount="indefinite" values="1;1;0"/></circle><circle cx="12" cy="3.5" r="1.5" fill="currentColor" opacity="0"><animateTransform attributeName="transform" begin="0.4s" calcMode="discrete" dur="2.4s" repeatCount="indefinite" type="rotate" values="60 12 12;150 12 12;240 12 12;330 12 12"/><animate attributeName="opacity" begin="0.4s" dur="0.6s" keyTimes="0;0.5;1" repeatCount="indefinite" values="1;1;0"/></circle></svg>
            </span>
        </div>

        <div class="template-filter-group">
            <button
                v-for="option in statusOptions"
                :key="option.value"
                type="button"
                class="template-filter-pill"
                :class="{ 'template-filter-pill--active': params.status === option.value }"
                @click="applyStatus(option.value)"
            >
                {{ option.label }}
            </button>
        </div>

        <div class="template-filter-group">
            <button
                v-for="option in categoryOptions"
                :key="option.value"
                type="button"
                class="template-filter-pill"
                :class="{ 'template-filter-pill--active': params.category === option.value }"
                @click="applyCategory(option.value)"
            >
                {{ option.label }}
            </button>
        </div>
    </div>

    <Table :rows="rows">
        <TableHeader>
            <TableHeaderRow>
                <TableHeaderRowItem :position="'first'">{{ $t('Name') }}</TableHeaderRowItem>
                <TableHeaderRowItem class="hidden md:table-cell">{{ $t('Category') }}</TableHeaderRowItem>
                <TableHeaderRowItem class="hidden lg:table-cell">{{ $t('Language') }}</TableHeaderRowItem>
                <TableHeaderRowItem class="hidden xl:table-cell">{{ $t('Preview') }}</TableHeaderRowItem>
                <TableHeaderRowItem>{{ $t('Status') }}</TableHeaderRowItem>
                <TableHeaderRowItem class="hidden sm:table-cell">{{ $t('Last updated') }}</TableHeaderRowItem>
                <TableHeaderRowItem :position="'last'"></TableHeaderRowItem>
            </TableHeaderRow>
        </TableHeader>
        <TableBody>
            <TableBodyRow v-for="(item, index) in rows.data" :key="index">
                <TableBodyRowItem :position="'first'">
                    <span class="font-bold text-[16px] text-[var(--ui-text)]">{{ item.name }}</span>
                </TableBodyRowItem>
                <TableBodyRowItem class="hidden md:table-cell">
                    <span class="template-category-badge" :style="categoryStyle(item.category)">
                        {{ translateDynamic(item.category?.charAt(0) + item.category?.slice(1).toLowerCase()) }}
                    </span>
                </TableBodyRowItem>
                <TableBodyRowItem class="hidden lg:table-cell">
                    <span class="text-[var(--ui-muted)]">{{ languageLabel(item.language) }}</span>
                </TableBodyRowItem>
                <TableBodyRowItem class="hidden xl:table-cell">
                    <div class="template-preview-frame">
                        <WhatsappTemplate :parameters="toPreviewParameters(item.metadata)" :visible="true" :placeholder="true" />
                    </div>
                </TableBodyRowItem>
                <TableBodyRowItem>
                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[13px] font-bold capitalize" :class="statusChipClass(item.status)">
                        <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                        {{ translateDynamic(item.status) }}
                    </span>
                </TableBodyRowItem>
                <TableBodyRowItem class="hidden sm:table-cell">
                    <span class="text-[var(--ui-muted)]">{{ item.updated_at }}</span>
                </TableBodyRowItem>
                <TableBodyRowItem :position="'last'">
                    <Dropdown v-if="canEdit || canDelete" :align="'right'">
                        <button type="submit" class="template-toolbar-btn">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M12 16a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2a2 2 0 0 1 2-2m0-6a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2a2 2 0 0 1 2-2m0-6a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2a2 2 0 0 1 2-2Z"/>
                            </svg>
                        </button>
                        <template #items>
                            <DropdownItemGroup>
                                <DropdownItem v-if="canEdit && (item.status == 'APPROVED' || item.status == 'REJECTED' || item.status == 'PAUSED')" :href="'/templates/' + item.uuid">{{ $t('edit') }}</DropdownItem>
                                <DropdownItem v-if="canDelete" as="button" @click="openAlert(item.uuid)">{{ $t('Delete') }}</DropdownItem>
                            </DropdownItemGroup>
                        </template>
                    </Dropdown>
                </TableBodyRowItem>
            </TableBodyRow>
        </TableBody>
    </Table>

    <UiEmptyState v-if="rows.data.length == 0" :title="$t('You don\'t have any templates')">
        <template #icon>
            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 32 32"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M12 15h8m-8 4h8m8 5V11c0-1.105-.892-2-1.997-2H17c-2 0-2-3-5-3H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h20a2 2 0 0 0 2-2Z"></path></svg>
        </template>
        <template #actions>
            <Link v-if="canAdd" href="/templates/create" class="template-empty-cta">{{ $t('Create template') }}</Link>
            <button type="button" v-else disabled class="template-empty-cta template-empty-cta--disabled">{{ $t('Create template') }}</button>
        </template>
    </UiEmptyState>

    <!-- Alert Modal Component-->
    <AlertModal
        v-model="isOpenAlert"
        @confirm="() => confirmAlert(deleteAction)"
        :label = "$t('Delete row')"
        :description = "$t('Are you sure you want to delete this row? This action can not be undone')"
    />
</template>

<style scoped>
.template-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.template-filter-group {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}

.template-filter-pill {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.4rem 0.9rem;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--ui-muted);
    background: var(--ui-surface-soft);
    border: 1px solid var(--ui-border);
    transition: background-color 160ms ease, color 160ms ease, border-color 160ms ease;
}

.template-filter-pill:hover {
    color: var(--ui-text);
    background: var(--ui-border);
}

.template-filter-pill--active {
    color: #fff;
    background: var(--ui-secondary);
    border-color: var(--ui-secondary);
}

.template-category-badge {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.25rem 0.7rem;
    font-size: 0.75rem;
    font-weight: 700;
}

.template-preview-frame {
    max-width: 260px;
    max-height: 190px;
    overflow: hidden;
}

.template-preview-frame :deep(p) {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.template-toolbar-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.1rem;
    height: 2.1rem;
    border-radius: 999px;
    color: var(--ui-muted);
    background: transparent;
    cursor: pointer;
    transition: background-color 160ms ease, color 160ms ease;
}

.template-toolbar-btn:hover {
    background: var(--ui-surface-soft);
    color: var(--ui-text);
}

.template-empty-cta {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.7rem;
    padding: 0.6rem 1.2rem;
    font-size: 0.85rem;
    font-weight: 600;
    color: #fff;
    background: var(--ui-secondary);
    transition: filter 160ms ease;
}

.template-empty-cta:hover {
    filter: brightness(1.05);
}

.template-empty-cta--disabled {
    background: var(--ui-border-strong);
    color: var(--ui-muted);
    cursor: not-allowed;
}
</style>
