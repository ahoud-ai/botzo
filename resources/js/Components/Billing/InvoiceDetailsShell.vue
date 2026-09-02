<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { CreditCard, Landmark, Smartphone, Wallet } from 'lucide-vue-next';

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

const primaryActionClass = 'inline-flex min-h-10 items-center justify-center gap-2 rounded-xl bg-[var(--ui-primary)] px-4 py-2 text-sm font-semibold text-white shadow-[var(--ui-shadow-1)] transition hover:opacity-90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--ui-primary)]';
const secondaryActionClass = 'inline-flex min-h-10 items-center justify-center gap-2 rounded-xl border border-[var(--ui-border-strong)] bg-[var(--ui-surface)] px-4 py-2 text-sm font-medium text-[var(--ui-text)] shadow-[var(--ui-shadow-1)] transition hover:bg-[var(--ui-surface-soft)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--ui-border-strong)]';
const softPrimaryActionClass = 'inline-flex min-h-10 items-center justify-center gap-2 rounded-xl border border-[color-mix(in_srgb,var(--ui-secondary)_35%,var(--ui-border))] bg-[color-mix(in_srgb,var(--ui-secondary)_10%,var(--ui-surface))] px-4 py-2 text-sm font-medium text-[var(--ui-secondary)] shadow-[var(--ui-shadow-1)] transition hover:bg-[color-mix(in_srgb,var(--ui-secondary)_16%,var(--ui-surface))] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--ui-secondary)]';

// status_label arrives already translated from the backend (no separate status key exists),
// so match both the English and Arabic strings this app is known to send.
const STATUS_TONE_MAP = {
    paid: 'success',
    'مدفوعة': 'success',
    'covered by balance': 'secondary',
    'مغطاة من الرصيد': 'secondary',
    settled: 'muted',
    'مسواة': 'muted',
};
const TONE_CLASSES = {
    success: 'border-[color-mix(in_srgb,var(--ui-success)_35%,var(--ui-border))] bg-[color-mix(in_srgb,var(--ui-success)_12%,var(--ui-surface))] text-[var(--ui-success)]',
    secondary: 'border-[color-mix(in_srgb,var(--ui-secondary)_35%,var(--ui-border))] bg-[color-mix(in_srgb,var(--ui-secondary)_12%,var(--ui-surface))] text-[var(--ui-secondary)]',
    muted: 'border-[var(--ui-border-strong)] bg-[var(--ui-surface-soft)] text-[var(--ui-muted)]',
};
const statusTone = computed(() => {
    const key = String(props.invoice?.status_label ?? '').trim().toLowerCase();
    return STATUS_TONE_MAP[key] ?? 'muted';
});
const statusBadgeClass = computed(() => TONE_CLASSES[statusTone.value]);

const PAYMENT_ICON_MAP = [
    { icon: Smartphone, match: /apple\s*pay/i },
    { icon: Landmark, match: /bank/i },
    { icon: CreditCard, match: /visa|mastercard|mada|american express|card/i },
];
const paymentMethodIcon = computed(() => {
    const label = String(props.invoice?.payment?.method_label ?? '');
    const found = PAYMENT_ICON_MAP.find((entry) => entry.match.test(label));
    return found ? found.icon : Wallet;
});

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
            { label: t('Phone'), value: props.invoice?.customer?.phone ?? t('Not set'), forceLtr: true },
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

// Purpose-built two-point timeline from the two real timestamps this data model has —
// not a fabricated audit trail. "Paid" only appears when payment.paid_at is truthy.
const timelinePoints = computed(() => {
    const points = [
        { label: t('Issued at'), value: props.invoice?.issued_at ?? t('Not set') },
    ];

    if (props.invoice?.payment?.paid_at) {
        points.push({ label: t('Paid'), value: props.invoice.payment.paid_at });
    }

    return points;
});
</script>

<template>
    <div class="space-y-6" :dir="documentDirection">
        <section class="rounded-[1.1rem] border border-[var(--ui-border)] bg-[var(--ui-surface)] p-6 shadow-[var(--ui-shadow-1)] md:p-8">
            <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                <div class="max-w-3xl">
                    <div class="flex flex-wrap items-center gap-2">
                        <span v-if="contextLabel" class="inline-flex rounded-full bg-[color-mix(in_srgb,var(--ui-secondary)_12%,var(--ui-surface))] px-3 py-1 text-xs font-semibold text-[var(--ui-secondary)]">
                            {{ contextLabel }}
                        </span>
                        <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold" :class="statusBadgeClass">
                            {{ invoice?.status_label ?? $t('Not set') }}
                        </span>
                    </div>
                    <h1 class="mt-4 text-2xl font-semibold tracking-tight text-[var(--ui-text)] md:text-3xl">
                        {{ resolvedTitle }}
                    </h1>
                    <p class="mt-3 text-sm leading-7 text-[var(--ui-muted)]">
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

            <div class="mt-6 grid gap-4 sm:grid-cols-3">
                <article
                    v-for="fact in keyFacts"
                    :key="fact.label"
                    class="rounded-[0.9rem] border border-[var(--ui-border)] bg-[var(--ui-surface-soft)] px-4 py-4"
                >
                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-[var(--ui-muted)]">{{ fact.label }}</p>
                    <p
                        class="mt-2 break-words text-sm font-semibold text-[var(--ui-text)]"
                        :class="fact.emphasize ? 'text-lg text-[var(--ui-secondary)]' : ''"
                        :dir="fact.forceLtr ? 'ltr' : 'auto'"
                    >
                        {{ fact.value }}
                    </p>
                </article>
            </div>

            <div v-if="timelinePoints.length" class="mt-6 flex items-center gap-0 overflow-x-auto pt-1">
                <template v-for="(point, index) in timelinePoints" :key="point.label">
                    <div class="flex shrink-0 items-center gap-2.5">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border-2" :class="'border-[var(--ui-success)] bg-[color-mix(in_srgb,var(--ui-success)_16%,var(--ui-surface))] text-[var(--ui-success)]'">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="m5 13l4 4L19 7" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        </span>
                        <div class="min-w-0">
                            <p class="text-xs font-semibold text-[var(--ui-text)]">{{ point.label }}</p>
                            <p class="truncate text-[11px] text-[var(--ui-muted)]" dir="ltr">{{ point.value }}</p>
                        </div>
                    </div>
                    <div v-if="index < timelinePoints.length - 1" class="mx-3 h-[2px] w-10 shrink-0 rounded-full bg-[var(--ui-success)] sm:w-16"></div>
                </template>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(320px,0.85fr)]">
            <section class="rounded-[1.1rem] border border-[var(--ui-border)] bg-[var(--ui-surface)] p-5 shadow-[var(--ui-shadow-1)]">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-[var(--ui-text)]">{{ $t('Invoice items') }}</h2>
                        <p class="mt-1 text-sm text-[var(--ui-muted)]">{{ $t('Only the billed lines required for review are listed here.') }}</p>
                    </div>
                    <span class="rounded-full border border-[var(--ui-border)] bg-[var(--ui-surface-soft)] px-3 py-1 text-xs font-semibold text-[var(--ui-muted)]" dir="ltr">
                        {{ props.invoice?.invoice_number }}
                    </span>
                </div>

                <div class="mt-4 overflow-hidden rounded-[0.95rem] border border-[var(--ui-border)]">
                    <div class="hidden overflow-x-auto lg:block">
                        <table class="min-w-full divide-y divide-[var(--ui-border)]">
                            <thead class="bg-[var(--ui-surface-soft)]">
                                <tr>
                                    <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-[0.16em] text-[var(--ui-muted)]">{{ $t('Item') }}</th>
                                    <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-[0.16em] text-[var(--ui-muted)]">{{ $t('Description') }}</th>
                                    <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-[0.16em] text-[var(--ui-muted)]">{{ $t('Amount') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[var(--ui-border)] bg-[var(--ui-surface)]">
                                <tr v-for="item in props.invoice?.items ?? []" :key="`${item.label}-${item.amount}`">
                                    <td class="px-4 py-4 text-sm font-semibold text-[var(--ui-text)]">{{ item.label ?? '—' }}</td>
                                    <td class="px-4 py-4 text-sm text-[var(--ui-muted)]">{{ item.description ?? '—' }}</td>
                                    <td class="px-4 py-4 text-sm font-semibold text-[var(--ui-text)]" dir="ltr">{{ item.amount ?? '0.00' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="divide-y divide-[var(--ui-border)] lg:hidden">
                        <article
                            v-for="item in props.invoice?.items ?? []"
                            :key="`${item.label}-${item.amount}-mobile`"
                            class="space-y-3 px-4 py-4"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <p class="text-sm font-semibold text-[var(--ui-text)]">{{ item.label ?? '—' }}</p>
                                <span class="rounded-md bg-[color-mix(in_srgb,var(--ui-secondary)_10%,var(--ui-surface))] px-3 py-1 text-xs font-semibold text-[var(--ui-secondary)]" dir="ltr">
                                    {{ item.amount ?? '0.00' }}
                                </span>
                            </div>
                            <p class="text-sm leading-6 text-[var(--ui-muted)]">{{ item.description ?? '—' }}</p>
                        </article>
                    </div>
                </div>
            </section>

            <section class="space-y-6">
                <section class="rounded-[1.1rem] border border-[var(--ui-border)] bg-[var(--ui-surface)] p-5 shadow-[var(--ui-shadow-1)]">
                    <h2 class="text-base font-semibold text-[var(--ui-text)]">{{ $t('Payment summary') }}</h2>
                    <p class="mt-1 text-sm text-[var(--ui-muted)]">{{ $t('Payment and plan information stays available here without opening the PDF.') }}</p>

                    <div class="mt-4 flex items-center gap-3 rounded-[0.9rem] border border-[var(--ui-border)] bg-[var(--ui-surface-soft)] px-4 py-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-[var(--ui-border)] bg-[var(--ui-surface)] text-[var(--ui-secondary)]">
                            <component :is="paymentMethodIcon" class="h-4.5 w-4.5" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-[var(--ui-muted)]">{{ $t('Payment method') }}</p>
                            <p class="mt-0.5 break-words text-sm font-semibold text-[var(--ui-text)]">{{ props.invoice?.payment?.method_label ?? $t('Not set') }}</p>
                        </div>
                    </div>

                    <div class="mt-3 space-y-3">
                        <div
                            v-for="row in paymentRows"
                            :key="row.label"
                            class="rounded-[0.9rem] border border-[var(--ui-border)] bg-[var(--ui-surface-soft)] px-4 py-3"
                        >
                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-[var(--ui-muted)]">{{ row.label }}</p>
                            <p class="mt-1 break-words text-sm font-semibold text-[var(--ui-text)]" :dir="row.forceLtr ? 'ltr' : 'auto'">
                                {{ row.value }}
                            </p>
                        </div>
                    </div>
                </section>

                <section class="rounded-[1.1rem] border border-[var(--ui-border)] bg-[var(--ui-surface)] p-5 shadow-[var(--ui-shadow-1)]">
                    <h2 class="text-base font-semibold text-[var(--ui-text)]">{{ $t('Invoice totals') }}</h2>
                    <p class="mt-1 text-sm text-[var(--ui-muted)]">{{ $t('Totals are grouped in one compact block for a quick accounting check.') }}</p>
                    <div class="mt-4 space-y-3">
                        <div
                            v-for="row in summaryRows"
                            :key="row.label"
                            class="flex items-center justify-between gap-3 rounded-[0.9rem] px-4 py-3"
                            :class="row.emphasize ? 'border border-[color-mix(in_srgb,var(--ui-secondary)_35%,var(--ui-border))] bg-[color-mix(in_srgb,var(--ui-secondary)_10%,var(--ui-surface))]' : 'border border-[var(--ui-border)] bg-[var(--ui-surface-soft)]'"
                        >
                            <span class="text-sm font-medium text-[var(--ui-muted)]">{{ row.label }}</span>
                            <span
                                class="text-sm font-semibold text-[var(--ui-text)]"
                                :class="row.emphasize ? 'text-lg text-[var(--ui-secondary)]' : ''"
                                :dir="row.forceLtr ? 'ltr' : 'auto'"
                            >
                                {{ row.value }}
                            </span>
                        </div>
                    </div>
                </section>
            </section>
        </div>

        <section class="rounded-[1.1rem] border border-[var(--ui-border)] bg-[var(--ui-surface)] p-5 shadow-[var(--ui-shadow-1)]">
            <h2 class="text-base font-semibold text-[var(--ui-text)]">{{ $t('Billing parties') }}</h2>
            <p class="mt-1 text-sm text-[var(--ui-muted)]">{{ $t('Vendor and customer details remain visible for a quick finance review.') }}</p>

            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                <article
                    v-for="card in partyCards"
                    :key="card.title"
                    class="rounded-[0.95rem] border border-[var(--ui-border)] bg-[var(--ui-surface-soft)] px-4 py-4"
                >
                    <h3 class="text-sm font-semibold text-[var(--ui-text)]">{{ card.title }}</h3>
                    <div class="mt-4 space-y-3">
                        <div
                            v-for="row in card.rows"
                            :key="`${card.title}-${row.label}`"
                            class="rounded-[0.85rem] border border-[var(--ui-border)] bg-[var(--ui-surface)] px-3 py-3"
                        >
                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-[var(--ui-muted)]">{{ row.label }}</p>
                            <p class="mt-1 break-words text-sm font-semibold text-[var(--ui-text)]" :dir="row.forceLtr ? 'ltr' : 'auto'">
                                {{ row.value }}
                            </p>
                        </div>
                    </div>
                </article>
            </div>
        </section>
    </div>
</template>
