<template>
    <aside class="settings-sidebar flex max-h-[52svh] w-full shrink-0 flex-col overflow-hidden border-b border-[var(--ui-border)] bg-[var(--ui-surface)] md:h-full md:max-h-none md:w-80 md:border-b-0 md:border-e">
        <div class="settings-sidebar-head border-b border-[var(--ui-border)] px-4 py-5">
            <h2 class="settings-sidebar-title">{{ $t('Settings') }}</h2>
            <p class="settings-sidebar-subtitle">{{ $t('Choose one settings area at a time') }}</p>
        </div>

        <nav class="min-h-0 flex-1 space-y-5 overflow-y-auto px-3 py-4">
            <section v-for="section in visibleSections" :key="section.key" class="space-y-2">
                <p class="ui-sidebar-section-label ui-sidebar-section-label--spaced">
                    {{ $t(section.labelKey) }}
                </p>

                <ul class="space-y-1">
                    <li
                        v-for="item in section.items"
                        :key="item.href"
                        class="ui-sidebar-item rounded-xl px-2 truncate"
                        :class="{ 'ui-sidebar-item--active': isActive(item) }"
                    >
                        <Link :href="item.href" class="flex items-center p-2 gap-x-3 rounded-xl">
                            <span class="ui-sidebar-icon-chip"><component :is="item.icon" class="h-[18px] w-[18px]" /></span>
                            <span class="ui-sidebar-item-label">{{ $t(item.labelKey) }}</span>
                        </Link>
                    </li>
                </ul>
            </section>
        </nav>
    </aside>
</template>

<script setup>
    import { Link, usePage } from "@inertiajs/vue3";
    import { computed } from "vue";
    import {
        Archive,
        Clock3,
        CreditCard,
        Languages,
        LayoutPanelTop,
        LogIn,
        Mail,
        RadioTower,
        ReceiptText,
        Settings,
        SlidersHorizontal,
        Sparkles,
    } from "lucide-vue-next";
    import { useAdminPermission } from "@/Composables/useAdminPermission";

    const { adminCan } = useAdminPermission();
    const page = usePage();

    const sections = computed(() => [
        {
            key: 'core',
            labelKey: 'Core settings',
            items: [
                { labelKey: 'General settings', href: '/admin/settings/general', permission: ['settings', 'general'], icon: Settings },
                { labelKey: 'Timezone and currency', href: '/admin/settings/timezone', permission: ['settings', 'timezone'], icon: Clock3 },
                { labelKey: 'Broadcast driver', href: '/admin/settings/broadcast-drivers', permission: ['settings', 'broadcast_driver'], icon: RadioTower },
                { labelKey: 'Social login', href: '/admin/settings/socials', permission: ['settings', 'general'], icon: LogIn },
                { labelKey: 'Storage settings', href: '/admin/settings/storage', permission: ['settings', 'general'], icon: Archive },
            ],
        },
        {
            key: 'features',
            labelKey: 'Features',
            items: [
                { labelKey: 'Embedded Signup', href: '/admin/settings/features/embedded-signup', permission: ['settings', 'general'], icon: Sparkles },
                { labelKey: 'AI Assistant', href: '/admin/settings/features/ai-assistant', permission: ['settings', 'general'], icon: Sparkles },
                { labelKey: 'Flow builder', href: '/admin/settings/features/flow-builder', permission: ['settings', 'general'], icon: Sparkles },
            ],
        },
        {
            key: 'billing',
            labelKey: 'Billing settings',
            items: [
                { labelKey: 'Payment gateways', href: '/admin/payment-gateways', permission: ['settings', 'payment_gateways'], icon: CreditCard },
                { labelKey: 'Subscription settings', href: '/admin/settings/subscription', permission: ['settings', 'general'], icon: Clock3 },
                { labelKey: 'Billing info', href: '/admin/settings/billing', permission: ['settings', 'billing'], icon: CreditCard },
                { labelKey: 'Tax rates', href: '/admin/tax-rates', permission: ['settings', 'tax_rates'], icon: ReceiptText },
                { labelKey: 'Coupons', href: '/admin/coupons', permission: ['settings', 'coupons'], icon: Sparkles },
            ],
        },
        {
            key: 'communication',
            labelKey: 'Communication settings',
            items: [
                { labelKey: 'SMTP settings', href: '/admin/settings/smtp', permission: ['settings', 'smtp'], icon: SlidersHorizontal },
                { labelKey: 'Email templates', href: '/admin/settings/email-templates', permission: ['settings', 'email_templates'], icon: Mail },
            ],
        },
        {
            key: 'frontend',
            labelKey: 'Experience settings',
            items: [
                { labelKey: 'Frontend Management', href: '/admin/settings/frontend', permission: ['settings', 'frontend'], icon: LayoutPanelTop },
                { labelKey: 'Languages & translations', href: '/admin/languages', permission: ['languages', 'view'], icon: Languages },
            ],
        },
    ]);

    const visibleSections = computed(() => sections.value
        .map((section) => ({
            ...section,
            items: section.items.filter((item) => adminCan(item.permission[0], item.permission[1])),
        }))
        .filter((section) => section.items.length > 0));

    const isActive = (item) => {
        const currentUrl = page.url || '';

        if (item.href === '/admin/payment-gateways') {
            return currentUrl.startsWith('/admin/payment-gateways');
        }

        if (item.href === '/admin/tax-rates') {
            return currentUrl.startsWith('/admin/tax-rates');
        }

        if (item.href === '/admin/coupons') {
            return currentUrl.startsWith('/admin/coupons');
        }

        if (item.href === '/admin/languages') {
            return currentUrl.startsWith('/admin/languages');
        }

        if (item.href === '/admin/settings/frontend') {
            return currentUrl.startsWith('/admin/settings/frontend')
                || currentUrl.startsWith('/admin/settings/pages')
                || currentUrl.startsWith('/admin/settings/page/');
        }

        return currentUrl.startsWith(item.href);
    };
</script>

<style scoped>
.settings-sidebar-title {
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--ui-text);
}

.settings-sidebar-subtitle {
    margin-top: 0.25rem;
    font-size: 0.85rem;
    line-height: 1.35;
    color: var(--ui-muted);
}
</style>
