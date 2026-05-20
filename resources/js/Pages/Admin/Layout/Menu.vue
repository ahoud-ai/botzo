<template>
    <div class="admin-menu creative-menu flex h-full min-h-0 flex-col overflow-hidden">
        <div class="admin-menu__brand-wrap">
            <Link href="/admin/dashboard" class="admin-menu__brand">
                <span v-if="logoSrc" class="admin-menu__logo">
                    <img :src="logoSrc" :alt="companyName" />
                </span>
                <span v-else class="admin-menu__brand-mark">{{ brandInitial }}</span>

                <span class="admin-menu__brand-copy">
                    <span class="admin-menu__brand-title">{{ companyName }}</span>
                    <span class="admin-menu__brand-subtitle">{{ $t('Administration') }}</span>
                </span>
            </Link>

            <button
                v-if="props.isSidebarOpen === true"
                type="button"
                class="admin-menu__close"
                :aria-label="$t('Close')"
                :title="$t('Close')"
                @click="closeSidebar"
            >
                <X class="h-5 w-5" />
            </button>
        </div>

        <nav class="admin-menu__nav" :aria-label="$t('Administration')">
            <section v-for="section in visibleSections" :key="section.key" class="admin-menu-section">
                <div class="admin-menu-section__label">{{ $t(section.labelKey) }}</div>

                <Link
                    v-for="item in section.items"
                    :key="item.href"
                    :href="item.href"
                    class="admin-menu-item"
                    :class="{ 'admin-menu-item--active': isActive(item) }"
                    :style="item.style"
                    :aria-current="isActive(item) ? 'page' : undefined"
                    :title="$t(item.labelKey)"
                >
                    <span class="admin-menu-item__icon">
                        <component :is="item.icon" class="h-5 w-5" />
                    </span>
                    <span class="admin-menu-item__label">{{ $t(item.labelKey) }}</span>
                    <span class="admin-menu-item__spark" aria-hidden="true" />
                </Link>
            </section>
        </nav>

        <div class="admin-menu__profile">
            <button type="button" class="admin-menu-profile__main" @click="openModal">
                <span class="admin-menu-profile__avatar">
                    <img v-if="props.user.avatar" :src="'/media/' + props.user.avatar" :alt="userName" />
                    <UserRound v-else class="h-5 w-5" />
                </span>
                <span class="admin-menu-profile__copy">
                    <span class="admin-menu-profile__name">{{ userName }}</span>
                    <span class="admin-menu-profile__meta">{{ $t('View profile') }}</span>
                </span>
            </button>

            <Link
                href="/logout"
                method="post"
                as="button"
                type="button"
                class="admin-menu-profile__logout"
                :aria-label="$t('Logout')"
                :title="$t('Logout')"
            >
                <LogOut class="h-5 w-5" />
            </Link>
        </div>
    </div>

    <ProfileModal :user="props.user" :organization="{}" :isOpen="isOpen" role="admin" :languages="languages" @close="closeModal()" />
</template>

<script setup>
    import { Link, usePage } from "@inertiajs/vue3";
    import { computed, defineProps, ref } from "vue";
    import {
        Building2,
        CircleHelp,
        CreditCard,
        LayoutDashboard,
        LogOut,
        MessageSquareText,
        Settings2,
        ShieldCheck,
        Star,
        UserRound,
        UsersRound,
        X,
    } from "lucide-vue-next";
    import ProfileModal from '@/Components/ProfileModal.vue';
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
    const { adminCan, adminCanAny } = useAdminPermission();
    const isOpen = ref(false);

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

    const getValueByKey = (key) => {
        const found = props.config.find(item => item.key === key);
        return found ? found.value : '';
    };

    const companyName = computed(() => getValueByKey('company_name') || 'Botzo');
    const logoSrc = computed(() => {
        const logo = getValueByKey('logo');
        return logo ? `/media/${logo}` : '';
    });
    const brandInitial = computed(() => companyName.value.trim().charAt(0).toUpperCase() || 'B');
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
                menuItem('Dashboard', '/admin/dashboard', LayoutDashboard, adminCan('customers', 'view'), ['--ui-primary', '--ui-secondary']),
            ],
        },
        {
            key: 'operations',
            labelKey: 'Operations',
            items: [
                menuItem('Organizations', '/admin/organizations', Building2, adminCan('organizations', 'view'), ['--ui-secondary', '--ui-primary']),
                menuItem('Users', '/admin/users', UsersRound, adminCan('customers', 'view'), ['--ui-primary', '--ui-accent']),
                menuItem('Billing', '/admin/payment-logs', CreditCard, adminCan('billing', 'view'), ['--ui-accent', '--ui-primary']),
                menuItem('Support desk', '/admin/support', MessageSquareText, adminCan('support', 'view'), ['--ui-success', '--ui-primary']),
                menuItem('Team', '/admin/team/users', UsersRound, adminCan('team', 'view'), ['--ui-secondary', '--ui-success']),
            ],
        },
        {
            key: 'growth',
            labelKey: 'Growth',
            items: [
                menuItem('Roles', '/admin/team/roles', ShieldCheck, adminCan('roles', 'view'), ['--ui-primary', '--ui-secondary']),
                menuItem('Subscription plans', '/admin/plans', CreditCard, adminCan('subscription_plans', 'view'), ['--ui-accent', '--ui-secondary']),
                menuItem('FAQs', '/admin/faqs', CircleHelp, adminCan('settings', 'frontend'), ['--ui-secondary', '--ui-primary']),
                menuItem('Reviews', '/admin/testimonials', Star, adminCan('settings', 'frontend'), ['--ui-accent', '--ui-primary']),
            ],
        },
        {
            key: 'system',
            labelKey: 'Administration',
            items: [
                {
                    ...menuItem('Settings', settingsEntryHref.value, Settings2, canAccessSettings.value, ['--ui-primary', '--ui-accent']),
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

    function menuItem(labelKey, href, icon, visible, tones) {
        return {
            labelKey,
            href,
            icon,
            visible,
            activePaths: [href],
            style: {
                '--item-tone': `var(${tones[0]})`,
                '--item-accent': `var(${tones[1]})`,
            },
        };
    }

    const isActive = (item) => item.activePaths.some((path) => currentPath.value === path || currentPath.value.startsWith(`${path}/`));

    const closeSidebar = () => {
        emit('closeSidebar', true);
    };

    const closeModal = () => {
        isOpen.value = false;
    };

    const openModal = () => {
        isOpen.value = true;
        closeSidebar();
    };
</script>

<style scoped>
.admin-menu {
    position: relative;
    background:
        radial-gradient(360px 180px at 112% -8%, color-mix(in srgb, var(--ui-primary) 13%, transparent), transparent 68%),
        radial-gradient(300px 170px at -12% 96%, color-mix(in srgb, var(--ui-secondary) 11%, transparent), transparent 70%),
        linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(248, 251, 255, 0.95));
}

.admin-menu::before {
    content: "";
    position: absolute;
    inset-inline: 1rem;
    top: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, color-mix(in srgb, var(--ui-primary) 26%, transparent), transparent);
}

.admin-menu__brand-wrap {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 1.25rem 1.15rem 0.85rem;
}

.admin-menu__brand {
    display: flex;
    min-width: 0;
    flex: 1;
    align-items: center;
    gap: 0.75rem;
    text-decoration: none;
}

.admin-menu__logo {
    display: inline-flex;
    min-width: 0;
    align-items: center;
}

.admin-menu__logo img {
    max-width: 9.25rem;
    max-height: 3rem;
    object-fit: contain;
}

.admin-menu__brand-mark {
    display: inline-flex;
    width: 3rem;
    height: 3rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 1rem;
    background: linear-gradient(135deg, var(--ui-primary), color-mix(in srgb, var(--ui-primary) 72%, var(--ui-secondary)));
    color: #fff;
    font-size: 1.35rem;
    font-weight: 900;
    box-shadow: 0 18px 28px -22px color-mix(in srgb, var(--ui-primary) 75%, rgba(15, 23, 42, 0.45));
}

.admin-menu__brand-copy {
    display: flex;
    min-width: 0;
    flex-direction: column;
    gap: 0.1rem;
}

.admin-menu__brand-title {
    color: var(--ui-text);
    font-size: 1.15rem;
    font-weight: 900;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.admin-menu__brand-subtitle {
    color: var(--ui-muted);
    font-size: 0.76rem;
    font-weight: 700;
}

.admin-menu__close,
.admin-menu-profile__logout {
    display: inline-flex;
    width: 2.55rem;
    height: 2.55rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border: 1px solid color-mix(in srgb, var(--ui-primary) 14%, var(--ui-border));
    border-radius: 0.85rem;
    background: rgba(255, 255, 255, 0.82);
    color: color-mix(in srgb, var(--ui-text) 82%, var(--ui-primary) 18%);
    transition: transform 160ms ease, border-color 160ms ease, color 160ms ease, background 160ms ease;
}

.admin-menu__close:hover,
.admin-menu-profile__logout:hover {
    transform: translateY(-1px);
    border-color: color-mix(in srgb, var(--ui-primary) 30%, var(--ui-border));
    background: color-mix(in srgb, #fff 88%, var(--ui-primary) 12%);
    color: var(--ui-primary);
}

.admin-menu__nav {
    position: relative;
    min-height: 0;
    flex: 1;
    overflow-y: auto;
    padding: 0.45rem 0.85rem 0.8rem;
    scrollbar-width: thin;
    scrollbar-color: color-mix(in srgb, var(--ui-primary) 18%, transparent) transparent;
}

.admin-menu-section {
    padding: 0.45rem 0 0.65rem;
}

.admin-menu-section + .admin-menu-section {
    border-top: 1px solid color-mix(in srgb, var(--ui-primary) 9%, transparent);
}

.admin-menu-section__label {
    margin: 0.35rem 0.55rem 0.45rem;
    color: color-mix(in srgb, var(--ui-muted) 82%, var(--ui-primary));
    font-size: 0.68rem;
    font-weight: 900;
    letter-spacing: 0.11em;
    text-transform: uppercase;
}

.admin-menu-item {
    position: relative;
    display: flex;
    min-height: 3.1rem;
    align-items: center;
    gap: 0.75rem;
    overflow: hidden;
    border: 1px solid transparent;
    border-radius: 1rem;
    padding: 0.55rem 0.65rem;
    color: color-mix(in srgb, var(--ui-text) 84%, var(--item-tone));
    text-decoration: none;
    transition: transform 170ms ease, border-color 170ms ease, background 170ms ease, color 170ms ease, box-shadow 170ms ease;
}

.admin-menu-item::before {
    content: "";
    position: absolute;
    inset: 0;
    opacity: 0;
    background:
        linear-gradient(115deg, color-mix(in srgb, var(--item-tone) 12%, transparent), transparent 52%),
        linear-gradient(290deg, color-mix(in srgb, var(--item-accent) 9%, transparent), transparent 58%);
    transition: opacity 170ms ease;
}

.admin-menu-item:hover,
.admin-menu-item--active {
    color: color-mix(in srgb, var(--item-tone) 78%, var(--ui-text));
    border-color: color-mix(in srgb, var(--item-tone) 18%, var(--ui-border));
    background: color-mix(in srgb, #fff 92%, var(--item-tone) 8%);
}

.admin-menu-item:hover {
    transform: translateX(-2px);
}

:global(.direction-ltr) .admin-menu-item:hover {
    transform: translateX(2px);
}

.admin-menu-item--active {
    box-shadow:
        inset -3px 0 0 color-mix(in srgb, var(--item-tone) 76%, white),
        0 15px 28px -24px color-mix(in srgb, var(--item-tone) 52%, rgba(15, 23, 42, 0.42));
}

:global(.direction-ltr) .admin-menu-item--active {
    box-shadow:
        inset 3px 0 0 color-mix(in srgb, var(--item-tone) 76%, white),
        0 15px 28px -24px color-mix(in srgb, var(--item-tone) 52%, rgba(15, 23, 42, 0.42));
}

.admin-menu-item:hover::before,
.admin-menu-item--active::before {
    opacity: 1;
}

.admin-menu-item__icon,
.admin-menu-item__label,
.admin-menu-item__spark {
    position: relative;
    z-index: 1;
}

.admin-menu-item__icon {
    display: inline-flex;
    width: 2.15rem;
    height: 2.15rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border: 1px solid color-mix(in srgb, var(--item-tone) 16%, transparent);
    border-radius: 0.78rem;
    background: rgba(255, 255, 255, 0.78);
    color: color-mix(in srgb, var(--item-tone) 78%, var(--ui-text));
}

.admin-menu-item--active .admin-menu-item__icon {
    background: linear-gradient(135deg, var(--item-tone), color-mix(in srgb, var(--item-tone) 72%, var(--item-accent)));
    color: #fff;
    border-color: transparent;
}

.admin-menu-item__label {
    min-width: 0;
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.92rem;
    font-weight: 800;
}

.admin-menu-item__spark {
    width: 0.42rem;
    height: 0.42rem;
    flex-shrink: 0;
    border-radius: 999px;
    background: color-mix(in srgb, var(--item-tone) 44%, transparent);
    opacity: 0;
    transform: scale(0.65);
    transition: opacity 170ms ease, transform 170ms ease;
}

.admin-menu-item--active .admin-menu-item__spark {
    opacity: 1;
    transform: scale(1);
}

.admin-menu__profile {
    position: relative;
    display: flex;
    align-items: center;
    gap: 0.55rem;
    margin: 0.85rem;
    padding: 0.45rem;
    border: 1px solid color-mix(in srgb, var(--ui-primary) 13%, var(--ui-border));
    border-radius: 1.1rem;
    background:
        linear-gradient(135deg, rgba(255, 255, 255, 0.96), color-mix(in srgb, #fff 90%, var(--ui-primary) 10%));
    box-shadow: 0 18px 34px -28px rgba(15, 23, 42, 0.44);
}

.admin-menu-profile__main {
    display: flex;
    min-width: 0;
    flex: 1;
    align-items: center;
    gap: 0.6rem;
    text-align: start;
}

.admin-menu-profile__avatar {
    display: inline-flex;
    width: 2.65rem;
    height: 2.65rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border: 1px solid color-mix(in srgb, var(--ui-primary) 18%, transparent);
    border-radius: 0.95rem;
    background: color-mix(in srgb, #fff 84%, var(--ui-primary) 16%);
    color: var(--ui-primary);
}

.admin-menu-profile__avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.admin-menu-profile__copy {
    display: flex;
    min-width: 0;
    flex-direction: column;
}

.admin-menu-profile__name {
    color: var(--ui-text);
    font-size: 0.9rem;
    font-weight: 900;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.admin-menu-profile__meta {
    color: var(--ui-muted);
    font-size: 0.76rem;
    font-weight: 700;
}

@media (max-width: 768px) {
    .admin-menu__brand-wrap {
        padding-top: 1rem;
    }

    .admin-menu__nav {
        padding-inline: 0.75rem;
    }
}
</style>
