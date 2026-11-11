<template>
    <!-- Sidebar item -->
    <li v-if="available && variant === 'menu'" class="ui-sidebar-item rounded-xl px-2 truncate" :class="isActive ? 'ui-sidebar-item--active' : ''">
        <Link href="/alerts" class="flex items-center justify-between p-2 gap-x-3 rounded-xl" :aria-label="label">
            <div class="flex items-center gap-x-3">
                <span class="ui-sidebar-icon-chip" :class="iconsOnly && count > 0 ? 'ui-sidebar-icon-chip--dot' : ''">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9a6 6 0 1 1 12 0c0 7 3 8 3 8H3s3-1 3-8"/><path d="M10.3 21a2 2 0 0 0 3.4 0"/></svg>
                </span>
                <span class="ui-sidebar-item-label" :class="iconsOnly ? 'ui-sidebar-item-label--collapsed' : ''">{{ $t('Notifications') }}</span>
            </div>
            <span v-if="count > 0 && !iconsOnly" class="ui-sidebar-badge" aria-hidden="true">{{ shownCount }}</span>
        </Link>
    </li>

    <!-- Mobile top bar -->
    <Link v-else-if="available" href="/alerts" class="nl-bell" :aria-label="label" :title="label">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9a6 6 0 1 1 12 0c0 7 3 8 3 8H3s3-1 3-8"/><path d="M10.3 21a2 2 0 0 0 3.4 0"/></svg>
        <span v-if="count > 0" class="nl-count" aria-hidden="true">{{ shownCount }}</span>
    </Link>
</template>
<script setup>
    import { computed, onBeforeUnmount, onMounted } from 'vue';
    import { Link, usePage } from '@inertiajs/vue3';
    import { useI18n } from 'vue-i18n';
    import { toast } from 'vue3-toastify';
    import { useHumanRequests } from '@/Composables/useHumanRequests';

    const props = defineProps({
        variant: { type: String, default: 'menu' },
        iconsOnly: { type: Boolean, default: false },
    });

    const { t } = useI18n();
    const page = usePage();
    const { available, count, listen, start, changeListeners, claimAnnouncement } = useHumanRequests();

    const shownCount = computed(() => (count.value > 99 ? '99+' : count.value));
    const isActive = computed(() => page.url.startsWith('/alerts'));
    const label = computed(() => (count.value > 0
        ? t('Notifications: {count} waiting', { count: count.value })
        : t('Notifications')));

    const onChange = (opened, eventId) => {
        if (opened && claimAnnouncement(eventId)) {
            toast(t('A conversation is waiting for the team'), { autoClose: 5000 });
        }
    };

    onMounted(() => {
        start();
        changeListeners.add(onChange);

        const config = page.props.config ?? [];
        const value = (key) => config.find((item) => item.key === key)?.value;
        listen(page.props.auth?.user?.id, value('pusher_app_key'), value('pusher_app_cluster'));
    });

    onBeforeUnmount(() => changeListeners.delete(onChange));
</script>
<style scoped>
.nl-bell {
    position: relative; width: 44px; height: 44px; display: inline-flex; align-items: center; justify-content: center;
    border-radius: 12px; color: var(--ui-text); touch-action: manipulation;
}
.nl-bell:focus-visible { outline: 3px solid var(--ui-secondary); outline-offset: 2px; }
.nl-count {
    position: absolute; top: 2px; inset-inline-end: 0; min-width: 20px; height: 20px; padding: 0 5px;
    border-radius: 999px; background: #d92d20; color: #fff; font-size: 12px; font-weight: 700; line-height: 20px;
    text-align: center; font-variant-numeric: tabular-nums;
}
</style>
