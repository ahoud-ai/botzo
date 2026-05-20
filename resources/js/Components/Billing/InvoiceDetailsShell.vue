<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    title: {
        type: String,
        default: null,
    },
    contextLabel: {
        type: String,
        default: null,
    },
    contextDescription: {
        type: String,
        default: null,
    },
    invoice: {
        type: Object,
        required: true,
    },
    backUrl: {
        type: String,
        default: null,
    },
    backLabel: {
        type: String,
        default: null,
    },
    printUrl: {
        type: String,
        default: null,
    },
    downloadUrl: {
        type: String,
        default: null,
    },
});

const { locale, t } = useI18n();

const documentDirection = computed(() => String(locale.value ?? '').startsWith('ar') ? 'rtl' : 'ltr');
const resolvedTitle = computed(() => props.title ?? props.invoice?.invoice_number ?? t('Invoice'));

const primaryActionClass = 'inline-flex min-h-10 items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600';
const secondaryActionClass = 'inline-flex min-h-10 items-center justify-center rounded-md border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-300';
const softPrimaryActionClass = 'inline-flex min-h-10 items-center justify-center rounded-md border border-primary/20 bg-primary/5 px-4 py-2 text-sm font-medium text-primary shadow-sm transition hover:bg-primary/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600';

const keyFacts = computed(() => ([
    {
        label: t('Invoice no.'),
        value: props.invoice?.invoice_number ?? t('Not set'),
        forceLtr: true,
    },
    {
        label: t('Issued date'),
        value: props.invoice?.issued_at ?? t('Not set'),
        forceLtr: true,
    },
    {
        label: t('Status'),
        value: props.invoice?.status_label ?? t('Not set'),
    },
    {
        label: t('Total'),
        value: props.invoice?.summary?.total ?? '0.00',
        emphasize: true,
        forceLtr: true,
    },
]));

const partyCards = computed(() => ([
    {
        title: t('Vendor'),
        rows: [
            { label: t('Name'), value: props.invoice?.vendor?.name ?? t('Not set') },
            { label: t('Tax ID'), value: props.invoice?.vendor?.tax_id || t('Not set'), forceLtr: true },
            {
                label: t('Phone'),
                value: props.invoice?.vendor?.phones?.length
                    ? props.invoice.vendor.phones.join(' / ')
                    : t('Not set'),
                forceLtr: true,
            },
        ],
    },
    {
        title: t('Customer'),
        rows: [
            { label: t('Organization'), value: props.invoice?.customer?.name ?? t('Not set') },
            { label: t('Owner'), value: props.invoice?.customer?.owner_name ?? t('Not set') },
            { label: t('Email'), value: props.invoice?.customer?.email ?? t('Not set'), forceLtr: true },
        ],
    },
]));

const summaryRows = computed(() => {
    const baseRows = [
        { label: t('Subtotal'), value: props.invoice?.summary?.subtotal ?? '0.00', forceLtr: true },
    ];

    const taxRows = Array.isArray(props.invoice?.tax_lines) && props.invoice.tax_lines.length
        ? props.invoice.tax_lines.map((line) => ({
            label: line.label ?? t('Tax'),
            value: line.amount ?? '0.00',
            forceLtr: true,
        }))
        : [{ label: t('Tax'), value: props.invoice?.summary?.tax ?? '0.00', forceLtr: true }];

    return [
        ...baseRows,
        ...taxRows,
        { label: t('Total'), value: props.invoice?.summary?.total ?? '0.00', emphasize: true, forceLtr: true },
    ];
});

const paymentRows = computed(() => ([
    {
        label: t('Payment method'),
        value: props.invoice?.payment?.method_label ?? t('Not set'),
    },
    {
        label: t('Reference'),
        value: props.invoice?.payment?.reference ?? t('Not set'),
        forceLtr: true,
    },
    {
        label: t('Paid at'),
        value: props.invoice?.payment?.paid_at ?? t('Not set'),
        forceLtr: true,
    },
    {
        label: t('Plan'),
        value: props.invoice?.subscription?.plan_name ?? t('Not set'),
    },
    {
        label: t('Billing cycle'),
        value: props.invoice?.subscription?.period ?? t('Not set'),
    },
]));
</script>

<template>
    <div class="space-y-6" :dir="documentDirection">
        <section class="rounded-[1rem] border border-slate-200 bg-white p-6 shadow-sm shadow-slate-200/60 md:p-8">
            <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                <div class="max-w-3xl">
                    <div v-if="contextLabel" class="inline-flex rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">
                        {{ contextLabel }}
                    </div>
                    <h1 class="mt-4 text-2xl font-semibold tracking-tight text-slate-950 md:text-3xl">
                        {{ resolvedTitle }}
                    </h1>
                    <p class="mt-3 text-sm leading-7 text-slate-600">
                        {{ contextDescription ?? $t('Review the essential invoice details here, then print or download the official PDF when needed.') }}
                    </p>
                </div>

                <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap xl:justify-end">
                    <Link
                        v-if="backUrl"
                        :href="backUrl"
                        :class="secondaryActionClass"
                    >
                        {{ backLabel ?? $t('Back') }}
                    </Link>
                    <a
                        v-if="downloadUrl"
                        :href="downloadUrl"
                        download
                        :class="softPrimaryActionClass"
                    >
                        {{ $t('Download PDF') }}
                    </a>
                    <a
                        v-if="printUrl"
                        :href="printUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        :class="primaryActionClass"
                    >
                        {{ $t('Print') }}
                    </a>
                </div>
            </div>

            <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <article
                    v-for="fact in keyFacts"
                    :key="fact.label"
                    class="rounded-[0.9rem] border border-slate-200 bg-slate-50 px-4 py-4"
                >
                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">{{ fact.label }}</p>
                    <p
                        class="mt-2 break-words text-sm font-semibold text-slate-900"
                        :class="fact.emphasize ? 'text-lg text-primary' : ''"
                        :dir="fact.forceLtr ? 'ltr' : 'auto'"
                    >
                        {{ fact.value }}
                    </p>
                </article>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(320px,0.85fr)]">
            <section class="rounded-[1rem] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/50">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-slate-950">{{ $t('Invoice items') }}</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ $t('Only the billed lines required for review are listed here.') }}</p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600" dir="ltr">
                        {{ props.invoice?.invoice_number }}
                    </span>
                </div>

                <div class="mt-4 overflow-hidden rounded-[0.95rem] border border-slate-200">
                    <div class="hidden overflow-x-auto lg:block">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ $t('Item') }}</th>
                                    <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ $t('Description') }}</th>
                                    <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ $t('Amount') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr v-for="item in props.invoice?.items ?? []" :key="`${item.label}-${item.amount}`">
                                    <td class="px-4 py-4 text-sm font-semibold text-slate-900">{{ item.label ?? '—' }}</td>
                                    <td class="px-4 py-4 text-sm text-slate-600">{{ item.description ?? '—' }}</td>
                                    <td class="px-4 py-4 text-sm font-semibold text-slate-900" dir="ltr">{{ item.amount ?? '0.00' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="divide-y divide-slate-100 lg:hidden">
                        <article
                            v-for="item in props.invoice?.items ?? []"
                            :key="`${item.label}-${item.amount}-mobile`"
                            class="space-y-3 px-4 py-4"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <p class="text-sm font-semibold text-slate-900">{{ item.label ?? '—' }}</p>
                                <span class="rounded-md bg-primary/8 px-3 py-1 text-xs font-semibold text-primary" dir="ltr">
                                    {{ item.amount ?? '0.00' }}
                                </span>
                            </div>
                            <p class="text-sm leading-6 text-slate-600">{{ item.description ?? '—' }}</p>
                        </article>
                    </div>
                </div>
            </section>

            <section class="space-y-6">
                <section class="rounded-[1rem] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/50">
                    <h2 class="text-base font-semibold text-slate-950">{{ $t('Payment summary') }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $t('Payment and plan information stays available here without opening the PDF.') }}</p>
                    <div class="mt-4 space-y-3">
                        <div
                            v-for="row in paymentRows"
                            :key="row.label"
                            class="rounded-[0.9rem] border border-slate-200 bg-slate-50 px-4 py-3"
                        >
                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">{{ row.label }}</p>
                            <p class="mt-1 break-words text-sm font-semibold text-slate-900" :dir="row.forceLtr ? 'ltr' : 'auto'">
                                {{ row.value }}
                            </p>
                        </div>
                    </div>
                </section>

                <section class="rounded-[1rem] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/50">
                    <h2 class="text-base font-semibold text-slate-950">{{ $t('Invoice totals') }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $t('Totals are grouped in one compact block for a quick accounting check.') }}</p>
                    <div class="mt-4 space-y-3">
                        <div
                            v-for="row in summaryRows"
                            :key="row.label"
                            class="flex items-center justify-between gap-3 rounded-[0.9rem] px-4 py-3"
                            :class="row.emphasize ? 'bg-primary/8 ring-1 ring-primary/15' : 'border border-slate-200 bg-slate-50'"
                        >
                            <span class="text-sm font-medium text-slate-600">{{ row.label }}</span>
                            <span
                                class="text-sm font-semibold text-slate-950"
                                :class="row.emphasize ? 'text-lg text-primary' : ''"
                                :dir="row.forceLtr ? 'ltr' : 'auto'"
                            >
                                {{ row.value }}
                            </span>
                        </div>
                    </div>
                </section>
            </section>
        </div>

        <section class="rounded-[1rem] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/50">
            <h2 class="text-base font-semibold text-slate-950">{{ $t('Billing parties') }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ $t('Vendor and customer details remain visible for a quick finance review.') }}</p>

            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                <article
                    v-for="card in partyCards"
                    :key="card.title"
                    class="rounded-[0.95rem] border border-slate-200 bg-slate-50 px-4 py-4"
                >
                    <h3 class="text-sm font-semibold text-slate-950">{{ card.title }}</h3>
                    <div class="mt-4 space-y-3">
                        <div
                            v-for="row in card.rows"
                            :key="`${card.title}-${row.label}`"
                            class="rounded-[0.85rem] bg-white px-3 py-3"
                        >
                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">{{ row.label }}</p>
                            <p class="mt-1 break-words text-sm font-semibold text-slate-900" :dir="row.forceLtr ? 'ltr' : 'auto'">
                                {{ row.value }}
                            </p>
                        </div>
                    </div>
                </article>
            </div>
        </section>
    </div>
</template>
