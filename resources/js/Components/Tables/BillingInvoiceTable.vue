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
const viewButtonClass = 'inline-flex min-h-10 items-center justify-center rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-300';
const printButtonClass = 'inline-flex min-h-10 items-center justify-center rounded-md bg-primary px-3 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600';
const downloadButtonClass = 'inline-flex min-h-10 items-center justify-center rounded-md border border-primary/20 bg-primary/5 px-3 py-2 text-sm font-medium text-primary shadow-sm transition hover:bg-primary/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600';

const statusClass = (label) => {
    const normalized = String(label ?? '').toLowerCase();

    if (normalized.includes('paid') || normalized.includes('مغطاة') || normalized.includes('settled') || normalized.includes('مسددة')) {
        return 'bg-emerald-50 text-emerald-700 ring-emerald-200';
    }

    if (normalized.includes('pending') || normalized.includes('معلقة')) {
        return 'bg-amber-50 text-amber-700 ring-amber-200';
    }

    return 'bg-slate-100 text-slate-700 ring-slate-200';
};
</script>

<template>
    <div class="space-y-4">
        <template v-if="rows?.data?.length">
            <div class="overflow-hidden rounded-[1.25rem] border border-slate-200 bg-white shadow-sm shadow-slate-200/60">
                <div class="hidden overflow-x-auto xl:block">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-5 py-4 text-start text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ $t('Invoice no.') }}</th>
                                <th class="px-5 py-4 text-start text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ $t('Issued at') }}</th>
                                <th v-if="showOrganization" class="px-5 py-4 text-start text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ $t('Organization') }}</th>
                                <th class="px-5 py-4 text-start text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ $t('Plan') }}</th>
                                <th class="px-5 py-4 text-start text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ $t('Total') }}</th>
                                <th class="px-5 py-4 text-start text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ $t('Status') }}</th>
                                <th class="px-5 py-4 text-start text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ $t('Payment method') }}</th>
                                <th class="px-5 py-4 text-start text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ $t('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="item in rows.data" :key="item.uuid" class="transition hover:bg-slate-50/80">
                                <td class="px-5 py-4 align-top text-sm font-semibold text-slate-900">{{ item.invoice_number }}</td>
                                <td class="px-5 py-4 align-top text-sm text-slate-600">{{ item.issued_at }}</td>
                                <td v-if="showOrganization" class="px-5 py-4 align-top text-sm text-slate-700">{{ item.organization_name ?? '—' }}</td>
                                <td class="px-5 py-4 align-top text-sm text-slate-700">{{ item.plan_name ?? '—' }}</td>
                                <td class="px-5 py-4 align-top text-sm font-semibold text-slate-900">{{ item.total }}</td>
                                <td class="px-5 py-4 align-top">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1" :class="statusClass(item.status_label)">
                                        {{ item.status_label }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 align-top text-sm text-slate-700">{{ item.payment_method_label }}</td>
                                <td class="px-5 py-4 align-top">
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

                <div class="divide-y divide-slate-100 xl:hidden">
                    <article
                        v-for="item in rows.data"
                        :key="`${item.uuid}-mobile`"
                        class="space-y-4 px-4 py-4"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-900">{{ item.invoice_number }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ item.issued_at }}</p>
                            </div>
                            <span class="rounded-full px-3 py-1 text-xs font-semibold ring-1" :class="statusClass(item.status_label)">
                                {{ item.status_label }}
                            </span>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="rounded-[1rem] bg-slate-50 px-3 py-3">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">{{ $t('Plan') }}</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900">{{ item.plan_name ?? '—' }}</p>
                            </div>
                            <div class="rounded-[1rem] bg-slate-50 px-3 py-3">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">{{ $t('Total') }}</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900">{{ item.total }}</p>
                            </div>
                            <div v-if="showOrganization" class="rounded-[1rem] bg-slate-50 px-3 py-3">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">{{ $t('Organization') }}</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900">{{ item.organization_name ?? '—' }}</p>
                            </div>
                            <div class="rounded-[1rem] bg-slate-50 px-3 py-3">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">{{ $t('Payment method') }}</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900">{{ item.payment_method_label }}</p>
                            </div>
                        </div>

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
