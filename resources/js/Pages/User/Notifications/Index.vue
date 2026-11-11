<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame ui-text-main min-h-full">
            <UiPageHeader :title="$t('Notifications')" :subtitle="$t('Conversations waiting for the team, and how they were handled')" />

            <div class="nt-tabs" role="tablist" :aria-label="$t('Notifications')">
                <button
                    v-for="item in tabs"
                    :id="`tab-${item.key}`"
                    :key="item.key"
                    type="button"
                    role="tab"
                    class="nt-tab"
                    :class="tab === item.key ? 'nt-tab--active' : ''"
                    :aria-selected="tab === item.key ? 'true' : 'false'"
                    :aria-controls="`panel-${item.key}`"
                    :tabindex="tab === item.key ? 0 : -1"
                    @click="tab = item.key"
                    @keydown.right.prevent="switchTab(isRtl ? -1 : 1)"
                    @keydown.left.prevent="switchTab(isRtl ? 1 : -1)"
                >
                    {{ item.label }}
                    <span class="nt-tab-count">{{ item.count }}</span>
                </button>
            </div>

            <section :id="`panel-${tab}`" role="tabpanel" :aria-labelledby="`tab-${tab}`" class="nt-panel">
                <p v-if="rows.length === 0" class="nt-empty">
                    {{ tab === 'open' ? $t('No conversation is waiting for the team.') : $t('Nothing was closed in the last 30 days.') }}
                </p>

                <ul v-else class="nt-list">
                    <li v-for="item in rows" :key="item.uuid" class="nt-item">
                        <div class="nt-main">
                            <span class="nt-name">{{ item.name }}</span>
                            <span class="nt-reason">{{ reasonLabel(item.reason) }}</span>
                            <span class="nt-meta">
                                <span>{{ channelLabel(item.channel) }}</span>
                                <span aria-hidden="true">&middot;</span>
                                <span>{{ $t('Opened') }} <time :datetime="item.opened_at">{{ formatTime(item.opened_at) }}</time></span>
                            </span>
                            <span v-if="tab === 'closed'" class="nt-meta">
                                <span>{{ resolutionLabel(item.resolution) }}<template v-if="item.resolved_by_name"> &middot; {{ item.resolved_by_name }}</template></span>
                                <span aria-hidden="true">&middot;</span>
                                <time :datetime="item.resolved_at">{{ formatTime(item.resolved_at) }}</time>
                            </span>
                        </div>
                        <div class="nt-actions">
                            <Link v-if="item.action_url" :href="item.action_url" class="nt-btn nt-btn--primary">{{ $t('Open conversation') }}</Link>
                            <button
                                v-if="tab === 'open'"
                                type="button"
                                class="nt-btn"
                                :disabled="closingUuid === item.uuid"
                                :aria-label="`${$t('Close request')}: ${item.name}`"
                                @click="close(item.uuid)"
                            >
                                {{ $t('Close request') }}
                            </button>
                        </div>
                    </li>
                </ul>
            </section>
        </div>
    </AppLayout>
</template>
<script setup>
    import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
    import { Link, router } from '@inertiajs/vue3';
    import { useI18n } from 'vue-i18n';
    import { toast } from 'vue3-toastify';
    import AppLayout from './../Layout/App.vue';
    import UiPageHeader from '@/Components/UI/UiPageHeader.vue';
    import { useHumanRequests } from '@/Composables/useHumanRequests';
    import { useRtl } from '@/Composables/useRtl';

    const props = defineProps({
        open: { type: Array, default: () => [] },
        closed: { type: Array, default: () => [] },
    });

    const { t, locale } = useI18n();
    const { isRtl } = useRtl();
    const { closeRequest, changeListeners } = useHumanRequests();
    const tab = ref('open');
    const closingUuid = ref(null);

    const REASONS = {
        customer_request: 'Asked for a team member',
        voice_request: 'Asked for a team member in a call',
        flow_handoff: 'Handed over by an automation',
        knowledge_gap: 'The assistant could not answer',
        ai_unavailable: 'The assistant is not responding',
        assistant_off: 'The assistant is off and a visitor is waiting',
    };

    const RESOLUTIONS = {
        team_reply: 'A team member replied',
        manual_close: 'Closed by hand',
        assistant_resumed: 'The assistant was turned back on',
    };

    const tabs = computed(() => [
        { key: 'open', label: t('Open'), count: props.open.length },
        { key: 'closed', label: t('Closed'), count: props.closed.length },
    ]);
    const rows = computed(() => (tab.value === 'open' ? props.open : props.closed));

    const reasonLabel = (reason) => t(REASONS[reason] ?? 'Waiting for the team');
    const resolutionLabel = (resolution) => t(RESOLUTIONS[resolution] ?? 'Closed');
    const channelLabel = (channel) => (channel === 'web' ? t('Website') : t('WhatsApp'));
    const formatTime = (value) => (value
        ? new Intl.DateTimeFormat(locale.value === 'ar' ? 'ar-EG' : 'en-GB', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
        : '');

    const switchTab = (step) => {
        const keys = tabs.value.map((item) => item.key);
        tab.value = keys[(keys.indexOf(tab.value) + step + keys.length) % keys.length];
        document.getElementById(`tab-${tab.value}`)?.focus();
    };

    const reload = () => router.reload({ only: ['open', 'closed'], preserveScroll: true });

    const close = async (uuid) => {
        closingUuid.value = uuid;
        try {
            await closeRequest(uuid);
        } catch (_error) {
            toast(t('Could not close the request. Refresh the page and try again.'), { autoClose: 4000 });
        }
        reload();
        closingUuid.value = null;
    };

    // A new request or one closed elsewhere shows up without refreshing the page.
    onMounted(() => changeListeners.add(reload));
    onBeforeUnmount(() => changeListeners.delete(reload));
</script>
<style scoped>
.nt-tabs { display: flex; gap: 8px; margin: 8px 0 16px; border-bottom: 1px solid var(--ui-border); }
.nt-tab {
    display: inline-flex; align-items: center; gap: 8px; min-height: 44px; padding: 0 16px; border: 0;
    border-bottom: 2px solid transparent; background: transparent; color: var(--ui-muted); font-size: 14px; font-weight: 600; cursor: pointer;
}
.nt-tab--active { color: var(--ui-text); border-bottom-color: var(--ui-secondary); }
.nt-tab:focus-visible { outline: 3px solid var(--ui-secondary); outline-offset: -3px; }
.nt-tab-count { min-width: 22px; padding: 0 6px; border-radius: 999px; background: color-mix(in srgb, var(--ui-text) 10%, transparent); font-size: 12px; font-variant-numeric: tabular-nums; }
.nt-panel { border: 1px solid var(--ui-border); border-radius: 16px; background: var(--ui-surface, transparent); overflow: hidden; }
.nt-empty { margin: 0; padding: 32px 16px; text-align: center; color: var(--ui-muted); font-size: 14px; }
.nt-list { margin: 0; padding: 0; list-style: none; }
.nt-item { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 16px; border-bottom: 1px solid var(--ui-border); }
.nt-item:last-child { border-bottom: 0; }
.nt-main { display: flex; flex-direction: column; gap: 3px; min-width: 0; flex: 1 1 260px; }
.nt-name { font-size: 15px; font-weight: 700; overflow-wrap: anywhere; }
.nt-reason { font-size: 14px; line-height: 1.5; }
.nt-meta { display: flex; flex-wrap: wrap; gap: 6px; font-size: 12px; color: var(--ui-muted); }
.nt-actions { display: flex; flex-wrap: wrap; gap: 8px; }
.nt-btn {
    display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: 0 14px; border-radius: 10px;
    border: 1px solid var(--ui-border); background: transparent; color: var(--ui-text); font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none;
}
.nt-btn:hover { background: color-mix(in srgb, var(--ui-text) 8%, transparent); }
.nt-btn:focus-visible { outline: 3px solid var(--ui-secondary); outline-offset: 2px; }
.nt-btn:disabled { opacity: .5; cursor: progress; }
/* The same filled style as the other main actions in the dashboard. */
.nt-btn--primary { border-color: transparent; background: var(--ui-secondary); color: #fff; }
.nt-btn--primary:hover { background: var(--ui-secondary); filter: brightness(1.08); }
@media (max-width: 640px) {
    .nt-actions { width: 100%; }
    .nt-actions > * { flex: 1; }
}
</style>
