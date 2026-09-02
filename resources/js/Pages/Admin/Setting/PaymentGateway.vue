<template>
    <AppLayout>
        <UiPageHeader :title="$t('Payment gateways')" :subtitle="$t('Configure payment gateways for accepting subscription payments')" />
        <div class="mt-6">
            <PaymentGatewayTable :rows="props.rows" />
        </div>
    </AppLayout>
</template>
<script setup>
    import AppLayout from "./Layout/App.vue";
    import PaymentGatewayTable from '@/Components/Tables/PaymentGatewayTable.vue';
    import UiPageHeader from '@/Components/UI/UiPageHeader.vue';

    const props = defineProps({ rows: Object });
</script>

