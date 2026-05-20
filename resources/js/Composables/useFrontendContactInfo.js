import { computed } from 'vue';

const normalizeText = (value) => {
    const normalized = typeof value === 'string' ? value.trim() : '';

    return normalized === '' ? null : normalized;
};

export function useFrontendContactInfo(companyConfigRef, currentLanguageCodeRef) {
    const isArabic = computed(() => {
        const code = String(currentLanguageCodeRef?.value || 'en').toLowerCase();

        return code.startsWith('ar');
    });

    const primaryPhone = computed(() => {
        const config = companyConfigRef?.value || {};

        return normalizeText(config.frontend_contact_phone_primary) || normalizeText(config.phone);
    });

    const secondaryPhone = computed(() => {
        const config = companyConfigRef?.value || {};
        const secondary = normalizeText(config.frontend_contact_phone_secondary);

        if (!secondary || secondary === primaryPhone.value) {
            return null;
        }

        return secondary;
    });

    const phoneNumbers = computed(() => {
        return [primaryPhone.value, secondaryPhone.value].filter(Boolean);
    });

    const addressLines = computed(() => {
        const config = companyConfigRef?.value || {};
        const firstLocalized = isArabic.value
            ? normalizeText(config.frontend_contact_address_primary_ar) || normalizeText(config.frontend_contact_address_primary_en)
            : normalizeText(config.frontend_contact_address_primary_en) || normalizeText(config.frontend_contact_address_primary_ar);
        const secondLocalized = isArabic.value
            ? normalizeText(config.frontend_contact_address_secondary_ar) || normalizeText(config.frontend_contact_address_secondary_en)
            : normalizeText(config.frontend_contact_address_secondary_en) || normalizeText(config.frontend_contact_address_secondary_ar);

        const lines = [firstLocalized, secondLocalized].filter(Boolean);

        if (lines.length > 0) {
            return lines;
        }

        const previousAddress = normalizeText(config.address);
        return previousAddress ? [previousAddress] : [];
    });

    const businessHoursLines = computed(() => {
        const config = companyConfigRef?.value || {};
        const firstLocalized = isArabic.value
            ? normalizeText(config.frontend_contact_business_hours_primary_ar) || normalizeText(config.frontend_contact_business_hours_primary_en)
            : normalizeText(config.frontend_contact_business_hours_primary_en) || normalizeText(config.frontend_contact_business_hours_primary_ar);
        const secondLocalized = isArabic.value
            ? normalizeText(config.frontend_contact_business_hours_secondary_ar) || normalizeText(config.frontend_contact_business_hours_secondary_en)
            : normalizeText(config.frontend_contact_business_hours_secondary_en) || normalizeText(config.frontend_contact_business_hours_secondary_ar);

        return [firstLocalized, secondLocalized].filter(Boolean);
    });

    return {
        addressLines,
        businessHoursLines,
        phoneNumbers,
        primaryPhone,
    };
}
