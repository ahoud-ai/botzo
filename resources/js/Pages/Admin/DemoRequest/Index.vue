<template>
    <AppLayout>
        <div class="dmr-page ui-page ui-fade-up text-[var(--ui-text)]">
            <div class="dmr-page__inner">
                <UiPageHeader :title="$t('Demo requests')" :subtitle="$t('Requests from the website form and from Mochi. Reach each person on WhatsApp and keep the status up to date.')" />

                <div class="grid gap-4 grid-cols-2 xl:grid-cols-4 dmr-kpi-row">
                    <UiStatCard class="ui-kpi--compact dmr-kpi dmr-kpi--muted" :title="$t('Total requests')" :value="summary?.total ?? 0" />
                    <UiStatCard class="ui-kpi--compact dmr-kpi dmr-kpi--warning" :title="$t('New')" :value="summary?.new ?? 0" />
                    <UiStatCard class="ui-kpi--compact dmr-kpi dmr-kpi--secondary" :title="$t('Contacted')" :value="summary?.contacted ?? 0" />
                    <UiStatCard class="ui-kpi--compact dmr-kpi dmr-kpi--success" :title="$t('Done')" :value="summary?.done ?? 0" />
                </div>

                <div class="dmr-toolbar">
                    <label class="dmr-search">
                        <span class="sr-only">{{ $t('Search') }}</span>
                        <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                        <input v-model="params.search" type="search" class="dmr-search-input" :placeholder="$t('Search by name, phone, or email…')" @input="search">
                    </label>

                    <div class="dmr-pill-group" role="group" :aria-label="$t('Status')">
                        <button
                            v-for="option in statusOptions"
                            :key="option.value"
                            type="button"
                            class="dmr-pill"
                            :class="{ 'dmr-pill--active': params.status === option.value }"
                            :aria-pressed="params.status === option.value ? 'true' : 'false'"
                            :style="{ '--tone': `var(${option.tone})` }"
                            @click="applyStatus(option.value)"
                        >
                            <span v-if="option.value !== 'all'" class="dmr-pill-dot" aria-hidden="true"></span>
                            {{ option.label }}
                        </button>
                    </div>
                </div>

                <div v-if="rows.data.length" class="dmr-table-shell">
                    <table class="dmr-table">
                        <thead>
                            <tr>
                                <th>{{ $t('Name') }}</th>
                                <th>{{ $t('WhatsApp') }}</th>
                                <th>{{ $t('Best time') }}</th>
                                <th>{{ $t('Source') }}</th>
                                <th>{{ $t('Date') }}</th>
                                <th>{{ $t('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in rows.data" :key="item.id" class="dmr-row">
                                <td>
                                    <div class="dmr-name">{{ item.full_name }}</div>
                                    <div v-if="item.business_activity" class="dmr-sub">{{ item.business_activity }}</div>
                                    <div v-if="item.email" class="dmr-sub" dir="ltr">{{ item.email }}</div>
                                    <div v-if="item.notes" class="dmr-note">{{ item.notes }}</div>
                                </td>
                                <td>
                                    <div class="dmr-phone" dir="ltr">{{ item.phone }}</div>
                                    <a v-if="item.whatsapp_url" :href="item.whatsapp_url" target="_blank" rel="noopener" class="dmr-wa">
                                        <svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2m5.4 14.1c-.2.6-1.3 1.2-1.8 1.2s-1 .3-3.4-.7a11.7 11.7 0 0 1-4.6-4.1c-.4-.5-1-1.7-1-2.9s.6-1.8.9-2.1.6-.3.8-.3h.6c.2 0 .4 0 .6.5l.9 2.1c.1.2.1.4 0 .6l-.3.5-.4.5c-.1.1-.3.3-.1.6a8.6 8.6 0 0 0 1.6 2 7.8 7.8 0 0 0 2.3 1.4c.3.1.5.1.6-.1l.9-1.1c.2-.3.4-.2.6-.1l2 .9c.3.1.5.2.5.3s.1.6-.1 1.2"/></svg>
                                        {{ $t('Contact on WhatsApp') }}
                                    </a>
                                </td>
                                <td>{{ timeLabel(item.preferred_time) }}</td>
                                <td>
                                    <span class="dmr-source" :class="item.source === 'mochi' ? 'dmr-source--mochi' : ''">{{ item.source === 'mochi' ? $t('Mochi') : $t('Website form') }}</span>
                                </td>
                                <td class="dmr-date">{{ formatDate(item.created_at) }}</td>
                                <td>
                                    <select
                                        class="dmr-status"
                                        :value="item.status"
                                        :aria-label="`${$t('Status')}: ${item.full_name}`"
                                        :style="{ '--tone': `var(${statusTone(item.status)})` }"
                                        :disabled="savingId === item.id"
                                        @change="updateStatus(item, $event.target.value)"
                                    >
                                        <option v-for="option in statusOptions.filter((o) => o.value !== 'all')" :key="option.value" :value="option.value">{{ option.label }}</option>
                                    </select>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <UiEmptyState v-else :title="$t('No demo requests yet')" :description="$t('Requests from the website form and from Mochi will appear here.')" />

                <Pagination :pagination="rows" />
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
    import AppLayout from "../Layout/App.vue";
    import UiPageHeader from "@/Components/UI/UiPageHeader.vue";
    import UiStatCard from "@/Components/UI/UiStatCard.vue";
    import UiEmptyState from "@/Components/UI/UiEmptyState.vue";
    import Pagination from "@/Components/Pagination.vue";
    import { router } from "@inertiajs/vue3";
    import { computed, ref } from "vue";
    import { useI18n } from "vue-i18n";
    import debounce from "lodash/debounce";

    const props = defineProps({
        title: { type: String },
        rows: { type: Object, required: true },
        filters: { type: Object, default: () => ({}) },
        summary: { type: Object, default: () => ({}) },
    });

    const { t, locale } = useI18n();

    const params = ref({
        search: props.filters?.search ?? '',
        status: props.filters?.status ?? 'all',
    });
    const savingId = ref(null);

    const statusOptions = computed(() => ([
        { value: 'all', label: t('All statuses'), tone: '--ui-secondary' },
        { value: 'new', label: t('New'), tone: '--ui-warning' },
        { value: 'contacted', label: t('Contacted'), tone: '--ui-secondary' },
        { value: 'done', label: t('Done'), tone: '--ui-success' },
    ]));

    const statusTone = (status) => statusOptions.value.find((option) => option.value === status)?.tone ?? '--ui-muted';

    const timeLabel = (value) => ({ morning: t('Morning'), evening: t('Evening'), any: t('Any time') })[value] ?? '—';

    const formatDate = (value) => {
        if (!value) {
            return '';
        }
        return new Intl.DateTimeFormat(locale.value === 'ar' ? 'ar-SA' : 'en-GB', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
    };

    const visit = () => {
        const data = Object.fromEntries(Object.entries(params.value).filter(([, value]) => value !== null && value !== '' && value !== 'all'));
        router.visit('/admin/demo-requests', { method: 'get', data, preserveState: true, preserveScroll: true });
    };

    const search = debounce(visit, 500);

    const applyStatus = (value) => {
        params.value.status = value;
        visit();
    };

    const updateStatus = (item, status) => {
        savingId.value = item.id;
        router.post(`/admin/demo-requests/${item.id}/status`, { status }, {
            preserveScroll: true,
            onFinish: () => {
                savingId.value = null;
            },
        });
    };
</script>

<style scoped>
.dmr-page {
    min-height: 100%;
    padding: 1.5rem 2rem;
}

.dmr-page__inner {
    width: 100%;
    max-width: 1680px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.dmr-kpi-row :deep(.dmr-kpi) {
    position: relative;
    overflow: hidden;
}

.dmr-kpi-row :deep(.dmr-kpi)::before {
    content: '';
    position: absolute;
    inset-inline: 0;
    top: 0;
    height: 3px;
    background: var(--dmr-kpi-tone);
}

.dmr-kpi-row :deep(.dmr-kpi--muted) { --dmr-kpi-tone: var(--ui-muted); }
.dmr-kpi-row :deep(.dmr-kpi--secondary) { --dmr-kpi-tone: var(--ui-secondary); }
.dmr-kpi-row :deep(.dmr-kpi--success) { --dmr-kpi-tone: var(--ui-success); }
.dmr-kpi-row :deep(.dmr-kpi--warning) { --dmr-kpi-tone: var(--ui-warning); }

.dmr-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.85rem;
}

.dmr-search {
    flex: 1;
    min-width: 15rem;
    display: flex;
    align-items: center;
    gap: 0.55rem;
    padding: 0.68rem 1rem;
    border: 1px solid var(--ui-border);
    border-radius: 0.8rem;
    background: var(--ui-surface);
    color: var(--ui-muted);
}

.dmr-search:focus-within {
    border-color: color-mix(in srgb, var(--ui-secondary) 32%, var(--ui-border));
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--ui-secondary) 10%, transparent);
}

.dmr-search-input {
    flex: 1;
    min-width: 0;
    border: none;
    background: transparent;
    outline: none;
    font-size: 0.9rem;
    color: var(--ui-text);
}

.dmr-pill-group {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.dmr-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    min-height: 2.25rem;
    padding: 0.45rem 0.9rem;
    border-radius: 999px;
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--ui-muted);
    background: var(--ui-surface);
    border: 1px solid var(--ui-border);
    cursor: pointer;
    transition: border-color 150ms ease, color 150ms ease;
}

.dmr-pill:hover {
    border-color: color-mix(in srgb, var(--tone) 34%, var(--ui-border));
    color: color-mix(in srgb, var(--tone) 70%, var(--ui-text));
}

.dmr-pill:focus-visible,
.dmr-status:focus-visible,
.dmr-wa:focus-visible {
    outline: 2px solid var(--ui-secondary);
    outline-offset: 2px;
}

.dmr-pill--active {
    color: #fff;
    border-color: transparent;
    background: linear-gradient(135deg, var(--ui-secondary), color-mix(in srgb, var(--ui-secondary) 78%, var(--ui-primary)));
}

.dmr-pill-dot {
    width: 6px;
    height: 6px;
    border-radius: 999px;
    background: var(--tone);
}

.dmr-pill--active .dmr-pill-dot {
    background: #fff;
}

.dmr-table-shell {
    border: 1px solid var(--ui-border);
    border-radius: var(--ui-radius-lg);
    box-shadow: var(--ui-shadow-1);
    overflow-x: auto;
    background: var(--ui-surface);
}

.dmr-table {
    width: 100%;
    min-width: 760px;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.dmr-table th {
    text-align: start;
    padding: 0.85rem 1.1rem;
    color: var(--ui-muted);
    font-weight: 800;
    font-size: 0.74rem;
    border-bottom: 1px solid var(--ui-border);
    background: var(--ui-surface-soft);
    white-space: nowrap;
}

.dmr-table td {
    padding: 0.95rem 1.1rem;
    border-bottom: 1px solid var(--ui-border);
    vertical-align: top;
}

.dmr-row:last-child td {
    border-bottom: none;
}

.dmr-row:hover {
    background: var(--ui-surface-soft);
}

.dmr-name {
    font-weight: 800;
}

.dmr-sub {
    margin-top: 0.15rem;
    font-size: 0.78rem;
    color: var(--ui-muted);
}

.dmr-note {
    margin-top: 0.35rem;
    max-width: 22rem;
    font-size: 0.78rem;
    color: var(--ui-text);
    overflow-wrap: anywhere;
}

.dmr-phone {
    font-weight: 700;
    white-space: nowrap;
    text-align: start;
}

.dmr-wa {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    margin-top: 0.4rem;
    min-height: 2rem;
    padding: 0.3rem 0.75rem;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 800;
    color: #0A0F1C;
    background: #25D366;
    text-decoration: none;
    white-space: nowrap;
}

.dmr-source {
    display: inline-flex;
    padding: 0.25rem 0.65rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--ui-muted);
    border: 1px solid var(--ui-border);
    white-space: nowrap;
}

.dmr-source--mochi {
    color: color-mix(in srgb, var(--ui-secondary) 85%, var(--ui-text));
    border-color: color-mix(in srgb, var(--ui-secondary) 40%, transparent);
    background: color-mix(in srgb, var(--ui-secondary) 12%, transparent);
}

.dmr-date {
    color: var(--ui-muted);
    white-space: nowrap;
}

.dmr-status {
    min-height: 2.25rem;
    padding-block: 0.3rem;
    padding-inline: 0.8rem 2rem;
    border-radius: 999px;
    font-size: 0.8rem;
    font-weight: 800;
    border: 1px solid color-mix(in srgb, var(--tone) 35%, transparent);
    background-color: color-mix(in srgb, var(--tone) 14%, var(--ui-surface));
    color: color-mix(in srgb, var(--tone) 82%, var(--ui-text));
    cursor: pointer;
}

@media (max-width: 640px) {
    .dmr-page {
        padding: 1rem;
    }
}
</style>
