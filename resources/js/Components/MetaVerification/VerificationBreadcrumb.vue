<script setup>
import { useI18n } from "vue-i18n";
import PageBreadcrumb from "@/Components/PageBreadcrumb.vue";

const { t } = useI18n();
</script>

<template>
    <PageBreadcrumb :current-label="t('WhatsApp Account Verification')" />
</template>
