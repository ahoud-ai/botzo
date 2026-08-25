<script setup>
    import { Link, router, useForm, usePage } from "@inertiajs/vue3";
    import { ref, computed } from 'vue';
    import { useI18n } from 'vue-i18n';
    import { buildI18nLookupCandidates } from '@/Utils/i18nLookup';
    import debounce from 'lodash/debounce';
    import AlertModal from '@/Components/AlertModal.vue';
    import { useAlertModal } from '@/Composables/useAlertModal';
    import Pagination from '@/Components/Pagination.vue';
    import Dropdown from '@/Components/Dropdown.vue';
    import DropdownItemGroup from '@/Components/DropdownItemGroup.vue';
    import DropdownItem from '@/Components/DropdownItem.vue';
    import UiEmptyState from '@/Components/UI/UiEmptyState.vue';

    const props = defineProps({
        rows: {
            type: Object,
            required: true,
        },
        filters: {
            type: Object,
            default: () => ({}),
        }
    });

    const { isOpenAlert, openAlert, confirmAlert } = useAlertModal();
    const { t, te } = useI18n();
    const page = usePage();

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
        search: props.filters?.search ?? null,
        status: props.filters?.status ?? 'all',
    });

    const statusOptions = computed(() => ([
        { value: 'all', label: t('All statuses') },
        { value: 'ongoing', label: t('ongoing') },
        { value: 'scheduled', label: t('scheduled') },
        { value: 'completed', label: t('completed') },
    ]));

    const isSearching = ref(false);

    const runSearch = () => {
        const filteredParams = Object.fromEntries(
            Object.entries(params.value).filter(([, value]) => value !== null && value !== 'all' && value !== '')
        );

        router.visit('/campaigns', {
            method: 'get',
            data: filteredParams,
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                isSearching.value = false;
            },
        });
    }

    const clearSearch = () => {
        params.value.search = null;
        runSearch();
    }

    const search = debounce(() => {
        isSearching.value = true;
        runSearch();
    }, 600);

    const applyStatus = (value) => {
        params.value.status = value;
        runSearch();
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

    // Status identity: color + icon are driven entirely by the app's real
    // semantic tokens (info/warning/success), matching the chip colors
    // already used across templates/tickets - not decorative placeholders.
    const statusColorVar = (status) => {
        switch (status) {
            case 'ongoing':
                return '#155dfc';
            case 'scheduled':
                return 'var(--ui-warning)';
            case 'completed':
                return 'var(--ui-success)';
            default:
                return 'var(--ui-muted)';
        }
    };

    const statusChipClass = (status) => {
        switch (status) {
            case 'completed':
                return 'ui-chip-success';
            case 'ongoing':
                return 'ui-chip-info';
            case 'scheduled':
                return 'ui-chip-warning';
            default:
                return 'ui-chip-neutral';
        }
    };

    // Same category palette already used on the Templates page.
    const categoryStyles = {
        MARKETING: { color: '#e11d48', background: 'rgba(225, 29, 72, 0.12)' },
        UTILITY: { color: '#155dfc', background: 'rgba(21, 93, 252, 0.12)' },
        AUTHENTICATION: { color: '#7c3aed', background: 'rgba(124, 58, 237, 0.12)' },
    };
    const categoryColor = (category) => categoryStyles[String(category ?? '').toUpperCase()]?.color ?? 'var(--ui-muted)';

    const effectiveDenominator = (contactCount, contactGroupCount) => (contactCount > 0 ? contactCount : contactGroupCount);

    const percentValue = (numerator, contactCount, contactGroupCount) => {
        const denom = effectiveDenominator(contactCount, contactGroupCount);
        if (!denom) {
            return 0;
        }
        return Math.max(0, Math.min(100, Math.round((numerator / denom) * 100)));
    };

    const fracLabel = (numerator, contactCount, contactGroupCount) => {
        const denom = effectiveDenominator(contactCount, contactGroupCount);
        return numerator + '/' + denom;
    };

    const formatDateTime = (value) => {
        if (!value) {
            return '-';
        }

        const options = { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' };
        const locale = page.props?.currentLanguage === 'ar' ? 'ar-EG' : 'en-US';
        return new Intl.DateTimeFormat(locale, options).format(new Date(value));
    };

    const recipientsTarget = (item) => effectiveDenominator(item.contacts_count, item.contact_group_count);

    const campaignUrl = (uuid) => '/campaigns/' + uuid;
</script>
<template>
    <div class="campaign-toolbar">
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

        <div class="campaign-filter-group">
            <button
                v-for="option in statusOptions"
                :key="option.value"
                type="button"
                class="campaign-filter-pill"
                :class="{ 'campaign-filter-pill--active': params.status === option.value }"
                @click="applyStatus(option.value)"
            >
                {{ option.label }}
            </button>
        </div>
    </div>

    <div class="campaign-list">
        <div
            v-for="(item, index) in rows.data"
            :key="index"
            class="campaign-card"
            :style="{ '--status-color': statusColorVar(item.status) }"
        >
            <Link
                v-if="canView || canViewAll"
                :href="campaignUrl(item.uuid)"
                class="campaign-card-link"
                :aria-label="item.name"
            ></Link>

            <span class="status-chip-icon">
                <span v-if="item.status === 'ongoing'" class="pulse-ring"></span>
                <svg v-if="item.status === 'ongoing'" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="m13 2-10 12h7l-1 8 10-12h-7l1-8Z"/></svg>
                <svg v-else-if="item.status === 'scheduled'" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.8"/><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 3"/></svg>
                <svg v-else xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
            </span>

            <div class="campaign-main">
                <p class="campaign-name">{{ item.name }}</p>
                <span v-if="item.template" class="template-chip">
                    <span class="dot" :style="{ '--tpl-color': categoryColor(item.template.category) }"></span>
                    {{ item.template.name }}
                </span>
            </div>

            <div v-if="item.status !== 'scheduled'" class="metrics">
                <div class="recipients-block">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm12 10v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <b>{{ recipientsTarget(item) }}</b> {{ $t('Recipients') }}
                </div>
                <div class="metric">
                    <div class="metric-label">{{ $t('Delivery rate') }}</div>
                    <div class="metric-value-row">
                        <span class="metric-pct">{{ percentValue(item.delivery_count, item.contacts_count, item.contact_group_count) }}%</span>
                        <span class="metric-frac" dir="ltr">{{ fracLabel(item.delivery_count, item.contacts_count, item.contact_group_count) }}</span>
                    </div>
                    <div class="meter"><div class="meter-fill meter-fill--delivery" :style="{ width: percentValue(item.delivery_count, item.contacts_count, item.contact_group_count) + '%' }"></div></div>
                </div>
                <div class="metric">
                    <div class="metric-label">{{ $t('Read rate') }}</div>
                    <div class="metric-value-row">
                        <span class="metric-pct">{{ percentValue(item.read_count, item.contacts_count, item.contact_group_count) }}%</span>
                        <span class="metric-frac" dir="ltr">{{ fracLabel(item.read_count, item.contacts_count, item.contact_group_count) }}</span>
                    </div>
                    <div class="meter"><div class="meter-fill meter-fill--read" :style="{ width: percentValue(item.read_count, item.contacts_count, item.contact_group_count) + '%' }"></div></div>
                </div>
            </div>
            <div v-else class="schedule-note">
                <span><b>{{ recipientsTarget(item) }}</b> {{ $t('Recipients') }}</span>
                <span class="when">{{ $t('Scheduled for') }} {{ formatDateTime(item.scheduled_at) }}</span>
            </div>

            <div class="status-tail">
                <span class="status-badge" :class="statusChipClass(item.status)">
                    <span class="dot"></span>
                    {{ translateDynamic(item.status) }}
                </span>
                <span class="updated-at">{{ $t('Created') }}: {{ formatDateTime(item.created_at) }}</span>
            </div>

            <Dropdown v-if="canView || canDelete" :align="'right'" class="campaign-kebab-wrap">
                <button type="button" class="campaign-kebab">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M12 16a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2a2 2 0 0 1 2-2m0-6a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2a2 2 0 0 1 2-2m0-6a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2a2 2 0 0 1 2-2Z"/>
                    </svg>
                </button>
                <template #items>
                    <DropdownItemGroup>
                        <DropdownItem v-if="canView" :href="campaignUrl(item.uuid)">{{ $t('View') }}</DropdownItem>
                        <DropdownItem v-if="canDelete" as="button" @click="openAlert(item.uuid)">{{ $t('Delete') }}</DropdownItem>
                    </DropdownItemGroup>
                </template>
            </Dropdown>
        </div>
    </div>

    <Pagination v-if="rows.meta" class="mt-4" :pagination="rows.meta"/>

    <UiEmptyState v-if="rows.data.length === 0" :title="$t('No campaigns found')" :description="$t('You don\'t have any campaigns')">
        <template #icon>
            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 256 256"><path fill="none" stroke="currentColor" stroke-width="10" d="M216 80h-32V48a16 16 0 0 0-16-16H40a16 16 0 0 0-16 16v128a8 8 0 0 0 13 6.22L72 154v30a16 16 0 0 0 16 16h93.59L219 230.22a8 8 0 0 0 5 1.78a8 8 0 0 0 8-8V96a16 16 0 0 0-16-16"/></svg>
        </template>
        <template #actions>
            <Link v-if="canAdd" href="/campaigns/create" class="campaign-empty-cta">{{ $t('Create campaign') }}</Link>
            <button type="button" v-else disabled class="campaign-empty-cta campaign-empty-cta--disabled">{{ $t('Create campaign') }}</button>
        </template>
    </UiEmptyState>

    <!-- Alert Modal Component-->
    <AlertModal
        v-model="isOpenAlert"
        @confirm="() => confirmAlert(deleteAction)"
        :label = "$t('Delete row')"
        :description = "$t('Are you sure you want to delete this campaign? This action will only delete the campaign; sent messages will not be deleted from the chat history.')"
    />
</template>

<style scoped>
.campaign-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1.25rem;
}

.campaign-filter-group {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}

.campaign-filter-pill {
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

.campaign-filter-pill:hover {
    color: var(--ui-text);
    background: var(--ui-border);
}

.campaign-filter-pill--active {
    color: #fff;
    background: var(--ui-secondary);
    border-color: var(--ui-secondary);
    box-shadow: 0 8px 18px -10px color-mix(in srgb, var(--ui-secondary) 65%, transparent);
}

.campaign-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.campaign-card {
    --status-color: var(--ui-muted);
    position: relative;
    display: grid;
    grid-template-columns: auto minmax(0, 1.5fr) minmax(0, 2fr) auto auto;
    align-items: center;
    gap: 1.25rem;
    background: var(--ui-surface);
    border: 1px solid var(--ui-border);
    border-radius: 1.1rem;
    padding: 1.1rem 1.3rem;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    transition: box-shadow 220ms cubic-bezier(.2,.8,.2,1), border-color 220ms ease;
}

.campaign-card-link {
    position: absolute;
    inset: 0;
    z-index: 0;
    border-radius: inherit;
}

.campaign-card:hover {
    box-shadow: 0 16px 32px -14px rgba(15, 23, 42, 0.3);
    border-color: color-mix(in srgb, var(--status-color) 45%, var(--ui-border));
}

:global(.dark) .campaign-card:hover {
    box-shadow: 0 20px 44px -18px rgba(0, 0, 0, 0.6);
}

.status-chip-icon {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.75rem;
    height: 2.75rem;
    border-radius: 0.9rem;
    background: color-mix(in srgb, var(--status-color) 15%, transparent);
    color: var(--status-color);
    flex-shrink: 0;
}

.pulse-ring {
    position: absolute;
    inset: -3px;
    border-radius: inherit;
    border: 2px solid var(--status-color);
    opacity: 0;
    animation: campaign-pulse 2.2s ease-out infinite;
}

@media (prefers-reduced-motion: reduce) {
    .pulse-ring { animation: none; }
}

@keyframes campaign-pulse {
    0% { opacity: 0.55; transform: scale(1); }
    100% { opacity: 0; transform: scale(1.35); }
}

.campaign-main { min-width: 0; }

.campaign-name {
    font-size: 1rem;
    font-weight: 700;
    margin: 0 0 0.35rem;
    color: var(--ui-text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.template-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.75rem;
    color: var(--ui-muted);
    font-weight: 600;
    background: var(--ui-surface-soft);
    border: 1px solid var(--ui-border);
    border-radius: 999px;
    padding: 0.2rem 0.65rem 0.2rem 0.55rem;
    max-width: 100%;
}

.template-chip .dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--tpl-color, var(--ui-muted));
    flex-shrink: 0;
}

.metrics {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    flex-wrap: wrap;
}

.metric { min-width: 100px; }

.metric-label {
    font-size: 0.7rem;
    color: var(--ui-muted);
    font-weight: 700;
    margin-bottom: 0.3rem;
}

.metric-value-row {
    display: flex;
    align-items: baseline;
    gap: 0.35rem;
    margin-bottom: 0.35rem;
}

.metric-pct {
    font-size: 1rem;
    font-weight: 800;
    color: var(--ui-text);
    font-variant-numeric: tabular-nums;
}

.metric-frac {
    font-size: 0.7rem;
    color: var(--ui-muted);
    font-variant-numeric: tabular-nums;
    unicode-bidi: isolate;
}

.meter {
    height: 5px;
    border-radius: 999px;
    background: var(--ui-surface-soft);
    overflow: hidden;
    width: 100px;
}

.meter-fill {
    height: 100%;
    border-radius: 999px;
    transition: width 500ms cubic-bezier(.2,.8,.2,1);
}

.meter-fill--delivery { background: #155dfc; }
.meter-fill--read { background: var(--ui-secondary); }

.recipients-block {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    color: var(--ui-muted);
    font-size: 0.8rem;
    font-weight: 700;
}

.recipients-block b {
    color: var(--ui-text);
    font-variant-numeric: tabular-nums;
    font-weight: 800;
}

.schedule-note {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    font-size: 0.8rem;
    color: var(--ui-muted);
    font-weight: 600;
}

.schedule-note b { color: var(--ui-text); font-variant-numeric: tabular-nums; }
.schedule-note .when { color: var(--ui-warning); font-weight: 700; }

.status-tail {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.45rem;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    border-radius: 999px;
    padding: 0.32rem 0.75rem;
    font-size: 0.75rem;
    font-weight: 800;
    white-space: nowrap;
    text-transform: capitalize;
}

.status-badge .dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
}

.updated-at {
    font-size: 0.72rem;
    color: var(--ui-muted);
    white-space: nowrap;
}

.campaign-kebab-wrap {
    position: relative;
    z-index: 1;
    flex-shrink: 0;
}

.campaign-kebab {
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

.campaign-kebab:hover {
    background: var(--ui-surface-soft);
    color: var(--ui-text);
}

.campaign-empty-cta {
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

.campaign-empty-cta:hover { filter: brightness(1.05); }

.campaign-empty-cta--disabled {
    background: var(--ui-border-strong);
    color: var(--ui-muted);
    cursor: not-allowed;
}

@media (max-width: 980px) {
    .campaign-card {
        grid-template-columns: auto 1fr auto;
        grid-template-areas:
            "icon main kebab"
            "icon metrics kebab"
            "icon tail kebab";
        row-gap: 0.85rem;
    }
    .status-chip-icon { grid-area: icon; }
    .campaign-main { grid-area: main; }
    .metrics, .schedule-note { grid-area: metrics; }
    .status-tail { grid-area: tail; flex-direction: row; align-items: center; justify-content: space-between; width: 100%; }
    .campaign-kebab-wrap { grid-area: kebab; align-self: start; }
}
</style>
