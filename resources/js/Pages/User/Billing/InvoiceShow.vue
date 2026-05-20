<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame text-[var(--ui-text)] min-h-full">
            <InvoiceDetailsShell
                :title="props.title"
                :context-label="$t('Billing workspace')"
                :context-description="$t('Review the essential invoice details in one page, then print or download the official PDF only when you need it.')"
                :invoice="props.invoice"
                :back-url="props.backUrl"
                :back-label="props.backLabel"
                :print-url="props.printUrl"
                :download-url="props.downloadUrl"
            />
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from './../Layout/App.vue';
import InvoiceDetailsShell from '@/Components/Billing/InvoiceDetailsShell.vue';

const props = defineProps({
    title: String,
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
</script>
