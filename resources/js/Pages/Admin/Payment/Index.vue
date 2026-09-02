<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame ui-text-main min-h-full space-y-6">
            <UiPageHeader :title="$t('Billing records')" :subtitle="$t('Use simple filters to review invoice documents or accounting activity without duplicated summaries.')">
                <template #actions>
                    <div class="pay-view-toggle">
                        <button
                            type="button"
                            class="pay-view-toggle-btn"
                            :class="{ 'pay-view-toggle-btn--active': props.activeView === 'invoices' }"
                            @click="switchView('invoices')"
                        >
                            {{ $t('Invoices') }}
                        </button>
                        <button
                            type="button"
                            class="pay-view-toggle-btn"
                            :class="{ 'pay-view-toggle-btn--active': props.activeView === 'activity' }"
                            @click="switchView('activity')"
                        >
                            {{ $t('Activity') }}
                        </button>
                    </div>
                </template>
            </UiPageHeader>

            <form class="pay-toolbar" @submit.prevent="applyFilters()">
                <div class="grid gap-4 xl:grid-cols-[minmax(0,1.35fr)_minmax(220px,0.85fr)_170px_170px_auto]">
                    <label class="space-y-2">
                        <span class="pay-field-label">{{ $t('Search') }}</span>
                        <input
                            v-model="form.search"
                            type="text"
                            class="pay-field"
                            :placeholder="props.activeView === 'invoices' ? $t('Search invoices by number, workspace, or plan') : $t('Search billing activity by workspace or note')"
                        >
                    </label>

                    <label class="space-y-2">
                        <span class="pay-field-label">{{ $t('Organization') }}</span>
                        <select v-model="form.organization_uuid" class="pay-field">
                            <option value="">{{ $t('All organizations') }}</option>
                            <option v-for="option in props.organizationOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                    </label>

                    <label class="space-y-2">
                        <span class="pay-field-label">{{ $t('Date from') }}</span>
                        <input v-model="form.date_from" type="date" class="pay-field">
                    </label>

                    <label class="space-y-2">
                        <span class="pay-field-label">{{ $t('Date to') }}</span>
                        <input v-model="form.date_to" type="date" class="pay-field">
                    </label>

                    <div class="flex flex-wrap items-end gap-2 xl:justify-end">
                        <button type="submit" class="pay-btn pay-btn--solid">
                            {{ $t('Apply filters') }}
                        </button>
                        <button type="button" class="pay-btn pay-btn--ghost" @click="resetFilters">
                            {{ $t('Reset') }}
                        </button>
                    </div>
                </div>
            </form>

            <UiSectionCard
                :title="props.activeView === 'invoices' ? $t('Invoice register') : $t('Billing activity')"
                :subtitle="props.activeView === 'invoices'
                    ? $t('Open invoice details, print the official document, or download the PDF from a compact accounting register.')
                    : $t('Review manual credits, debits, and payment records in a simple activity table.')"
            >
                <BillingInvoiceTable
                    v-if="props.activeView === 'invoices'"
                    :rows="props.invoiceRows"
                    :view-base-path="'/admin/payment-logs/invoices'"
                    :print-base-path="'/admin/payment-logs/invoices'"
                    :download-base-path="'/admin/payment-logs/invoices'"
                    :show-organization="true"
                />
                <BillingTable
                    v-else
                    :rows="props.billingActivity"
                    :filters="props.filters"
                />
            </UiSectionCard>
        </div>
    </AppLayout>
</template>

<script setup>
import { reactive, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from './../Layout/App.vue';
import BillingInvoiceTable from '@/Components/Tables/BillingInvoiceTable.vue';
import BillingTable from '@/Components/Tables/BillingTable.vue';
import UiPageHeader from '@/Components/UI/UiPageHeader.vue';
import UiSectionCard from '@/Components/UI/UiSectionCard.vue';

const props = defineProps({
    title: String,
    activeView: {
        type: String,
        default: 'invoices',
    },
    invoiceRows: {
        type: Object,
        default: null,
    },
    billingActivity: {
        type: Object,
        default: null,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    organizationOptions: {
        type: Array,
        default: () => [],
    },
});

const form = reactive({
    view: props.activeView ?? 'invoices',
    search: props.filters?.search ?? '',
    organization_uuid: props.filters?.organization_uuid ?? '',
    date_from: props.filters?.date_from ?? '',
    date_to: props.filters?.date_to ?? '',
});

watch(() => props.filters, (filters) => {
    form.view = props.activeView ?? 'invoices';
    form.search = filters?.search ?? '';
    form.organization_uuid = filters?.organization_uuid ?? '';
    form.date_from = filters?.date_from ?? '';
    form.date_to = filters?.date_to ?? '';
}, { deep: true });

const buildQuery = (view = form.view) => {
    const query = {
        view,
    };

    if (form.search) {
        query.search = form.search;
    }

    if (form.organization_uuid) {
        query.organization_uuid = form.organization_uuid;
    }

    if (form.date_from) {
        query.date_from = form.date_from;
    }

    if (form.date_to) {
        query.date_to = form.date_to;
    }

    return query;
};

const visit = (query) => {
    router.get(window.location.pathname, query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const applyFilters = (view = form.view) => {
    form.view = view;
    visit(buildQuery(view));
};

const switchView = (view) => {
    applyFilters(view);
};

const resetFilters = () => {
    form.search = '';
    form.organization_uuid = '';
    form.date_from = '';
    form.date_to = '';
    applyFilters(form.view);
};
</script>

<style scoped>
.pay-view-toggle {
    display: inline-flex;
    gap: 0.25rem;
    padding: 0.25rem;
    border-radius: 0.9rem;
    background: var(--ui-surface-soft);
    border: 1px solid var(--ui-border);
}

.pay-view-toggle-btn {
    border-radius: 0.65rem;
    padding: 0.5rem 1rem;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--ui-muted);
    transition: background-color 160ms ease, color 160ms ease;
}

.pay-view-toggle-btn:hover {
    color: var(--ui-text);
}

.pay-view-toggle-btn--active {
    background: var(--ui-surface);
    color: var(--ui-text);
    box-shadow: var(--ui-shadow-1);
}

.pay-toolbar {
    padding: 1.1rem 1.25rem;
    border: 1px solid var(--ui-border);
    border-radius: var(--ui-radius-xl, 1.25rem);
    background: linear-gradient(160deg, color-mix(in srgb, var(--ui-secondary) 5%, var(--ui-surface)), var(--ui-surface));
    box-shadow: var(--ui-shadow-1);
}

.pay-field-label {
    display: block;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--ui-muted);
}

.pay-field {
    height: 2.75rem;
    width: 100%;
    border-radius: 0.8rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    padding-inline: 1rem;
    font-size: 0.85rem;
    color: var(--ui-text);
    outline: none;
    transition: border-color 160ms ease, box-shadow 160ms ease;
}

.pay-field::placeholder {
    color: color-mix(in srgb, var(--ui-text) 48%, transparent);
}

.pay-field:focus {
    border-color: color-mix(in srgb, var(--ui-secondary) 40%, var(--ui-border));
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--ui-secondary) 12%, transparent);
}

.pay-btn {
    display: inline-flex;
    min-height: 2.75rem;
    align-items: center;
    justify-content: center;
    border-radius: 0.8rem;
    padding: 0.5rem 1.1rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: background-color 160ms ease, border-color 160ms ease, filter 160ms ease;
}

.pay-btn--solid {
    border: 1px solid transparent;
    background: var(--ui-secondary);
    color: #fff;
    box-shadow: var(--ui-shadow-1);
}

.pay-btn--solid:hover {
    filter: brightness(1.05);
}

.pay-btn--ghost {
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    color: var(--ui-text);
}

.pay-btn--ghost:hover {
    background: var(--ui-surface-soft);
    border-color: var(--ui-border-strong);
}
</style>
