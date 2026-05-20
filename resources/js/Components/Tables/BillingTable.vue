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
            <div class="overflow-hidden rounded-[1.25rem] border border-slate-200 bg-white shadow-sm shadow-slate-200/60">
                <div class="hidden overflow-x-auto lg:block">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-5 py-4 text-start text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ $t('Date') }}</th>
                                <th class="px-5 py-4 text-start text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ $t('Organization') }}</th>
                                <th class="px-5 py-4 text-start text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ $t('Description') }}</th>
                                <th class="px-5 py-4 text-start text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ $t('Amount') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="item in normalizedRows" :key="`${item.uuid ?? item.created_at}-${item.description}-${item.amount}`" class="transition hover:bg-slate-50/80">
                                <td class="px-5 py-4 align-top text-sm font-medium text-slate-700">{{ item.created_at }}</td>
                                <td class="px-5 py-4 align-top text-sm font-semibold text-slate-900">{{ item.organization?.name ?? '—' }}</td>
                                <td class="px-5 py-4 align-top text-sm text-slate-600">{{ item.description ?? '—' }}</td>
                                <td class="px-5 py-4 align-top text-sm font-semibold text-slate-900">{{ item.amount }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="divide-y divide-slate-100 lg:hidden">
                    <article
                        v-for="item in normalizedRows"
                        :key="`${item.uuid ?? item.created_at}-${item.description}-${item.amount}-mobile`"
                        class="space-y-4 px-4 py-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-900">{{ item.organization?.name ?? '—' }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ item.created_at }}</p>
                            </div>
                            <span class="rounded-full bg-primary/8 px-3 py-1 text-xs font-semibold text-primary">{{ item.amount }}</span>
                        </div>
                        <div class="rounded-[1rem] bg-slate-50 px-3 py-3">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">{{ $t('Description') }}</p>
                            <p class="mt-1 text-sm text-slate-700">{{ item.description ?? '—' }}</p>
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
