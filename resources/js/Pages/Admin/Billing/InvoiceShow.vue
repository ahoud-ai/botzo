<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame ui-text-main min-h-full">
            <InvoiceDetailsShell
                :title="props.title"
                :context-label="$t('Admin billing workspace')"
                :context-description="$t('Open a focused invoice details page for finance review, then print or download the official document when needed.')"
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
