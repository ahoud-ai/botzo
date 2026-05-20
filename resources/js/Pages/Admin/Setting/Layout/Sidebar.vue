<template>
    <aside class="settings-sidebar flex max-h-[52svh] w-full shrink-0 flex-col overflow-hidden border-b border-slate-200 bg-white/95 md:h-full md:max-h-none md:w-80 md:border-b-0 md:border-e md:border-slate-200">
        <div class="border-b border-slate-100 px-4 py-5">
            <h2 class="text-lg font-semibold text-slate-950">{{ $t('Settings') }}</h2>
            <p class="mt-1 text-sm leading-5 text-slate-500">{{ $t('Choose one settings area at a time') }}</p>
        </div>

        <nav class="min-h-0 flex-1 space-y-5 overflow-y-auto px-3 py-4">
            <section v-for="section in visibleSections" :key="section.key" class="space-y-2">
                <div class="px-2 text-xs font-semibold uppercase tracking-[0px] text-slate-500">
                    {{ $t(section.labelKey) }}
                </div>

                <div class="space-y-1">
                    <Link
                        v-for="item in section.items"
                        :key="item.href"
                        :href="item.href"
                        :class="[
                            'group flex min-h-11 items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                            isActive(item)
                                ? 'bg-primary text-white shadow-sm'
                                : 'text-slate-700 hover:bg-slate-50 hover:text-primary'
                        ]"
                    >
                        <span
                            :class="[
                                'flex h-8 w-8 shrink-0 items-center justify-center rounded-md border transition',
                                isActive(item)
                                    ? 'border-white/30 bg-white/15 text-white'
                                    : 'border-slate-200 bg-white text-slate-500 group-hover:border-primary/30 group-hover:text-primary'
                            ]"
                        >
                            <component :is="item.icon" class="h-4 w-4" />
                        </span>
                        <span class="min-w-0 flex-1 truncate">{{ $t(item.labelKey) }}</span>
                    </Link>
                </div>
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
