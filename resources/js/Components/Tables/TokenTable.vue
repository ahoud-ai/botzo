<script setup>
import { computed, ref, toRefs } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AlertModal from '@/Components/AlertModal.vue';
import Pagination from '@/Components/Pagination.vue';
import { useAlertModal } from '@/Composables/useAlertModal';

const props = defineProps({
    rows: {
        type: Object,
        required: true,
    },
});

const { rows } = toRefs(props);

const { isOpenAlert, openAlert, confirmAlert } = useAlertModal();
const page = usePage();
const deleteForm = useForm({});
const rotateForm = useForm({});
const actionUuid = ref(null);

const canDelete = computed(() => {
    const permissions = page.props.permissions;

    if (!permissions || !Array.isArray(permissions)) {
        return false;
    }

    return permissions.includes('*') || permissions.includes('developer_tools.delete');
});

const canEdit = computed(() => {
    const permissions = page.props.permissions;

    if (!permissions || !Array.isArray(permissions)) {
        return false;
    }

    return permissions.includes('*') || permissions.includes('developer_tools.edit');
});

const hasRows = computed(() => Array.isArray(rows.value?.data) && rows.value.data.length > 0);

const deleteAction = (uuid) => {
    if (!canDelete.value) {
        return;
    }

    actionUuid.value = uuid;

    deleteForm.delete(`/developer-tools/access-tokens/${uuid}`, {
        preserveScroll: true,
        onFinish: () => {
            actionUuid.value = null;
        },
    });
};

const rotateToken = (uuid) => {
    if (!canEdit.value) {
        return;
    }

    actionUuid.value = uuid;

    rotateForm.post(`/developer-tools/access-tokens/${uuid}/rotate`, {
        preserveScroll: true,
        onFinish: () => {
            actionUuid.value = null;
        },
    });
};

const isBusy = (uuid) => actionUuid.value === uuid && (deleteForm.processing || rotateForm.processing);
</script>

<template>
    <div class="space-y-5">
        <div v-if="hasRows" class="grid gap-4">
            <article
                v-for="item in rows.data"
                :key="item.uuid"
                class="group relative overflow-hidden rounded-[28px] border border-slate-200/80 bg-white p-5 shadow-[0_24px_70px_-50px_rgba(15,23,42,0.55)] transition hover:-translate-y-0.5 hover:border-slate-300"
            >
                <div class="pointer-events-none absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-sky-400 via-cyan-400 to-emerald-400 opacity-80" />
                <div class="pointer-events-none absolute -top-10 end-0 h-24 w-24 rounded-full bg-sky-100/60 blur-2xl transition group-hover:bg-sky-200/70" />

                <div class="relative flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600">
                            <span class="h-2 w-2 rounded-full bg-sky-500" />
                            {{ $t('Access token') }}
                        </div>

                        <div class="mt-4 break-all font-mono text-sm text-slate-900">
                            {{ item.masked_token }}
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            v-if="canEdit"
                            type="button"
                            class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-600 transition hover:border-sky-200 hover:bg-sky-50 hover:text-sky-700 disabled:cursor-not-allowed disabled:opacity-60"
                            :title="$t('Rotate token')"
                            :disabled="isBusy(item.uuid)"
                            @click="rotateToken(item.uuid)"
                        >
                            <svg
                                v-if="isBusy(item.uuid) && rotateForm.processing"
                                xmlns="http://www.w3.org/2000/svg"
                                width="18"
                                height="18"
                                viewBox="0 0 24 24"
                                class="animate-spin"
                            >
                                <path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".25" />
                                <path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z" />
                            </svg>
                            <svg v-else xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none">
                                <path d="M20 4v6h-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M4 20v-6h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M18 10a7 7 0 0 0-11.95-4.95L4 7m16 10l-2.05 1.95A7 7 0 0 1 6 14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <span class="sr-only">{{ $t('Rotate token') }}</span>
                        </button>

                        <button
                            v-if="canDelete"
                            type="button"
                            class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-rose-200 bg-white text-rose-600 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-60"
                            :title="$t('Delete token')"
                            :disabled="isBusy(item.uuid)"
                            @click="openAlert(item.uuid)"
                        >
                            <svg
                                v-if="isBusy(item.uuid) && deleteForm.processing"
                                xmlns="http://www.w3.org/2000/svg"
                                width="18"
                                height="18"
                                viewBox="0 0 24 24"
                                class="animate-spin"
                            >
                                <path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".25" />
                                <path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z" />
                            </svg>
                            <svg v-else xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none">
                                <path d="M4 7h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                <path d="M10 11v5m4-5v5M6 7l1 11a2 2 0 0 0 2 1.8h6a2 2 0 0 0 2-1.8L18 7M9 7V5.6A1.6 1.6 0 0 1 10.6 4h2.8A1.6 1.6 0 0 1 15 5.6V7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <span class="sr-only">{{ $t('Delete token') }}</span>
                        </button>
                    </div>
                </div>

                <div class="relative mt-5 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50/80 px-3 py-3">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">
                            {{ $t('Last four digits') }}
                        </p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">
                            {{ item.token_last_four || '----' }}
                        </p>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50/80 px-3 py-3">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">
                            {{ $t('Created on') }}
                        </p>
                        <p class="mt-1 text-sm font-medium text-slate-900">
                            {{ item.created_at }}
                        </p>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50/80 px-3 py-3">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">
                            {{ $t('Latest change') }}
                        </p>
                        <p class="mt-1 text-sm font-medium text-slate-900">
                            {{ item.updated_at }}
                        </p>
                    </div>
                </div>
            </article>
        </div>

        <div
            v-else
            class="rounded-[28px] border border-dashed border-slate-300 bg-slate-50/70 p-8 text-center shadow-inner"
        >
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-500">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none">
                    <path d="M8 10a4 4 0 0 1 7-2.646l1.293 1.293a4 4 0 1 1-5.657 5.657l-.543-.543" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                    <path d="M16 14a4 4 0 0 1-7 2.646l-1.293-1.293A4 4 0 0 1 13.364 9.7l.543.543" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </div>

            <h3 class="mt-4 text-lg font-semibold text-slate-900">
                {{ $t('No access tokens yet.') }}
            </h3>
            <p class="mt-2 text-sm leading-6 text-slate-600">
                {{ $t('Create your first API key to start authenticating requests.') }}
            </p>
        </div>

        <Pagination :pagination="rows.meta" />
    </div>

    <AlertModal
        v-model="isOpenAlert"
        :label="$t('Delete row')"
        :description="$t('Are you sure you want to delete this row? This action can not be undone')"
        @confirm="() => confirmAlert(deleteAction)"
    />
</template>
