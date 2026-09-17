<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    invoice: {
        type: Object,
        required: true,
    },
});

const { locale, t } = useI18n();

const documentDirection = computed(() => String(locale.value ?? '').startsWith('ar') ? 'rtl' : 'ltr');

const brandName = computed(() => props.invoice?.vendor?.company_name || props.invoice?.vendor?.name || 'Botzo');

const vendorAddressLines = computed(() => (props.invoice?.vendor?.address_lines ?? []).filter(Boolean));
const customerAddressLines = computed(() => (props.invoice?.customer?.address_lines ?? []).filter(Boolean));

const summaryRows = computed(() => {
    const summary = props.invoice?.summary ?? {};
    const taxLines = Array.isArray(props.invoice?.tax_lines) ? props.invoice.tax_lines : [];

    const rows = [
        { label: t('Subtotal'), value: summary.subtotal ?? '0.00', total: false },
    ];

    if (taxLines.length) {
        taxLines.forEach((line) => rows.push({ label: line.label ?? t('Tax'), value: line.amount ?? '0.00', total: false }));
    } else {
        rows.push({ label: t('Tax'), value: summary.tax ?? '0.00', total: false });
    }

    rows.push({ label: t('Total'), value: summary.total ?? '0.00', total: true });

    return rows;
});

const items = computed(() => {
    const rows = props.invoice?.items ?? [];

    return rows.length ? rows : [{ label: t('Not set'), description: t('No invoice items available.'), amount: '0.00' }];
});
</script>

<template>
    <div class="invoice-print" :dir="documentDirection">
        <div class="ip-brand-row">
            <div class="ip-brand-left">
                <img src="/bimi/botzo-logo-new-512.png" alt="" class="ip-logo" />
            </div>
            <span class="ip-kicker">{{ $t('Official billing document') }}</span>
        </div>

        <h1 class="ip-title">{{ $t('Invoice') }}</h1>
        <p class="ip-note">{{ $t('A simplified invoice prepared for accounting review, printing, and PDF download.') }}</p>

        <div class="ip-price-box">
            <div class="ip-price-label">{{ $t('Total') }}</div>
            <div class="ip-price-value" dir="ltr">{{ invoice?.summary?.total ?? '0.00' }}</div>
            <div class="ip-price-note">{{ $t('Includes tax, if applicable') }}</div>
        </div>

        <div class="ip-chip-row">
            <div class="ip-chip ip-chip-accent">
                <div class="ip-chip-label">{{ $t('Status') }}</div>
                <div class="ip-chip-value">{{ invoice?.status_label ?? $t('Not set') }}</div>
            </div>
            <div class="ip-chip">
                <div class="ip-chip-label">{{ $t('Invoice no.') }}</div>
                <div class="ip-chip-value" dir="ltr">{{ invoice?.invoice_number ?? $t('Not set') }}</div>
            </div>
            <div class="ip-chip">
                <div class="ip-chip-label">{{ $t('Issued date') }}</div>
                <div class="ip-chip-value" dir="ltr">{{ invoice?.issued_at ?? $t('Not set') }}</div>
            </div>
        </div>

        <div class="ip-chip-row">
            <div class="ip-chip">
                <div class="ip-chip-label">{{ $t('Subscription plan') }}</div>
                <div class="ip-chip-value">{{ invoice?.subscription?.plan_name ?? $t('Not set') }}</div>
            </div>
            <div class="ip-chip">
                <div class="ip-chip-label">{{ $t('Billing period') }}</div>
                <div class="ip-chip-value">{{ invoice?.subscription?.period ?? $t('Not set') }}</div>
            </div>
            <div class="ip-chip">
                <div class="ip-chip-label">{{ $t('Payment method') }}</div>
                <div class="ip-chip-value">{{ invoice?.payment?.method_label ?? $t('Not set') }}</div>
            </div>
        </div>

        <div class="ip-divider"></div>

        <div class="ip-section">
            <h2 class="ip-section-title">{{ $t('Billing parties') }}</h2>
            <p class="ip-section-note">{{ $t('Essential vendor and customer details required to validate this invoice.') }}</p>
            <div class="ip-dual">
                <div class="ip-panel">
                    <div class="ip-panel-title">{{ $t('Vendor') }}</div>
                    <table class="ip-info-table">
                        <tr><td class="ip-info-label">{{ $t('Name') }}</td><td class="ip-info-value">{{ invoice?.vendor?.name ?? $t('Not set') }}</td></tr>
                        <tr><td class="ip-info-label">{{ $t('Tax ID') }}</td><td class="ip-info-value" dir="ltr">{{ invoice?.vendor?.tax_id ?? $t('Not set') }}</td></tr>
                        <tr><td class="ip-info-label">{{ $t('Contact') }}</td><td class="ip-info-value" dir="ltr">{{ (invoice?.vendor?.phones ?? []).join(' / ') || $t('Not set') }}</td></tr>
                        <tr>
                            <td class="ip-info-label">{{ $t('Address') }}</td>
                            <td class="ip-muted-copy">
                                <template v-if="vendorAddressLines.length">
                                    <div v-for="(line, idx) in vendorAddressLines" :key="idx">{{ line }}</div>
                                </template>
                                <template v-else>{{ $t('Not set') }}</template>
                            </td>
                        </tr>
                    </table>
                </div>
                <div class="ip-panel">
                    <div class="ip-panel-title">{{ $t('Customer') }}</div>
                    <table class="ip-info-table">
                        <tr><td class="ip-info-label">{{ $t('Organization') }}</td><td class="ip-info-value">{{ invoice?.customer?.name ?? $t('Not set') }}</td></tr>
                        <tr><td class="ip-info-label">{{ $t('Owner') }}</td><td class="ip-info-value">{{ invoice?.customer?.owner_name ?? $t('Not set') }}</td></tr>
                        <tr><td class="ip-info-label">{{ $t('Email') }}</td><td class="ip-info-value" dir="ltr">{{ invoice?.customer?.email ?? $t('Not set') }}</td></tr>
                        <tr>
                            <td class="ip-info-label">{{ $t('Address') }}</td>
                            <td class="ip-muted-copy">
                                <template v-if="customerAddressLines.length">
                                    <div v-for="(line, idx) in customerAddressLines" :key="idx">{{ line }}</div>
                                </template>
                                <template v-else>{{ $t('Not set') }}</template>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="ip-section">
            <h2 class="ip-section-title">{{ $t('Invoice items') }}</h2>
            <p class="ip-section-note">{{ $t('Only the invoice lines needed for business review and accounting approval are shown below.') }}</p>
            <table class="ip-items-table">
                <thead>
                    <tr>
                        <th style="width:24%;">{{ $t('Item') }}</th>
                        <th style="width:56%;">{{ $t('Description') }}</th>
                        <th style="width:20%;">{{ $t('Amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(item, idx) in items" :key="idx">
                        <td>{{ item.label ?? $t('Not set') }}</td>
                        <td>{{ item.description ?? '—' }}</td>
                        <td class="ip-amount" dir="ltr">{{ item.amount ?? '0.00' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="ip-section">
            <div class="ip-dual">
                <div class="ip-panel">
                    <div class="ip-panel-title">{{ $t('Payment details') }}</div>
                    <table class="ip-info-table">
                        <tr><td class="ip-info-label">{{ $t('Payment method') }}</td><td class="ip-info-value">{{ invoice?.payment?.method_label ?? $t('Not set') }}</td></tr>
                        <tr><td class="ip-info-label">{{ $t('Reference') }}</td><td class="ip-info-value" dir="ltr">{{ invoice?.payment?.reference ?? $t('Not set') }}</td></tr>
                        <tr><td class="ip-info-label">{{ $t('Paid at') }}</td><td class="ip-info-value" dir="ltr">{{ invoice?.payment?.paid_at ?? $t('Not set') }}</td></tr>
                        <tr><td class="ip-info-label">{{ $t('Billing period') }}</td><td class="ip-info-value">{{ invoice?.subscription?.period ?? $t('Not set') }}</td></tr>
                        <tr><td class="ip-info-label">{{ $t('Subscription plan') }}</td><td class="ip-info-value">{{ invoice?.subscription?.plan_name ?? $t('Not set') }}</td></tr>
                    </table>
                </div>
                <div class="ip-panel">
                    <div class="ip-panel-title">{{ $t('Invoice summary') }}</div>
                    <table class="ip-summary-table">
                        <tr v-for="row in summaryRows" :key="row.label" :class="row.total ? 'ip-summary-total' : ''">
                            <td class="ip-summary-label">{{ row.label }}</td>
                            <td class="ip-summary-value" dir="ltr">{{ row.value }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="ip-official">
            <div>
                <div class="ip-official-note">{{ $t('Secure document generated from the subscription billing system.') }}</div>
                <div class="ip-official-name">{{ brandName }}</div>
            </div>
            <div class="ip-official-end">{{ $t('Invoice') }} <span dir="ltr">{{ invoice?.invoice_number }}</span></div>
        </div>
    </div>
</template>

<style scoped>
/* Explicitly embedded (not just named in font-family) so html2canvas captures real
   Arabic glyph shaping — without an actual loaded @font-face, the browser falls back
   to whatever system font is installed, and html2canvas's canvas text renderer
   reproduces that fallback's Arabic shaping far worse than the live DOM does,
   producing broken/disconnected letterforms in the captured PDF. */
@font-face {
    font-family: 'TajawalPrint';
    src: url('/fonts/Tajawal/Tajawal-Regular.ttf') format('truetype');
    font-weight: 400;
    font-style: normal;
    font-display: block;
}
@font-face {
    font-family: 'TajawalPrint';
    src: url('/fonts/Tajawal/Tajawal-Bold.ttf') format('truetype');
    font-weight: 700;
    font-style: normal;
    font-display: block;
}

/* Single flat accent color throughout (no gradients, no secondary hues) — the Botzo
   mark itself keeps its own gradient since it's the logo graphic, not a UI color choice. */
.invoice-print {
    --ip-accent: #0E9F6E;
    --ip-accent-soft: #EAFBF3;
    --ip-accent-border: #BEEFD9;
    --ip-border: #CFD8E3;
    --ip-text: #0A0F1C;
    --ip-muted: #5C6B7A;
    --ip-body: #445566;

    width: 794px;
    padding: 30px 40px;
    background: #ffffff;
    color: var(--ip-text);
    font-family: 'TajawalPrint', 'Segoe UI', Tahoma, Arial, sans-serif;
    font-size: 12.5px;
    line-height: 1.45;
    box-sizing: border-box;
}

.ip-brand-row { display: flex; align-items: center; justify-content: space-between; }
.ip-brand-left { display: flex; align-items: center; gap: 10px; }
.ip-logo { width: 34px; height: 34px; display: block; }
.ip-brand-name { font-size: 16px; font-weight: 700; color: var(--ip-text); }
.ip-kicker {
    display: inline-block;
    padding: 6px 14px;
    border: 1px solid var(--ip-accent-border);
    background: var(--ip-accent-soft);
    color: var(--ip-accent);
    font-size: 11px;
    font-weight: 700;
    border-radius: 999px;
}

.ip-title { font-size: 25px; font-weight: 700; color: var(--ip-text); margin: 12px 0 4px; }
.ip-note { font-size: 11.5px; color: var(--ip-muted); margin: 0 0 9px; }

.ip-price-box {
    border: 1px solid var(--ip-accent-border);
    background: var(--ip-accent-soft);
    border-radius: 16px;
    padding: 11px 18px;
    margin-bottom: 9px;
}
.ip-price-label { font-size: 10.5px; color: var(--ip-accent); font-weight: 700; }
.ip-price-value { font-size: 25px; font-weight: 700; color: var(--ip-accent); margin: 2px 0; font-variant-numeric: tabular-nums; }
.ip-price-note { font-size: 10px; color: #0C8C61; }

.ip-chip-row { display: flex; gap: 8px; margin-bottom: 8px; }
.ip-chip { flex: 1; border: 1px solid var(--ip-border); border-radius: 11px; background: #ffffff; padding: 8px 12px; }
.ip-chip-accent { border-color: var(--ip-accent-border); background: var(--ip-accent-soft); }
.ip-chip-label { font-size: 9.5px; color: var(--ip-muted); }
.ip-chip-value { margin-top: 2px; font-size: 12px; font-weight: 700; color: var(--ip-text); font-variant-numeric: tabular-nums; }
.ip-chip-accent .ip-chip-value { color: var(--ip-accent); }

.ip-divider { height: 1px; background: #E5EBF3; margin: 9px 0; }

.ip-section { margin-bottom: 11px; }
.ip-section-title { font-size: 14.5px; font-weight: 700; color: var(--ip-text); margin: 0 0 2px; }
.ip-section-note { font-size: 10.5px; color: var(--ip-muted); margin: 0 0 7px; }

.ip-dual { display: flex; gap: 10px; }
.ip-panel { flex: 1; border: 1px solid var(--ip-border); border-radius: 13px; background: #ffffff; padding: 11px 14px; }
.ip-panel-title { font-size: 12.5px; font-weight: 700; color: var(--ip-text); margin-bottom: 4px; }

.ip-info-table { width: 100%; border-collapse: collapse; }
.ip-info-table tr + tr td { border-top: 1px solid #EDF2F7; }
.ip-info-label { width: 36%; padding: 5px 0; font-size: 10.5px; color: var(--ip-muted); vertical-align: top; }
.ip-info-value { padding: 5px 0; font-size: 12px; font-weight: 700; color: var(--ip-text); line-height: 1.4; vertical-align: top; font-variant-numeric: tabular-nums; }
.ip-muted-copy { padding: 5px 0; font-size: 11px; color: var(--ip-body); line-height: 1.4; vertical-align: top; }

.ip-items-table { width: 100%; border-collapse: collapse; margin-top: 2px; }
.ip-items-table th, .ip-items-table td { border: 1px solid var(--ip-border); padding: 8px 11px; vertical-align: top; text-align: start; font-size: 11.5px; line-height: 1.45; }
.ip-items-table th { background: var(--ip-accent-soft); color: var(--ip-accent); font-size: 10.5px; font-weight: 700; }
.ip-amount { text-align: end; white-space: nowrap; font-weight: 700; font-variant-numeric: tabular-nums; }

.ip-summary-table { width: 100%; border-collapse: collapse; }
.ip-summary-table td { border: 1px solid var(--ip-border); padding: 8px 12px; line-height: 1.45; }
.ip-summary-label { text-align: start; font-size: 11px; color: var(--ip-body); }
.ip-summary-value { text-align: end; font-size: 12px; font-weight: 700; color: var(--ip-text); white-space: nowrap; font-variant-numeric: tabular-nums; }
.ip-summary-total td { background: var(--ip-accent); border-color: var(--ip-accent); font-weight: 700; padding: 10px 12px; }
.ip-summary-total .ip-summary-label, .ip-summary-total .ip-summary-value { color: #ffffff; font-size: 14px; }

.ip-official {
    background: #0C8C61;
    border-radius: 13px;
    padding: 11px 16px;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.ip-official-note { font-size: 10px; color: #E3F7EE; }
.ip-official-name { font-size: 12px; font-weight: 700; margin-top: 1px; color: #ffffff; }
.ip-official-end { font-size: 10px; color: #E3F7EE; }
</style>
