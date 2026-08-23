<script setup>
import { computed, ref, toRefs } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AlertModal from '@/Components/AlertModal.vue';
import Pagination from '@/Components/Pagination.vue';
import UiEmptyState from '@/Components/UI/UiEmptyState.vue';
import { useAlertModal } from '@/Composables/useAlertModal';

const props = defineProps({
    rows: {
        type: Object,
        required: true,
    },
});

const { rows } = toRefs(props);

const { isOpenAlert: isOpenDeleteAlert, openAlert: openDeleteAlert, confirmAlert: confirmDeleteAlert } = useAlertModal();
const { isOpenAlert: isOpenRotateAlert, openAlert: openRotateAlert, confirmAlert: confirmRotateAlert } = useAlertModal();
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

const rotateAction = (uuid) => {
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
        <div v-if="hasRows" class="token-grid">
            <article
                v-for="item in rows.data"
                :key="item.uuid"
                class="token-card"
            >
                <div class="token-id">
                    <span class="token-mask">{{ item.masked_token }}</span>
                    <span class="token-chip">
                        <span class="token-chip-dot" />
                        {{ $t('Active') }}
                    </span>
                </div>

                <div class="token-meta">
                    <div class="token-meta-item">
                        <p class="token-meta-label">{{ $t('Created on') }}</p>
                        <p class="token-meta-value">{{ item.created_at }}</p>
                    </div>

                    <div class="token-meta-item">
                        <p class="token-meta-label">{{ $t('Last used') }}</p>
                        <p class="token-meta-value" :class="{ 'token-meta-value--muted': !item.last_used_at }">
                            {{ item.last_used_at || $t('Never used') }}
                        </p>
                    </div>
                </div>

                <div class="token-actions">
                    <button
                        v-if="canEdit"
                        type="button"
                        class="token-icon-btn"
                        :title="$t('Rotate token')"
                        :disabled="isBusy(item.uuid)"
                        @click="openRotateAlert(item.uuid)"
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
                        class="token-icon-btn token-icon-btn--danger"
                        :title="$t('Revoke token')"
                        :disabled="isBusy(item.uuid)"
                        @click="openDeleteAlert(item.uuid)"
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
                        <span class="sr-only">{{ $t('Revoke token') }}</span>
                    </button>
                </div>
            </article>
        </div>

        <UiEmptyState v-else :title="$t('No access tokens yet.')" :description="$t('Create your first API key to start authenticating requests.')">
            <template #icon>
                <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none"><path d="M8 10a4 4 0 0 1 7-2.646l1.293 1.293a4 4 0 1 1-5.657 5.657l-.543-.543" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /><path d="M16 14a4 4 0 0 1-7 2.646l-1.293-1.293A4 4 0 0 1 13.364 9.7l.543.543" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
            </template>
        </UiEmptyState>

        <Pagination v-if="hasRows" :pagination="rows.meta" />
    </div>

    <AlertModal
        v-model="isOpenDeleteAlert"
        :label="$t('Revoke this key?')"
        :description="$t('This key will stop working immediately. This action cannot be undone.')"
        :confirm-button-text="$t('Revoke')"
        @confirm="() => confirmDeleteAlert(deleteAction)"
    />

    <AlertModal
        v-model="isOpenRotateAlert"
        :label="$t('Rotate this key?')"
        :description="$t('The current key stops working immediately once rotated. Update any app or integration using it with the new key.')"
        :confirm-button-text="$t('Rotate')"
        confirm-button-class="bg-amber-600 hover:bg-amber-500"
        @confirm="() => confirmRotateAlert(rotateAction)"
    />
</template>

<style scoped>
.token-grid {
    display: flex;
    flex-direction: column;
    gap: 0.7rem;
}

.token-card {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    flex-wrap: wrap;
    border: 1px solid var(--ui-border);
    border-radius: 1rem;
    background: var(--ui-surface);
    padding: 1rem 1.15rem;
    transition: border-color 160ms ease;
}

.token-card:hover {
    border-color: var(--ui-border-strong);
}

.token-id {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    min-width: 13rem;
    flex: 2;
}

.token-mask {
    font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, monospace;
    font-size: 0.9rem;
    color: var(--ui-text);
    direction: ltr;
    unicode-bidi: isolate;
    text-align: start;
}

.token-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    width: fit-content;
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    padding: 0.22rem 0.6rem;
    border-radius: 999px;
}

.token-chip {
    background: color-mix(in srgb, #fff 84%, var(--ui-success) 16%);
    color: color-mix(in srgb, var(--ui-success) 76%, #0f172a);
}

.dark .token-chip {
    background: color-mix(in srgb, var(--ui-bg) 78%, var(--ui-success) 22%);
    color: color-mix(in srgb, var(--ui-success) 70%, #fff 30%);
}

.token-chip-dot {
    width: 0.4rem;
    height: 0.4rem;
    border-radius: 999px;
    background: currentColor;
}

.token-meta {
    display: flex;
    gap: 1.75rem;
    flex: 2;
    min-width: 15rem;
}

.token-meta-label {
    font-size: 0.68rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--ui-muted);
    margin: 0;
}

.token-meta-value {
    margin: 0.25rem 0 0;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--ui-text);
}

.token-meta-value--muted {
    font-weight: 500;
    font-style: italic;
    color: var(--ui-muted);
}

.token-actions {
    display: flex;
    gap: 0.5rem;
    flex: none;
    margin-inline-start: auto;
}

.token-icon-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 0.75rem;
    border: 1px solid var(--ui-border-strong);
    background: var(--ui-surface-soft);
    color: var(--ui-text);
    transition: filter 160ms ease;
}

.token-icon-btn:hover:not(:disabled) {
    filter: brightness(0.97);
}

.dark .token-icon-btn:hover:not(:disabled) {
    filter: brightness(1.25);
}

.token-icon-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.token-icon-btn--danger {
    color: var(--ui-danger);
    border-color: color-mix(in srgb, var(--ui-danger) 40%, var(--ui-border-strong));
}
</style>
