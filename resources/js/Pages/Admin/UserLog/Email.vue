<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame ui-text-main min-h-full space-y-6">
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-slate-950">{{ $t('Email logs') }}</h1>
                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ $t('Review delivery status, recipients, attempts, and recent email activity.') }}</p>
                </div>
            </div>

            <form class="rounded-[1.25rem] border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/60" @submit.prevent="applyFilters">
                <div class="flex flex-col gap-3 md:flex-row md:items-center">
                    <input
                        v-model="search"
                        type="text"
                        class="min-h-11 w-full rounded-xl border border-slate-200 px-4 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
                        :placeholder="$t('Search by recipient or subject')"
                    >
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-500">
                        {{ $t('Search') }}
                    </button>
                    <button type="button" class="inline-flex min-h-11 items-center justify-center rounded-md border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100" @click="resetFilters">
                        {{ $t('Reset') }}
                    </button>
                </div>
            </form>

            <section class="overflow-hidden rounded-[1.25rem] border border-slate-200 bg-white shadow-sm shadow-slate-200/60">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50/90">
                            <tr>
                                <th class="px-4 py-3 text-start text-[11px] font-semibold text-slate-500">{{ $t('Recipient') }}</th>
                                <th class="px-4 py-3 text-start text-[11px] font-semibold text-slate-500">{{ $t('Subject') }}</th>
                                <th class="px-4 py-3 text-start text-[11px] font-semibold text-slate-500">{{ $t('Status') }}</th>
                                <th class="px-4 py-3 text-start text-[11px] font-semibold text-slate-500">{{ $t('Attempts') }}</th>
                                <th class="px-4 py-3 text-start text-[11px] font-semibold text-slate-500">{{ $t('User') }}</th>
                                <th class="px-4 py-3 text-start text-[11px] font-semibold text-slate-500">{{ $t('Created') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            <tr v-for="row in tableRows" :key="row.uuid ?? row.id" class="align-top transition hover:bg-slate-50/70">
                                <td class="px-4 py-4 text-sm text-slate-700">{{ row.recipient }}</td>
                                <td class="px-4 py-4">
                                    <div class="max-w-xl text-sm font-medium text-slate-900">{{ row.subject }}</div>
                                    <div v-if="row.message" class="mt-1 max-w-xl truncate text-xs text-slate-500">{{ stripHtml(row.message) }}</div>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold" :class="statusClass(row.status)">
                                        {{ row.status ?? '-' }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-sm text-slate-700">{{ row.attempts ?? 0 }}</td>
                                <td class="px-4 py-4 text-sm text-slate-700">{{ userLabel(row.user) }}</td>
                                <td class="px-4 py-4 text-sm text-slate-600">{{ formatDate(row.created_at) }}</td>
                            </tr>
                            <tr v-if="tableRows.length === 0">
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">{{ $t('No email logs found.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination v-if="props.rows?.meta" class="border-t border-slate-200 p-4" :pagination="props.rows.meta" />
            </section>
        </div>
    </AppLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from './../Layout/App.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
    rows: {
        type: Object,
        default: () => ({ data: [] }),
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const search = ref(props.filters?.search ?? '');
const tableRows = computed(() => Array.isArray(props.rows?.data) ? props.rows.data : []);

const applyFilters = () => {
    router.get('/admin/user-logs/emails', search.value ? { search: search.value } : {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const resetFilters = () => {
    search.value = '';
    applyFilters();
};

const stripHtml = (value) => {
    return String(value ?? '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
};

const userLabel = (user) => {
    if (!user) {
        return '-';
    }

    return user.full_name || user.name || user.email || '-';
};

const statusClass = (status) => {
    if (status === 'sent') {
        return 'bg-emerald-50 text-emerald-700';
    }

    if (status === 'failed') {
        return 'bg-rose-50 text-rose-700';
    }

    return 'bg-amber-50 text-amber-700';
};

const formatDate = (value) => {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleString();
};
</script>
