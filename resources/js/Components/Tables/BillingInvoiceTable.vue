<script setup>
import { Link } from '@inertiajs/vue3';
import Pagination from '@/Components/Pagination.vue';
import UiEmptyState from '@/Components/UI/UiEmptyState.vue';

const props = defineProps({
    rows: {
        type: Object,
        required: true,
    },
    viewBasePath: {
        type: String,
        required: true,
    },
    printBasePath: {
        type: String,
        required: true,
    },
    downloadBasePath: {
        type: String,
        default: null,
    },
    showOrganization: {
        type: Boolean,
        default: false,
    },
});

const invoiceHref = (uuid) => `${props.viewBasePath}/${uuid}`;
const printHref = (uuid) => `${props.printBasePath}/${uuid}/print`;
const downloadHref = (uuid) => props.downloadBasePath ? `${props.downloadBasePath}/${uuid}/download` : null;
const viewButtonClass = 'billing-table-btn billing-table-btn--ghost';
const printButtonClass = 'billing-table-btn billing-table-btn--solid';
const downloadButtonClass = 'billing-table-btn billing-table-btn--tint';

const statusClass = (label) => {
    const normalized = String(label ?? '').toLowerCase();

    if (normalized.includes('paid') || normalized.includes('مغطاة') || normalized.includes('settled') || normalized.includes('مسددة')) {
        return 'ui-chip-success';
    }

    if (normalized.includes('pending') || normalized.includes('معلقة')) {
        return 'ui-chip-warning';
    }

    return 'ui-chip-neutral';
};
</script>

<template>
    <div class="space-y-4">
        <template v-if="rows?.data?.length">
            <div class="ui-table-shell">
                <div class="hidden overflow-x-auto xl:block">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th class="ui-table-th ui-table-th-first">{{ $t('Invoice no.') }}</th>
                                <th class="ui-table-th">{{ $t('Issued at') }}</th>
                                <th v-if="showOrganization" class="ui-table-th">{{ $t('Organization') }}</th>
                                <th class="ui-table-th">{{ $t('Plan') }}</th>
                                <th class="ui-table-th">{{ $t('Total') }}</th>
                                <th class="ui-table-th">{{ $t('Status') }}</th>
                                <th class="ui-table-th">{{ $t('Payment method') }}</th>
                                <th class="ui-table-th ui-table-th-last">{{ $t('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in rows.data" :key="item.uuid">
                                <td class="ui-table-td ui-table-td-first font-bold text-[15px] text-[var(--ui-text)]">{{ item.invoice_number }}</td>
                                <td class="ui-table-td text-[var(--ui-muted)]">{{ item.issued_at }}</td>
                                <td v-if="showOrganization" class="ui-table-td text-[var(--ui-text)]">{{ item.organization_name ?? '—' }}</td>
                                <td class="ui-table-td text-[var(--ui-text)]">{{ item.plan_name ?? '—' }}</td>
                                <td class="ui-table-td font-bold text-[var(--ui-text)]">{{ item.total }}</td>
                                <td class="ui-table-td">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-bold" :class="statusClass(item.status_label)">
                                        {{ item.status_label }}
                                    </span>
                                </td>
                                <td class="ui-table-td text-[var(--ui-text)]">{{ item.payment_method_label }}</td>
                                <td class="ui-table-td ui-table-td-last">
                                    <div class="flex flex-wrap gap-2">
                                        <Link :href="invoiceHref(item.uuid)" :class="viewButtonClass">
                                            {{ $t('View') }}
                                        </Link>
                                        <a :href="printHref(item.uuid)" target="_blank" rel="noopener noreferrer" :class="printButtonClass">
                                            {{ $t('Print') }}
                                        </a>
                                        <a v-if="downloadHref(item.uuid)" :href="downloadHref(item.uuid)" download :class="downloadButtonClass">
                                            {{ $t('Download PDF') }}
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="billing-mobile-list xl:hidden">
                    <article
                        v-for="item in rows.data"
                        :key="`${item.uuid}-mobile`"
                        class="billing-mobile-card"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-bold text-[15px] text-[var(--ui-text)]">{{ item.invoice_number }}</p>
                                <p class="mt-1 text-xs text-[var(--ui-muted)]">{{ item.issued_at }}</p>
                            </div>
                            <span class="rounded-full px-3 py-1 text-xs font-bold" :class="statusClass(item.status_label)">
                                {{ item.status_label }}
                            </span>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2 mt-4">
                            <div class="billing-mobile-field">
                                <p class="billing-mobile-field-label">{{ $t('Plan') }}</p>
                                <p class="billing-mobile-field-value">{{ item.plan_name ?? '—' }}</p>
                            </div>
                            <div class="billing-mobile-field">
                                <p class="billing-mobile-field-label">{{ $t('Total') }}</p>
                                <p class="billing-mobile-field-value">{{ item.total }}</p>
                            </div>
                            <div v-if="showOrganization" class="billing-mobile-field">
                                <p class="billing-mobile-field-label">{{ $t('Organization') }}</p>
                                <p class="billing-mobile-field-value">{{ item.organization_name ?? '—' }}</p>
                            </div>
                            <div class="billing-mobile-field">
                                <p class="billing-mobile-field-label">{{ $t('Payment method') }}</p>
                                <p class="billing-mobile-field-value">{{ item.payment_method_label }}</p>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2 mt-4">
                            <Link :href="invoiceHref(item.uuid)" :class="viewButtonClass">
                                {{ $t('View') }}
                            </Link>
                            <a :href="printHref(item.uuid)" target="_blank" rel="noopener noreferrer" :class="printButtonClass">
                                {{ $t('Print') }}
                            </a>
                            <a v-if="downloadHref(item.uuid)" :href="downloadHref(item.uuid)" download :class="downloadButtonClass">
                                {{ $t('Download PDF') }}
                            </a>
                        </div>
                    </article>
                </div>
            </div>
        </template>

        <UiEmptyState
            v-else
            :title="$t('No invoices yet')"
            :description="$t('Invoice documents will appear here once subscriptions are billed or manual billing entries are created.')"
        >
            <template #icon>
                <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24"><path fill="currentColor" d="M7 3h10a2 2 0 0 1 2 2v14l-2-1l-2 1l-2-1l-2 1l-2-1l-2 1V5a2 2 0 0 1 2-2m0 2v10.76l.4-.2l1.6-.8l2 1l2-1l2 1l1.6-.8l.4.2V5zm2 2h6v2H9zm0 4h6v2H9z"/></svg>
            </template>
        </UiEmptyState>

        <Pagination v-if="rows && rows.meta" :pagination="rows.meta" />
    </div>
</template>

<style scoped>
.billing-mobile-card {
    padding: 1.1rem;
    border-top: 1px solid var(--ui-border);
}

.billing-mobile-card:first-child {
    border-top: none;
}

.billing-mobile-field {
    border-radius: var(--ui-radius-sm);
    background: var(--ui-surface-soft);
    padding: 0.6rem 0.75rem;
}

.billing-mobile-field-label {
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: var(--ui-muted);
}

.billing-mobile-field-value {
    margin-top: 0.25rem;
    font-size: 0.88rem;
    font-weight: 600;
    color: var(--ui-text);
}

.billing-table-btn {
    display: inline-flex;
    min-height: 2.35rem;
    align-items: center;
    justify-content: center;
    border-radius: 0.6rem;
    padding: 0.5rem 0.9rem;
    font-size: 0.83rem;
    font-weight: 600;
    transition: background-color 160ms ease, filter 160ms ease;
}

.billing-table-btn--ghost {
    color: var(--ui-text);
    background: var(--ui-surface-soft);
    border: 1px solid var(--ui-border);
}

.billing-table-btn--ghost:hover {
    background: var(--ui-border);
}

.billing-table-btn--solid {
    color: #fff;
    background: var(--ui-secondary);
}

.billing-table-btn--solid:hover {
    filter: brightness(1.05);
}

.billing-table-btn--tint {
    color: var(--ui-secondary);
    background: color-mix(in srgb, var(--ui-secondary) 12%, var(--ui-surface));
    border: 1px solid color-mix(in srgb, var(--ui-secondary) 28%, var(--ui-border));
}

.billing-table-btn--tint:hover {
    background: color-mix(in srgb, var(--ui-secondary) 18%, var(--ui-surface));
}
</style>
