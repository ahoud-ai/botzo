<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame ui-text-main min-h-full space-y-6">
            <section class="rounded-[1.35rem] border border-slate-200 bg-white p-6 shadow-sm shadow-slate-200/60 md:p-8">
                <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                    <div class="max-w-3xl">
                        <div class="inline-flex rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">
                            {{ props.activeView === 'invoices' ? $t('Invoice register') : $t('Billing activity') }}
                        </div>
                        <h1 class="mt-4 text-2xl font-semibold text-slate-950 md:text-3xl">{{ $t('Billing records') }}</h1>
                        <p class="mt-3 text-sm leading-7 text-slate-600">
                            {{ $t('Use simple filters to review invoice documents or accounting activity without duplicated summaries.') }}
                        </p>
                    </div>

                    <div class="inline-flex rounded-lg border border-slate-200 bg-slate-50 p-1 shadow-sm shadow-slate-200/40">
                        <button
                            type="button"
                            class="rounded-md px-4 py-2 text-sm font-medium transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                            :class="props.activeView === 'invoices' ? 'bg-primary text-white shadow-sm shadow-primary/25' : 'text-slate-600 hover:bg-white'"
                            @click="switchView('invoices')"
                        >
                            {{ $t('Invoices') }}
                        </button>
                        <button
                            type="button"
                            class="rounded-md px-4 py-2 text-sm font-medium transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                            :class="props.activeView === 'activity' ? 'bg-primary text-white shadow-sm shadow-primary/25' : 'text-slate-600 hover:bg-white'"
                            @click="switchView('activity')"
                        >
                            {{ $t('Activity') }}
                        </button>
                    </div>
                </div>

                <form class="mt-6 rounded-[1.15rem] border border-slate-200 bg-slate-50 p-4" @submit.prevent="applyFilters()">
                    <div class="grid gap-4 xl:grid-cols-[minmax(0,1.35fr)_minmax(220px,0.85fr)_170px_170px_auto]">
                        <label class="space-y-2">
                            <span class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ $t('Search') }}</span>
                            <input
                                v-model="form.search"
                                type="text"
                                class="h-11 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm text-slate-700 shadow-sm shadow-slate-200/40 transition placeholder:text-slate-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/15"
                                :placeholder="props.activeView === 'invoices' ? $t('Search invoices by number, workspace, or plan') : $t('Search billing activity by workspace or note')"
                            >
                        </label>

                        <label class="space-y-2">
                            <span class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ $t('Organization') }}</span>
                            <select
                                v-model="form.organization_uuid"
                                class="h-11 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm text-slate-700 shadow-sm shadow-slate-200/40 transition focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/15"
                            >
                                <option value="">{{ $t('All organizations') }}</option>
                                <option v-for="option in props.organizationOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                        </label>

                        <label class="space-y-2">
                            <span class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ $t('Date from') }}</span>
                            <input
                                v-model="form.date_from"
                                type="date"
                                class="h-11 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm text-slate-700 shadow-sm shadow-slate-200/40 transition focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/15"
                            >
                        </label>

                        <label class="space-y-2">
                            <span class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ $t('Date to') }}</span>
                            <input
                                v-model="form.date_to"
                                type="date"
                                class="h-11 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm text-slate-700 shadow-sm shadow-slate-200/40 transition focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/15"
                            >
                        </label>

                        <div class="flex flex-wrap items-end gap-2 xl:justify-end">
                            <button
                                type="submit"
                                class="inline-flex min-h-10 items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                            >
                                {{ $t('Apply filters') }}
                            </button>
                            <button
                                type="button"
                                class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-300"
                                @click="resetFilters"
                            >
                                {{ $t('Reset') }}
                            </button>
                        </div>
                    </div>
                </form>
            </section>

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
