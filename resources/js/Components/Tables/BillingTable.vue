<script setup>
import { computed } from 'vue';
import Pagination from '@/Components/Pagination.vue';
import UiEmptyState from '@/Components/UI/UiEmptyState.vue';

const props = defineProps({
    rows: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    uuid: {
        type: String,
        default: null,
    },
});

const normalizedRows = computed(() => Array.isArray(props.rows?.data) ? props.rows.data : []);
</script>

<template>
    <div class="space-y-4">
        <template v-if="normalizedRows.length">
            <div class="ui-table-shell">
                <div class="hidden overflow-x-auto lg:block">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th class="ui-table-th ui-table-th-first">{{ $t('Date') }}</th>
                                <th class="ui-table-th">{{ $t('Organization') }}</th>
                                <th class="ui-table-th">{{ $t('Description') }}</th>
                                <th class="ui-table-th ui-table-th-last">{{ $t('Amount') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in normalizedRows" :key="`${item.uuid ?? item.created_at}-${item.description}-${item.amount}`">
                                <td class="ui-table-td ui-table-td-first text-[var(--ui-muted)]">{{ item.created_at }}</td>
                                <td class="ui-table-td font-bold text-[var(--ui-text)]">{{ item.organization?.name ?? '—' }}</td>
                                <td class="ui-table-td text-[var(--ui-text)]">{{ item.description ?? '—' }}</td>
                                <td class="ui-table-td ui-table-td-last font-bold text-[var(--ui-text)]">{{ item.amount }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="billing-mobile-list lg:hidden">
                    <article
                        v-for="item in normalizedRows"
                        :key="`${item.uuid ?? item.created_at}-${item.description}-${item.amount}-mobile`"
                        class="billing-mobile-card"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-bold text-[15px] text-[var(--ui-text)]">{{ item.organization?.name ?? '—' }}</p>
                                <p class="mt-1 text-xs text-[var(--ui-muted)]">{{ item.created_at }}</p>
                            </div>
                            <span class="billing-amount-chip">{{ item.amount }}</span>
                        </div>
                        <div class="billing-mobile-field mt-3">
                            <p class="billing-mobile-field-label">{{ $t('Description') }}</p>
                            <p class="billing-mobile-field-value">{{ item.description ?? '—' }}</p>
                        </div>
                    </article>
                </div>
            </div>
        </template>

        <UiEmptyState
            v-else
            :title="$t('No billing activity yet')"
            :description="$t('Credits, debits, and payment entries will appear here once the billing owner records activity.')"
        >
            <template #icon>
                <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24"><path fill="currentColor" d="M13 3l5 5l-5 5V9H6V7h7zm-2 18l-5-5l5-5v4h7v2h-7z"/></svg>
            </template>
        </UiEmptyState>

        <Pagination v-if="rows?.meta" :pagination="rows.meta" />
    </div>
</template>

<style scoped>
.billing-mobile-card {
    padding: 1.1rem;
    border-top: 1px solid var(--ui-border);
}

.billing-mobile-card:first-child {
    border-top: none;
}

.billing-mobile-field {
    border-radius: var(--ui-radius-sm);
    background: var(--ui-surface-soft);
    padding: 0.6rem 0.75rem;
}

.billing-mobile-field-label {
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: var(--ui-muted);
}

.billing-mobile-field-value {
    margin-top: 0.25rem;
    font-size: 0.88rem;
    color: var(--ui-text);
}

.billing-amount-chip {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.3rem 0.8rem;
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--ui-secondary);
    background: color-mix(in srgb, var(--ui-secondary) 12%, var(--ui-surface));
}
</style>
