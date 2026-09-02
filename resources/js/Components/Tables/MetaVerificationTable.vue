<script setup>
    import { Link, router } from "@inertiajs/vue3";
    import { computed, ref } from "vue";
    import { useI18n } from 'vue-i18n';
    import debounce from 'lodash/debounce';
    import UiEmptyState from '@/Components/UI/UiEmptyState.vue';
    import Pagination from '@/Components/Pagination.vue';

    const { t } = useI18n();

    const props = defineProps({
        rows: {
            type: Object,
            required: true,
        },
        filters: {
            type: Object,
            default: () => ({}),
        },
    });

    const params = ref({
        search: props.filters?.search ?? null,
        status: props.filters?.status ?? 'all',
    });

    const statusOptions = computed(() => ([
        { value: 'all', label: t('All statuses'), tone: '--ui-secondary' },
        { value: 'requested', label: t('requested'), tone: '--ui-muted' },
        { value: 'awaiting_agreement', label: t('awaiting_agreement'), tone: '--ui-accent' },
        { value: 'awaiting_payment', label: t('awaiting_payment'), tone: '--ui-warning' },
        { value: 'under_review', label: t('under_review'), tone: '--ui-primary' },
        { value: 'submitted_to_meta', label: t('submitted_to_meta'), tone: '--ui-secondary' },
        { value: 'meta_reviewing', label: t('meta_reviewing'), tone: 'mix' },
        { value: 'approved', label: t('approved'), tone: '--ui-success' },
        { value: 'rejected', label: t('rejected'), tone: '--ui-danger' },
    ]));

    const toneStyle = (tone) => {
        if (tone === 'mix') {
            return { '--tone': 'color-mix(in srgb, var(--ui-primary) 50%, var(--ui-secondary) 50%)' };
        }
        return { '--tone': `var(${tone})` };
    };

    const statusTone = (status) => {
        const option = statusOptions.value.find((item) => item.value === status);
        return toneStyle(option?.tone ?? '--ui-muted');
    };

    const contactInitial = (item) => (item.full_name ?? '').trim().charAt(0).toUpperCase() || '#';

    const isSearching = ref(false);

    const runSearch = () => {
        const filteredParams = Object.fromEntries(
            Object.entries(params.value).filter(([, value]) => value !== null && value !== 'all' && value !== '')
        );

        router.visit('/admin/meta-verifications', {
            method: 'get',
            data: filteredParams,
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                isSearching.value = false;
            },
        });
    };

    const search = debounce(() => {
        isSearching.value = true;
        runSearch();
    }, 600);

    const applyStatus = (value) => {
        params.value.status = value;
        runSearch();
    };
</script>

<template>
    <div class="mvr-toolbar">
        <div class="mvr-search">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
            <input v-model="params.search" @input="search" type="text" class="mvr-search-input" :placeholder="$t('Search by name, phone, or email…')">
        </div>

        <div class="mvr-pill-group">
            <button
                v-for="option in statusOptions"
                :key="option.value"
                type="button"
                class="mvr-pill"
                :class="{ 'mvr-pill--active': params.status === option.value }"
                :style="toneStyle(option.tone)"
                @click="applyStatus(option.value)"
            >
                <span v-if="option.value !== 'all'" class="mvr-pill-dot"></span>
                {{ option.label }}
            </button>
        </div>
    </div>

    <div class="mvr-table-shell">
        <table class="mvr-table">
            <thead>
                <tr>
                    <th>{{ $t('Full name') }}</th>
                    <th>{{ $t('Business name') }}</th>
                    <th>{{ $t('Organization') }}</th>
                    <th>{{ $t('Status') }}</th>
                    <th>{{ $t('Date created') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="item in rows.data" :key="item.id" class="mvr-row">
                    <td>
                        <Link :href="`/admin/meta-verifications/${item.id}`" class="mvr-contact">
                            <span class="mvr-contact-avatar" :style="statusTone(item.status)">{{ contactInitial(item) }}</span>
                            <span class="mvr-contact-text">
                                <span class="mvr-contact-name">{{ item.full_name }}</span>
                                <span class="mvr-contact-sub">{{ item.phone }}</span>
                            </span>
                        </Link>
                    </td>
                    <td>
                        <Link :href="`/admin/meta-verifications/${item.id}`">{{ item.business_name }}</Link>
                    </td>
                    <td>
                        <Link :href="`/admin/meta-verifications/${item.id}`">
                            <span v-if="item.organization" class="mvr-org-badge">
                                <span class="mvr-org-dot"></span>{{ item.organization.name }}
                            </span>
                            <span v-else class="mvr-guest-badge">{{ $t('Guest (not registered)') }}</span>
                        </Link>
                    </td>
                    <td>
                        <Link :href="`/admin/meta-verifications/${item.id}`">
                            <span class="mvr-chip" :style="statusTone(item.status)">{{ $t(item.status) }}</span>
                        </Link>
                    </td>
                    <td>
                        <Link :href="`/admin/meta-verifications/${item.id}`" class="mvr-date">{{ item.created_at }}</Link>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <UiEmptyState v-if="rows.data.length === 0" :title="$t('No meta verification requests found')">
        <template #icon>
            <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 4 6v6c0 5 3.4 8.7 8 10 4.6-1.3 8-5 8-10V6z"/><path d="m9 12 2 2 4-4"/></svg>
        </template>
    </UiEmptyState>

    <Pagination :pagination="rows.meta" />
</template>

<style scoped>
.mvr-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.85rem;
    margin-bottom: 1.1rem;
}

.mvr-search {
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
    transition: border-color 160ms ease, box-shadow 160ms ease;
}

.mvr-search:focus-within {
    border-color: color-mix(in srgb, var(--ui-secondary) 32%, var(--ui-border));
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--ui-secondary) 10%, transparent);
}

.mvr-search-input {
    flex: 1;
    border: none;
    background: transparent;
    outline: none;
    font-size: 0.85rem;
    color: var(--ui-text);
}

.mvr-pill-group {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.mvr-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.5rem 0.9rem;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--ui-muted);
    background: var(--ui-surface);
    border: 1px solid var(--ui-border);
    transition: transform 150ms ease, border-color 150ms ease, color 150ms ease;
}

.mvr-pill:hover {
    transform: translateY(-1px);
    border-color: color-mix(in srgb, var(--tone) 34%, var(--ui-border));
    color: color-mix(in srgb, var(--tone) 70%, var(--ui-text));
}

.mvr-pill--active {
    color: #fff;
    border-color: transparent;
    background: linear-gradient(135deg, var(--ui-secondary), color-mix(in srgb, var(--ui-secondary) 78%, var(--ui-primary)));
    box-shadow: 0 10px 20px -12px color-mix(in srgb, var(--ui-secondary) 60%, rgba(15, 23, 42, 0.5));
}

.mvr-pill-dot {
    width: 6px;
    height: 6px;
    border-radius: 999px;
    background: var(--tone);
    flex-shrink: 0;
}

.mvr-pill--active .mvr-pill-dot {
    background: #fff;
}

.mvr-table-shell {
    border: 1px solid var(--ui-border);
    border-radius: var(--ui-radius-lg);
    box-shadow: var(--ui-shadow-1);
    overflow: hidden;
    background: var(--ui-surface);
}

.mvr-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
}

.mvr-table th {
    text-align: start;
    padding: 0.85rem 1.1rem;
    color: var(--ui-muted);
    font-weight: 800;
    font-size: 0.72rem;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    border-bottom: 1px solid var(--ui-border);
    background: var(--ui-surface-soft);
}

.mvr-table td {
    padding: 1rem 1.1rem;
    border-bottom: 1px solid var(--ui-border);
    vertical-align: middle;
}

.mvr-row:last-child td {
    border-bottom: none;
}

.mvr-row {
    transition: background-color 150ms ease;
}

.mvr-row:hover {
    background: var(--ui-surface-soft);
}

.mvr-table a {
    color: inherit;
    text-decoration: none;
    display: block;
}

a.mvr-contact {
    display: flex;
    align-items: center;
    gap: 0.7rem;
}

.mvr-contact-avatar {
    display: inline-flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    width: 2.35rem;
    height: 2.35rem;
    border-radius: 0.75rem;
    font-size: 0.85rem;
    font-weight: 800;
    background: color-mix(in srgb, var(--tone) 16%, var(--ui-surface));
    color: color-mix(in srgb, var(--tone) 78%, var(--ui-text));
    border: 1px solid color-mix(in srgb, var(--tone) 28%, transparent);
}

.mvr-contact-text {
    display: flex;
    min-width: 0;
    flex-direction: column;
}

.mvr-contact-name {
    font-weight: 800;
    color: var(--ui-text);
}

.mvr-contact-sub {
    font-size: 0.75rem;
    color: var(--ui-muted);
    margin-top: 0.1rem;
}

.mvr-org-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--ui-text);
}

.mvr-org-dot {
    width: 6px;
    height: 6px;
    border-radius: 999px;
    background: var(--ui-secondary);
    flex-shrink: 0;
}

.mvr-guest-badge {
    display: inline-flex;
    align-items: center;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--ui-muted);
    padding: 0.25rem 0.65rem;
    border: 1px dashed var(--ui-border-strong);
    border-radius: 999px;
}

.mvr-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.8rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 800;
    border: 1px solid color-mix(in srgb, var(--tone) 28%, transparent);
    background: color-mix(in srgb, var(--tone) 15%, transparent);
    color: color-mix(in srgb, var(--tone) 82%, var(--ui-text));
    white-space: nowrap;
}

.mvr-date {
    color: var(--ui-muted);
}
</style>
