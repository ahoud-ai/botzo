<script setup>
    import { computed, reactive, ref } from 'vue';
    import debounce from 'lodash/debounce';
    import { router, useForm } from '@inertiajs/vue3';
    import { useI18n } from 'vue-i18n';
    import FormTextArea from '@/Components/FormTextArea.vue';
    import UiEmptyState from '@/Components/UI/UiEmptyState.vue';

    const { t } = useI18n();

    const props = defineProps({
        rows: {
            type: Array,
            required: true,
        },
        filters: {
            type: Object
        },
        language: {
            type: Object
        }
    });

    const selectedString = ref(null);

    const params = ref({
        search: props.filters.search,
    });

    const isSearching = ref(false);

    const clearSearch = () => {
        params.value.search = null;
        runSearch();
    }

    const search = debounce(() => {
        isSearching.value = true;
        runSearch();
    }, 1000);

    const runSearch = () => {
        const url = window.location.pathname;

        router.visit(url, {
            method: 'get',
            data: params.value,
            preserveState: true,
            onFinish: () => {
                isSearching.value = false;
            },
        })
    }

    const form2 = useForm({
        'translation': null
    });

    const openTextArea = (key, translation) => {
        selectedString.value = key;
        form2.translation = translation;
    }

    const cancelEdit = () => {
        selectedString.value = null;
    }

    const submitForm = async () => {
        form2.post('/admin/translations/'+ props.language.code +'/' + selectedString.value, {
            preserveScroll: true,
            onFinish: () => {
                selectedString.value = null;
            }
        })
    };

    const isUntranslated = (item) => item.Translation === item.Key || !String(item.Translation || '').trim();

    // Best-effort grouping based on keyword matches in the key text — the translation
    // files carry no real section/module metadata, so this is a practical approximation
    // to avoid one flat list of thousands of rows, not an exact taxonomy.
    const CATEGORIES = [
        { id: 'email', label: 'Email & notifications', keywords: ['email', 'mail', 'smtp', 'notification', 'inbox', 'subject line'] },
        { id: 'billing', label: 'Billing & payments', keywords: ['invoice', 'payment', 'billing', 'coupon', 'tax', 'subscription', 'plan', 'checkout', 'moyasar', 'gateway', 'price', 'refund'] },
        { id: 'store', label: 'Store & products', keywords: ['product', 'store', 'shop', 'catalog', 'inventory', 'cart', 'order'] },
        { id: 'users', label: 'Users & team', keywords: ['user', 'team', 'member', 'role', 'permission', 'employee', 'staff', 'admin', 'agent', 'profile', 'account', 'login', 'password'] },
        { id: 'organizations', label: 'Organizations', keywords: ['organization', 'company', 'branch', 'workspace', 'tenant'] },
        { id: 'messaging', label: 'WhatsApp & messaging', keywords: ['whatsapp', 'message', 'chat', 'contact', 'campaign', 'broadcast', 'phone number'] },
        { id: 'support', label: 'Tickets & support', keywords: ['ticket', 'support', 'help desk', 'reply'] },
        { id: 'automation', label: 'Automation & flows', keywords: ['flow', 'automation', 'trigger', 'builder', 'node'] },
        { id: 'reports', label: 'Reports & analytics', keywords: ['report', 'analytic', 'dashboard', 'chart', 'stat', 'metric'] },
        { id: 'settings', label: 'Settings & configuration', keywords: ['setting', 'config', 'timezone', 'language', 'translation', 'storage', 'api', 'webhook', 'integration'] },
    ];

    const categoryFor = (key) => {
        const lower = key.toLowerCase();
        for (const category of CATEGORIES) {
            if (category.keywords.some((word) => lower.includes(word))) {
                return category;
            }
        }
        return { id: 'general', label: 'General' };
    };

    const groups = computed(() => {
        const buckets = new Map();

        for (const item of props.rows) {
            const category = categoryFor(item.Key);
            if (!buckets.has(category.id)) {
                buckets.set(category.id, { id: category.id, label: category.label, items: [] });
            }
            buckets.get(category.id).items.push(item);
        }

        return Array.from(buckets.values())
            .map((group) => ({
                ...group,
                untranslatedCount: group.items.filter(isUntranslated).length,
            }))
            .sort((a, b) => b.items.length - a.items.length);
    });

    const stats = computed(() => {
        const total = props.rows.length;
        const untranslated = props.rows.filter(isUntranslated).length;
        const translated = total - untranslated;
        const percent = total > 0 ? Math.round((translated / total) * 100) : 0;

        return { total, translated, untranslated, percent };
    });

    const expandedGroups = reactive({});
    const toggleGroup = (id) => {
        expandedGroups[id] = !expandedGroups[id];
    }

    const targetDir = computed(() => (props.language?.is_rtl ? 'rtl' : 'ltr'));
</script>
<template>
    <div class="lgt-toolbar">
        <div class="ui-table-search lgt-search">
            <span class="ui-table-search-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 15l6 6m-11-4a7 7 0 1 1 0-14a7 7 0 0 1 0 14Z"/></svg>
            </span>
            <input @input="search" v-model="params.search" type="text" class="ui-table-search-input" :placeholder="$t('Search translation string')">
            <button v-if="isSearching === false && params.search" @click="clearSearch" type="button" class="ui-table-search-action">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10s10-4.5 10-10S17.5 2 12 2zm3.7 12.3c.4.4.4 1 0 1.4c-.4.4-1 .4-1.4 0L12 13.4l-2.3 2.3c-.4.4-1 .4-1.4 0c-.4-.4-.4-1 0-1.4l2.3-2.3l-2.3-2.3c-.4-.4-.4-1 0-1.4c.4-.4 1-.4 1.4 0l2.3 2.3l2.3-2.3c.4-.4 1-.4 1.4 0c.4.4.4 1 0 1.4L13.4 12l2.3 2.3z"/></svg>
            </button>
            <span v-if="isSearching" class="ui-table-search-action">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><circle cx="12" cy="3.5" r="1.5" fill="currentColor" opacity="0"><animateTransform attributeName="transform" calcMode="discrete" dur="2.4s" repeatCount="indefinite" type="rotate" values="0 12 12;90 12 12;180 12 12;270 12 12"/><animate attributeName="opacity" dur="0.6s" keyTimes="0;0.5;1" repeatCount="indefinite" values="1;1;0"/></circle><circle cx="12" cy="3.5" r="1.5" fill="currentColor" opacity="0"><animateTransform attributeName="transform" begin="0.2s" calcMode="discrete" dur="2.4s" repeatCount="indefinite" type="rotate" values="30 12 12;120 12 12;210 12 12;300 12 12"/><animate attributeName="opacity" begin="0.2s" dur="0.6s" keyTimes="0;0.5;1" repeatCount="indefinite" values="1;1;0"/></circle><circle cx="12" cy="3.5" r="1.5" fill="currentColor" opacity="0"><animateTransform attributeName="transform" begin="0.4s" calcMode="discrete" dur="2.4s" repeatCount="indefinite" type="rotate" values="60 12 12;150 12 12;240 12 12;330 12 12"/><animate attributeName="opacity" begin="0.4s" dur="0.6s" keyTimes="0;0.5;1" repeatCount="indefinite" values="1;1;0"/></circle></svg>
            </span>
        </div>
    </div>

    <div v-if="stats.total > 0" class="lgt-stats">
        <div class="lgt-stats-head">
            <span class="lgt-stats-label">{{ $t('Translation progress') }}</span>
            <span class="lgt-stats-value">{{ stats.translated }}/{{ stats.total }} ({{ stats.percent }}%)</span>
        </div>
        <div class="lgt-progress-track">
            <div class="lgt-progress-fill" :style="{ width: stats.percent + '%' }"></div>
        </div>
        <p v-if="stats.untranslated > 0" class="lgt-stats-note">{{ stats.untranslated }} {{ $t('strings still need translation') }}</p>
    </div>

    <div v-if="isSearching" class="lgt-skeleton" aria-hidden="true">
        <div v-for="n in 5" :key="n" class="lgt-skeleton-row"></div>
    </div>

    <template v-else-if="props.rows.length">
        <div class="lgt-groups">
            <section v-for="group in groups" :key="group.id" class="lgt-group">
                <button type="button" class="lgt-group-head" @click="toggleGroup(group.id)" :aria-expanded="Boolean(expandedGroups[group.id])">
                    <span class="lgt-group-title">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" class="lgt-group-chevron" :class="{ 'lgt-group-chevron--open': expandedGroups[group.id] }"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 6l6 6l-6 6"/></svg>
                        {{ $t(group.label) }}
                    </span>
                    <span class="lgt-group-meta">
                        <span v-if="group.untranslatedCount > 0" class="lgt-group-badge">{{ group.untranslatedCount }} {{ $t('untranslated') }}</span>
                        <span class="lgt-group-count">{{ group.items.length }}</span>
                    </span>
                </button>

                <div v-if="expandedGroups[group.id]" class="lgt-rows">
                    <div class="lgt-row lgt-row--head">
                        <div class="lgt-col-key">{{ $t('Original') }}</div>
                        <div class="lgt-col-value">{{ $t('Translation') }} ({{ props.language?.name }})</div>
                    </div>
                    <div v-for="item in group.items" :key="item.Key" class="lgt-row" :class="{ 'lgt-row--untranslated': isUntranslated(item) }">
                        <div class="lgt-col-key">
                            <span class="lgt-key-text">{{ item.Key }}</span>
                            <span v-if="isUntranslated(item)" class="lgt-untranslated-chip">{{ $t('Not translated yet') }}</span>
                        </div>
                        <div class="lgt-col-value">
                            <template v-if="selectedString === item.Key">
                                <FormTextArea v-model="form2.translation" :name="''" :showLabel="false" :class="'w-full'" :dir="targetDir"/>
                                <div class="lgt-edit-actions">
                                    <button type="button" class="lgt-icon-btn lgt-icon-btn--confirm" :title="$t('Save')" @click="submitForm()">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"><path d="M14.25 8.75c-.5 2.5-2.385 4.854-5.03 5.38A6.25 6.25 0 0 1 3.373 3.798C5.187 1.8 8.25 1.25 10.75 2.25"/><path d="m5.75 7.75l2.5 2.5l6-6.5"/></g></svg>
                                    </button>
                                    <button type="button" class="lgt-icon-btn lgt-icon-btn--cancel" :title="$t('Cancel')" @click="cancelEdit()">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 6l12 12M18 6L6 18"/></svg>
                                    </button>
                                </div>
                            </template>
                            <template v-else>
                                <span class="lgt-value-text" :dir="targetDir" @click="openTextArea(item.Key, item.Translation)">{{ item.Translation || $t('No preview content') }}</span>
                                <button type="button" class="lgt-icon-btn" :title="$t('Edit')" @click="openTextArea(item.Key, item.Translation)">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"><path d="M9.533 11.15A1.823 1.823 0 0 0 9 12.438V15h2.578c.483 0 .947-.192 1.289-.534l7.6-7.604a1.822 1.822 0 0 0 0-2.577l-.751-.751a1.822 1.822 0 0 0-2.578 0z"/><path d="M21 12c0 4.243 0 6.364-1.318 7.682C18.364 21 16.242 21 12 21c-4.243 0-6.364 0-7.682-1.318C3 18.364 3 16.242 3 12c0-4.243 0-6.364 1.318-7.682C5.636 3 7.758 3 12 3"/></g></svg>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </template>

    <UiEmptyState
        v-else
        :title="$t('No translation strings found')"
    >
        <template #icon>
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M4 5h7m0 0h4m-4 0v2m0 0c0 4-2.5 7-6 8.5M11 7c1.2 3.5 3.5 6 6.5 7.5M4 19l4-9l4 9m-6.5-3h5"/></svg>
        </template>
    </UiEmptyState>
</template>

<style scoped>
.lgt-toolbar {
    margin-bottom: 1rem;
}

.lgt-search {
    max-width: 26rem;
}

.lgt-stats {
    margin-bottom: 1.25rem;
    border-radius: 1rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    padding: 1rem 1.15rem;
}

.lgt-stats-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.5rem;
}

.lgt-stats-label {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--ui-text);
}

.lgt-stats-value {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--ui-muted);
}

.lgt-progress-track {
    height: 0.5rem;
    border-radius: 999px;
    background: var(--ui-border);
    overflow: hidden;
}

.lgt-progress-fill {
    height: 100%;
    border-radius: 999px;
    background: var(--ui-secondary);
    transition: width 300ms ease;
}

.lgt-stats-note {
    margin-top: 0.5rem;
    font-size: 0.76rem;
    color: var(--ui-warning);
}

.lgt-groups {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.lgt-group {
    border: 1px solid var(--ui-border);
    border-radius: 1rem;
    background: var(--ui-surface);
    overflow: hidden;
}

.lgt-group-head {
    display: flex;
    width: 100%;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.9rem 1.1rem;
    background: var(--ui-surface-soft);
    transition: background-color 160ms ease;
}

.lgt-group-head:hover {
    background: color-mix(in srgb, var(--ui-secondary) 6%, var(--ui-surface-soft));
}

.lgt-group-title {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.88rem;
    font-weight: 700;
    color: var(--ui-text);
}

.lgt-group-chevron {
    color: var(--ui-muted);
    transition: transform 160ms ease;
}

.lgt-group-chevron--open {
    transform: rotate(90deg);
}

.lgt-group-meta {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.lgt-group-badge {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.15rem 0.55rem;
    font-size: 0.68rem;
    font-weight: 700;
    background: color-mix(in srgb, var(--ui-warning) 16%, transparent);
    color: var(--ui-warning);
}

.lgt-group-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.6rem;
    border-radius: 999px;
    background: var(--ui-border);
    padding: 0.1rem 0.5rem;
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--ui-muted);
}

.lgt-rows {
    border-top: 1px solid var(--ui-border);
}

.lgt-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    padding: 0.75rem 1.1rem;
    border-top: 1px solid var(--ui-border);
}

.lgt-row:first-child {
    border-top: none;
}

.lgt-row--head {
    background: var(--ui-surface-soft);
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: var(--ui-muted);
}

.lgt-row--untranslated {
    background: color-mix(in srgb, var(--ui-warning) 6%, transparent);
}

.lgt-col-key {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    min-width: 0;
}

.lgt-key-text {
    overflow-wrap: break-word;
    font-size: 0.83rem;
    color: var(--ui-text);
}

.lgt-untranslated-chip {
    display: inline-flex;
    align-self: flex-start;
    align-items: center;
    border-radius: 999px;
    padding: 0.1rem 0.5rem;
    font-size: 0.66rem;
    font-weight: 700;
    background: color-mix(in srgb, var(--ui-warning) 18%, transparent);
    color: var(--ui-warning);
}

.lgt-col-value {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    min-width: 0;
}

.lgt-value-text {
    flex: 1;
    min-width: 0;
    overflow-wrap: break-word;
    cursor: pointer;
    border-radius: 0.5rem;
    padding: 0.2rem 0.4rem;
    font-size: 0.83rem;
    color: var(--ui-text);
    transition: background-color 160ms ease;
}

.lgt-value-text:hover {
    background: var(--ui-surface-soft);
}

.lgt-edit-actions {
    display: flex;
    flex-shrink: 0;
    gap: 0.3rem;
}

.lgt-icon-btn {
    display: inline-flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    height: 2rem;
    width: 2rem;
    border-radius: 999px;
    color: var(--ui-muted);
    transition: background-color 160ms ease, color 160ms ease;
}

.lgt-icon-btn:hover {
    background: var(--ui-surface-soft);
    color: var(--ui-text);
}

.lgt-icon-btn--confirm:hover {
    color: var(--ui-success);
}

.lgt-icon-btn--cancel:hover {
    color: var(--ui-danger);
}

.lgt-skeleton {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}

.lgt-skeleton-row {
    height: 2.6rem;
    border-radius: 0.75rem;
    background: var(--ui-surface-soft);
    animation: lgt-pulse 1.4s ease-in-out infinite;
}

@keyframes lgt-pulse {
    0%, 100% { opacity: 0.6; }
    50% { opacity: 1; }
}
</style>
