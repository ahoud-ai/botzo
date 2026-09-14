import { computed, unref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

/**
 * Single source of truth for turning a raw `subscription_plans` row into
 * display-ready copy (name, subtitle, price unit, feature list). Used by
 * both the full /pricing page and the home page pricing teaser section so
 * the two never drift out of sync with each other.
 */
export function usePricingPlans(addonsRef) {
    const { t } = useI18n();
    const page = usePage();
    const currentLocale = computed(() => String(page.props.currentLanguage ?? 'en').toLowerCase());
    const isArabic = computed(() => currentLocale.value.startsWith('ar'));

    const addonLabelKeyMap = {
        'Flow builder': 'Flow Builder',
    };

    const parseMetadata = (value) => {
        if (!value) return null;
        if (typeof value === 'object') return value;
        if (typeof value === 'string') {
            try {
                const parsed = JSON.parse(value);
                return typeof parsed === 'object' && parsed !== null ? parsed : null;
            } catch (error) {
                return null;
            }
        }
        return null;
    };

    const getDetail = (value, key) => parseMetadata(value)?.[key] ?? null;

    const normalizeAddonFlag = (value) => {
        if (typeof value === 'boolean') return value;
        if (typeof value === 'number') return value === 1;
        if (typeof value === 'string') return ['1', 'true', 'yes', 'on'].includes(value.trim().toLowerCase());
        return false;
    };

    const firstFilled = (...values) => {
        for (const value of values) {
            if (typeof value === 'string' && value.trim() !== '') return value.trim();
        }
        return '';
    };

    const getPlanName = (item) => (isArabic.value
        ? firstFilled(item?.display_name, item?.name_ar, item?.name, item?.name_en)
        : firstFilled(item?.display_name, item?.name_en, item?.name, item?.name_ar));

    const getPlanKey = (item) => {
        const metadata = parseMetadata(item?.metadata) ?? {};
        const fromMetadata = typeof metadata?.commercial_plan_key === 'string' ? metadata.commercial_plan_key.trim().toLowerCase() : '';
        const fromName = firstFilled(item?.name_en, item?.name, item?.name_ar).toLowerCase();

        if (['starter', 'growth', 'business'].includes(fromMetadata)) return fromMetadata;
        if (fromName.includes('growth')) return 'growth';
        if (fromName.includes('business')) return 'business';
        return 'starter';
    };

    const localizedPlanText = (texts, item) => {
        const key = getPlanKey(item);
        return texts?.[key]?.[isArabic.value ? 'ar' : 'en'] ?? texts?.starter?.[isArabic.value ? 'ar' : 'en'] ?? '';
    };

    const planSubtitle = (item) => localizedPlanText({
        starter: {
            ar: 'لبداية منظمة على واتساب بشكل منظم',
            en: 'For launching an organized WhatsApp workspace',
        },
        growth: {
            ar: 'للفرق التي تحتاج أتمتة ونمو أسرع',
            en: 'For teams that need automation and faster growth',
        },
        business: {
            ar: 'للعمليات متعددة الفرق على واتساب',
            en: 'For multi-team WhatsApp operations',
        },
    }, item);

    const resolveAddonLabelKey = (key) => addonLabelKeyMap[key] ?? key;

    const filteredAddons = (item) => {
        const metadata = parseMetadata(item?.metadata) ?? {};
        const addons = metadata?.addons;
        if (!addons || typeof addons !== 'object') return {};

        return Object.entries(unref(addonsRef) ?? {}).reduce((acc, [key, value]) => {
            if ((Number(value) === 1 || normalizeAddonFlag(value)) && Object.prototype.hasOwnProperty.call(addons, key)) {
                acc[key] = normalizeAddonFlag(addons[key]);
            }
            return acc;
        }, {});
    };

    const getCustomFeatures = (item) => {
        const rawFeatures = getDetail(item?.metadata, 'custom_features');
        if (!Array.isArray(rawFeatures)) return [];

        return rawFeatures
            .map((feature) => {
                const textAr = typeof feature?.text_ar === 'string' ? feature.text_ar.trim() : '';
                const textEn = typeof feature?.text_en === 'string' ? feature.text_en.trim() : '';
                return isArabic.value ? firstFilled(textAr, textEn) : firstFilled(textEn, textAr);
            })
            .filter((feature) => feature !== '');
    };

    const formatPlanLimit = (value) => (String(value) === '-1' ? t('Unlimited') : value);

    const limitFeature = (metadata, key, labelKey) => {
        const value = metadata?.[key];
        const available = value !== null && value !== undefined && value !== '' && Number(value) !== 0;

        return {
            label: available ? `${formatPlanLimit(value)} ${t(labelKey)}` : t(labelKey),
            available,
        };
    };

    const addonFeature = (item, key) => {
        const metadata = parseMetadata(item?.metadata) ?? {};
        const addons = metadata?.addons && typeof metadata.addons === 'object' ? metadata.addons : {};
        const visibleAddons = filteredAddons(item);
        const value = Object.prototype.hasOwnProperty.call(visibleAddons, key) ? visibleAddons[key] : addons[key];

        return {
            label: t(resolveAddonLabelKey(key)),
            available: normalizeAddonFlag(value),
        };
    };

    const advancedFlowBuilderFeature = (metadata) => ({
        label: t('Advanced Flow Builder'),
        available: normalizeAddonFlag(metadata?.flow_builder_advanced_enabled),
    });

    const serviceFeatureText = (item, type) => localizedPlanText({
        starter: {
            ar: { training: 'تهيئة وتدريب 30 دقيقة', support: 'دعم فني خلال أوقات العمل', setup: 'ربط WhatsApp Cloud API الأساسي' }[type],
            en: { training: '30-minute onboarding and training', support: 'Business-hours technical support', setup: 'Basic WhatsApp Cloud API setup' }[type],
        },
        growth: {
            ar: { training: 'إعداد وتدريب 60 دقيقة', support: 'دعم أولوية خلال أوقات العمل', setup: 'مساعدة في الردود الجاهزة والحملات' }[type],
            en: { training: '60-minute setup and training', support: 'Priority business-hours support', setup: 'Canned replies and campaign assistance' }[type],
        },
        business: {
            ar: { training: 'جلستا تدريب للفريق', support: 'دعم أولوية أعلى', setup: 'مساعدة في الفلوهات وإدارة واتساب' }[type],
            en: { training: 'Two team training sessions', support: 'Higher-priority support', setup: 'Flows and WhatsApp operations assistance' }[type],
        },
    }, item);

    const separateServiceText = (item, type) => localizedPlanText({
        starter: {
            ar: { verification: 'توثيق الحساب خدمة منفصلة حسب متطلبات Meta', metaFees: 'رسوم رسائل WhatsApp غير مشمولة' }[type],
            en: { verification: 'Account verification is a separate service', metaFees: 'WhatsApp messaging fees are not included' }[type],
        },
    }, item);

    /** Full feature list — used by the dedicated /pricing page. */
    const planFeatureList = (item) => {
        const metadata = parseMetadata(item?.metadata) ?? {};

        return [
            limitFeature(metadata, 'campaign_limit', 'Campaigns'),
            limitFeature(metadata, 'message_limit', 'Messages'),
            limitFeature(metadata, 'contacts_limit', 'Contacts'),
            limitFeature(metadata, 'canned_replies_limit', 'Canned replies'),
            limitFeature(metadata, 'team_limit', 'Users'),
            limitFeature(metadata, 'branches_limit', 'Branches'),
            addonFeature(item, 'Embedded Signup'),
            addonFeature(item, 'AI Assistant'),
            addonFeature(item, 'Flow builder'),
            advancedFlowBuilderFeature(metadata),
            { label: serviceFeatureText(item, 'training'), available: true },
            { label: serviceFeatureText(item, 'support'), available: true },
            { label: serviceFeatureText(item, 'setup'), available: true },
            { label: separateServiceText(item, 'verification'), available: false },
            { label: separateServiceText(item, 'metaFees'), available: false },
        ].filter((feature) => feature.label !== '');
    };

    /** Compact feature list (top N available features) — used by the home page teaser card. */
    const planHighlightFeatures = (item, count = 5) => {
        const metadata = parseMetadata(item?.metadata) ?? {};
        const aiAssistant = filteredAddons(item)['AI Assistant'] ?? metadata?.addons?.['AI Assistant'];

        return [
            t('One WhatsApp number'),
            t('Up to {n} conversations per month', { n: formatPlanLimit(metadata.message_limit) }),
            normalizeAddonFlag(aiAssistant) ? t('Advanced AI bot') : t('Basic AI bot'),
            t('Up to {n} ready templates', { n: formatPlanLimit(metadata.canned_replies_limit) }),
            t('Up to {n} team members', { n: formatPlanLimit(metadata.team_limit) }),
        ].slice(0, count);
    };

    const tierRank = (item) => Number(parseMetadata(item?.metadata)?.tier_rank ?? 0);

    const formatPrice = (value) => {
        const number = Math.round(Number(value) || 0);
        return isArabic.value
            ? number.toLocaleString('ar-EG').replace(/,/g, '٬')
            : number.toLocaleString('en-US');
    };

    /** Order plans by tier and flag the middle one as the featured/"Most Popular" plan. */
    const orderPlansByTier = (plans, period) => {
        const ordered = [...(plans ?? [])]
            .filter((plan) => plan.period === period)
            .sort((a, b) => tierRank(a) - tierRank(b));

        const featuredIndex = ordered.length >= 3 ? 1 : -1;

        return ordered.map((plan, index) => ({ plan, featured: index === featuredIndex }));
    };

    return {
        isArabic,
        currentLocale,
        parseMetadata,
        getPlanName,
        getPlanKey,
        planSubtitle,
        filteredAddons,
        getCustomFeatures,
        formatPlanLimit,
        planFeatureList,
        planHighlightFeatures,
        tierRank,
        formatPrice,
        orderPlansByTier,
    };
}
