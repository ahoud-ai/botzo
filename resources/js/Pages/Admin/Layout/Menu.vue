<template>
    <div v-if="menuIconsOnly" class="admin-menu ui-sidebar-header ui-sidebar-header--compact flex flex-col items-center justify-center gap-y-2 px-2 h-24 mb-1">
        <Link href="/admin/dashboard" class="shrink-0">
            <NavBrandMark variant="mobile" />
        </Link>
        <button type="button" class="ui-sidebar-collapse-btn shrink-0" :title="$t('Expand menu')" @click="toggleMenu">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 3.5v17M3 9.4c0-2.24 0-3.36.436-4.216a4 4 0 0 1 1.748-1.748C6.04 3 7.16 3 9.4 3h5.2c2.24 0 3.36 0 4.216.436a4 4 0 0 1 1.748 1.748C21 6.04 21 7.16 21 9.4v5.2c0 2.24 0 3.36-.436 4.216a4 4 0 0 1-1.748 1.748C17.96 21 16.84 21 14.6 21H9.4c-2.24 0-3.36 0-4.216-.436a4 4 0 0 1-1.748-1.748C3 17.96 3 16.84 3 14.6z"/></svg>
        </button>
    </div>
    <div v-else class="admin-menu ui-sidebar-header flex items-center justify-between px-5 pt-5 h-20 mb-1">
        <Link href="/admin/dashboard">
            <NavBrandMark variant="desktop" />
        </Link>

        <div class="flex items-center gap-x-2">
            <button type="button" class="ui-sidebar-collapse-btn" :title="$t('Collapse menu')" @click="toggleMenu">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 3.5v17M3 9.4c0-2.24 0-3.36.436-4.216a4 4 0 0 1 1.748-1.748C6.04 3 7.16 3 9.4 3h5.2c2.24 0 3.36 0 4.216.436a4 4 0 0 1 1.748 1.748C21 6.04 21 7.16 21 9.4v5.2c0 2.24 0 3.36-.436 4.216a4 4 0 0 1-1.748 1.748C17.96 21 16.84 21 14.6 21H9.4c-2.24 0-3.36 0-4.216-.436a4 4 0 0 1-1.748-1.748C3 17.96 3 16.84 3 14.6z"/></svg>
            </button>
            <span v-if="props.isSidebarOpen === true" @click="closeSidebar()">
                <svg xmlns="http://www.w3.org/2000/svg" width="2em" height="2em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="2"><path d="M5 5L12 5L19 5"><animate fill="freeze" attributeName="d" dur="0.4s" values="M5 5L12 5L19 5;M5 5L12 12L19 5"/></path><path d="M5 12H19"><animate fill="freeze" attributeName="d" dur="0.4s" values="M5 12H19;M12 12H12"/></path><path d="M5 19L12 19L19 19"><animate fill="freeze" attributeName="d" dur="0.4s" values="M5 19L12 19L19 19;M5 19L12 12L19 19"/></path></g></svg>
            </span>
        </div>
    </div>

    <nav class="ui-menu flex-grow space-y-4 px-3 pt-2 overflow-y-auto" :aria-label="$t('Administration')">
        <div v-for="section in visibleSections" :key="section.key" class="flex-1">
            <p class="ui-sidebar-section-label" :class="menuIconsOnly ? 'ui-sidebar-section-label--collapsed' : ''">{{ $t(section.labelKey) }}</p>
            <ul class="space-y-1 text-sm mb-1">
                <li
                    v-for="item in section.items"
                    :key="item.href"
                    class="ui-sidebar-item rounded-xl px-2 truncate"
                    :class="{ 'ui-sidebar-item--active': isActive(item) }"
                >
                    <Link :href="item.href" :title="$t(item.labelKey)" :aria-current="isActive(item) ? 'page' : undefined" class="flex items-center p-2 gap-x-3 rounded-xl">
                        <span class="ui-sidebar-icon-chip"><component :is="item.icon" class="h-[18px] w-[18px]" /></span>
                        <span class="ui-sidebar-item-label" :class="menuIconsOnly ? 'ui-sidebar-item-label--collapsed' : ''">{{ $t(item.labelKey) }}</span>
                    </Link>
                </li>
            </ul>
        </div>
    </nav>

    <div class="ui-sidebar-footer">
        <div v-if="!menuIconsOnly" class="ui-sidebar-utility-row">
            <ThemeToggle />
            <LangToggle :languages="languages" :currentLanguage="currentLanguage" />
        </div>
        <div v-else class="mb-2 flex flex-col items-center gap-2">
            <ThemeToggle />
        </div>

        <Dropdown align="top-start" class="ui-sidebar-profile-dropdown">
            <div class="ui-sidebar-profile-card cursor-pointer" :class="!menuIconsOnly ? 'justify-between' : 'justify-center'">
                <div class="flex min-w-0 items-center gap-x-2">
                    <span class="ui-sidebar-profile-avatar">
                        <img v-if="props.user.avatar" class="h-full w-full object-cover" :src="'/media/' + props.user.avatar" :alt="userName" />
                        <UserRound v-else class="h-5 w-5" />
                    </span>
                    <div v-if="!menuIconsOnly" class="min-w-0">
                        <h2 class="ui-sidebar-profile-name truncate">{{ userName }}</h2>
                    </div>
                </div>
                <svg v-if="!menuIconsOnly" class="shrink-0 text-[var(--ui-muted)]" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="m8 10l4 4l4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>

            <template #items>
                <DropdownItemGroup>
                    <DropdownItem as="button" @click="openModal">
                        {{ $t('View profile') }}
                    </DropdownItem>
                    <MenuItem v-slot="{ active }">
                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            type="button"
                            :class="[active ? 'ui-dropdown-item ui-dropdown-item-active' : 'ui-dropdown-item', 'group flex w-full items-center gap-x-2 rounded-md px-2 py-2 text-sm ui-sidebar-logout-item']"
                        >
                            {{ $t('Logout') }}
                        </Link>
                    </MenuItem>
                </DropdownItemGroup>
            </template>
        </Dropdown>
    </div>

    <ProfileModal :user="props.user" :organization="{}" :isOpen="isOpen" role="admin" :languages="languages" @close="closeModal()" />
</template>

<script setup>
    import { Link, usePage } from "@inertiajs/vue3";
    import { computed, defineProps, ref } from "vue";
    import { MenuItem } from "@headlessui/vue";
    import {
        BadgeCheck,
        Building2,
        CircleHelp,
        CreditCard,
        LayoutDashboard,
        MessageSquareText,
        Settings2,
        ShieldCheck,
        Star,
        UserRound,
        UsersRound,
    } from "lucide-vue-next";
    import NavBrandMark from '@/Components/NavBrandMark.vue';
    import ProfileModal from '@/Components/ProfileModal.vue';
    import Dropdown from '@/Components/Dropdown.vue';
    import DropdownItem from '@/Components/DropdownItem.vue';
    import DropdownItemGroup from '@/Components/DropdownItemGroup.vue';
    import ThemeToggle from '@/Components/ThemeToggle.vue';
    import LangToggle from '@/Components/LangToggle.vue';
    import { useAdminPermission } from "@/Composables/useAdminPermission";

    const props = defineProps({
        config: {
            type: Array,
            required: true,
        },
        user: {
            type: Object,
            required: true,
        },
        organization: {
            type: Object,
            default: null,
        },
        organizations: {
            type: Array,
            default: () => [],
        },
        isSidebarOpen: {
            type: Boolean,
            default: false,
        },
    });

    const emit = defineEmits(['closeSidebar']);
    const page = usePage();
    const languages = computed(() => page.props.languages);
    const currentLanguage = computed(() => page.props.currentLanguage);
    const { adminCan, adminCanAny } = useAdminPermission();
    const isOpen = ref(false);
    const menuIconsOnly = ref(localStorage.getItem('AdminMenuOpen') === 'true');

    const settingsActions = [
        'general',
        'timezone',
        'broadcast_driver',
        'payment_gateways',
        'smtp',
        'email_templates',
        'frontend',
        'billing',
        'tax_rates',
        'coupons',
    ];

    const userName = computed(() => `${props.user.first_name ?? ''} ${props.user.last_name ?? ''}`.trim() || props.user.email || 'Admin');
    const currentPath = computed(() => (page.url || '').split('?')[0].replace(/\/+$/, '') || '/');

    const canAccessSettings = computed(() => adminCanAny('settings', settingsActions));
    const settingsEntryHref = computed(() => {
        const links = [
            { action: 'general', href: '/admin/settings/general' },
            { action: 'timezone', href: '/admin/settings/timezone' },
            { action: 'broadcast_driver', href: '/admin/settings/broadcast-drivers' },
            { action: 'payment_gateways', href: '/admin/payment-gateways' },
            { action: 'smtp', href: '/admin/settings/smtp' },
            { action: 'email_templates', href: '/admin/settings/email-templates' },
            { action: 'frontend', href: '/admin/settings/frontend' },
            { action: 'billing', href: '/admin/settings/billing' },
            { action: 'tax_rates', href: '/admin/tax-rates' },
            { action: 'coupons', href: '/admin/coupons' },
        ];

        const firstAccessible = links.find((link) => adminCan('settings', link.action));
        return firstAccessible?.href ?? '/admin/settings/general';
    });

    const menuSections = computed(() => [
        {
            key: 'overview',
            labelKey: 'Overview',
            items: [
                menuItem('Dashboard', '/admin/dashboard', LayoutDashboard, adminCan('customers', 'view')),
            ],
        },
        {
            key: 'operations',
            labelKey: 'Operations',
            items: [
                menuItem('Organizations', '/admin/organizations', Building2, adminCan('organizations', 'view')),
                menuItem('Users', '/admin/users', UsersRound, adminCan('customers', 'view')),
                menuItem('Billing', '/admin/payment-logs', CreditCard, adminCan('billing', 'view')),
                menuItem('Support desk', '/admin/support', MessageSquareText, adminCan('support', 'view')),
                menuItem('Meta verifications', '/admin/meta-verifications', BadgeCheck, adminCan('meta_verifications', 'view')),
                menuItem('Team', '/admin/team/users', UsersRound, adminCan('team', 'view')),
            ],
        },
        {
            key: 'growth',
            labelKey: 'Growth',
            items: [
                menuItem('Roles', '/admin/team/roles', ShieldCheck, adminCan('roles', 'view')),
                menuItem('Subscription plans', '/admin/plans', CreditCard, adminCan('subscription_plans', 'view')),
                menuItem('FAQs', '/admin/faqs', CircleHelp, adminCan('settings', 'frontend')),
                menuItem('Reviews', '/admin/testimonials', Star, adminCan('settings', 'frontend')),
            ],
        },
        {
            key: 'system',
            labelKey: 'Administration',
            items: [
                {
                    ...menuItem('Settings', settingsEntryHref.value, Settings2, canAccessSettings.value),
                    activePaths: ['/admin/settings', '/admin/payment-gateways', '/admin/tax-rates', '/admin/coupons', '/admin/languages'],
                },
            ],
        },
    ]);

    const visibleSections = computed(() => menuSections.value
        .map((section) => ({
            ...section,
            items: section.items.filter((item) => item.visible),
        }))
        .filter((section) => section.items.length > 0));

    function menuItem(labelKey, href, icon, visible) {
        return {
            labelKey,
            href,
            icon,
            visible,
            activePaths: [href],
        };
    }

    const isActive = (item) => item.activePaths.some((path) => currentPath.value === path || currentPath.value.startsWith(`${path}/`));

    const closeSidebar = () => {
        emit('closeSidebar', true);
    };

    const toggleMenu = () => {
        menuIconsOnly.value = !menuIconsOnly.value;
        localStorage.setItem('AdminMenuOpen', menuIconsOnly.value);
    };

    defineExpose({
        menuIconsOnly,
    });

    const closeModal = () => {
        isOpen.value = false;
    };

    const openModal = () => {
        isOpen.value = true;
        closeSidebar();
    };
</script>
