<script setup>
    import { computed } from 'vue';
    import AlertModal from '@/Components/AlertModal.vue';
    import { useForm } from "@inertiajs/vue3";
    import { useI18n } from 'vue-i18n';
    import { useAlertModal } from '@/Composables/useAlertModal';
    import Dropdown from '@/Components/Dropdown.vue';
    import DropdownItemGroup from '@/Components/DropdownItemGroup.vue';
    import DropdownItem from '@/Components/DropdownItem.vue';
    import UiEmptyState from '@/Components/UI/UiEmptyState.vue';

    const props = defineProps({
        rows: {
            type: Object,
            required: true,
        },
    });

    const { t } = useI18n();
    const { isOpenAlert, openAlert, confirmAlert } = useAlertModal();

    const emit = defineEmits(['edit', 'delete']);

    const form = useForm({'test': null});

    function edit(id) {
        emit('edit', id);
    }

    const deleteAction = (key) => {
        form.delete('/admin/coupons/' + key);
    }

    const isUnlimited = (item) => item.quantity === null || item.quantity === undefined;
    const redeemedCount = (item) => Number(item.quantity_redeemed ?? 0);
    const remainingCount = (item) => Math.max(0, Number(item.quantity ?? 0) - redeemedCount(item));
    const usagePercent = (item) => {
        const total = Number(item.quantity ?? 0);
        if (total <= 0) return 0;
        return Math.min(100, Math.round((redeemedCount(item) / total) * 100));
    };
</script>
<template>
    <div v-if="rows.data.length" class="cpn-grid">
        <article v-for="item in rows.data" :key="item.id" class="cpn-card" :class="{ 'cpn-card--inactive': item.status !== 'active' }">
            <div class="cpn-card-top">
                <div class="min-w-0">
                    <h3 class="cpn-name">{{ item.name }}</h3>
                    <span class="cpn-code">{{ item.code }}</span>
                </div>
                <Dropdown :align="'right'">
                    <button type="button" class="cpn-kebab-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24">
                            <path fill="currentColor" d="M12 16a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2a2 2 0 0 1 2-2m0-6a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2a2 2 0 0 1 2-2m0-6a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2a2 2 0 0 1 2-2Z"/>
                        </svg>
                    </button>
                    <template #items>
                        <DropdownItemGroup>
                            <DropdownItem as="button" @click="edit(item.id)">{{ $t('Edit') }}</DropdownItem>
                            <DropdownItem as="button" @click="openAlert(item.id)">{{ $t('Delete') }}</DropdownItem>
                        </DropdownItemGroup>
                    </template>
                </Dropdown>
            </div>

            <div class="cpn-stub">
                <span class="cpn-percentage">{{ item.percentage }}%</span>
                <span class="cpn-percentage-label">{{ $t('Discount') }}</span>
            </div>

            <div class="cpn-usage">
                <div class="flex items-center justify-between gap-2">
                    <span class="cpn-chip" :class="item.status === 'active' ? 'ui-chip-success' : 'ui-chip-neutral'">{{ $t(item.status) }}</span>
                    <span v-if="isUnlimited(item)" class="cpn-usage-text">{{ $t('Unlimited') }}</span>
                    <span v-else class="cpn-usage-text">{{ remainingCount(item) }}/{{ item.quantity }} {{ $t('Remaining') }}</span>
                </div>
                <div v-if="!isUnlimited(item)" class="cpn-progress-track mt-2">
                    <div class="cpn-progress-fill" :style="{ width: usagePercent(item) + '%' }"></div>
                </div>
            </div>
        </article>
    </div>

    <UiEmptyState
        v-else
        :title="$t('No coupons found')"
    >
        <template #icon>
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24"><path fill="currentColor" d="M4 6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 1 0 0 4v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3a2 2 0 1 0 0-4zm9-1v2h-2V5zm0 4v2h-2V9zm0 4v2h-2v-2zm0 4v2h-2v-2z"/></svg>
        </template>
    </UiEmptyState>

    <!-- Alert Modal Component-->
    <AlertModal
        v-model="isOpenAlert"
        @confirm="() => confirmAlert(deleteAction)"
        :label = "$t('Delete row')"
        :description = "$t('Are you sure you want to delete this row? This action can not be undone')"
    />
</template>

<style scoped>
.cpn-grid {
    display: grid;
    grid-template-columns: repeat(1, 1fr);
    gap: 1.1rem;
}

@media (min-width: 768px) {
    .cpn-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (min-width: 1280px) {
    .cpn-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

.cpn-card {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    padding: 1.25rem;
    border: 1px dashed var(--ui-border-strong);
    border-radius: 1.1rem;
    background: var(--ui-surface);
    transition: border-color 200ms ease, transform 200ms ease, box-shadow 200ms ease;
}

.cpn-card:hover {
    border-color: var(--ui-secondary);
    box-shadow: var(--ui-shadow-2);
    transform: translateY(-3px);
}

.cpn-card--inactive {
    opacity: 0.72;
}

.cpn-card-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
}

.cpn-name {
    font-size: 0.98rem;
    font-weight: 800;
    color: var(--ui-text);
}

.cpn-code {
    display: inline-block;
    margin-top: 0.35rem;
    border-radius: 0.5rem;
    background: var(--ui-surface-soft);
    border: 1px solid var(--ui-border);
    padding: 0.2rem 0.55rem;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    color: var(--ui-secondary);
}

.cpn-kebab-btn {
    display: inline-flex;
    height: 2.35rem;
    width: 2.35rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 0.7rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    color: var(--ui-muted);
    transition: background-color 160ms ease, color 160ms ease;
}

.cpn-kebab-btn:hover {
    background: var(--ui-surface-soft);
    color: var(--ui-text);
}

.cpn-stub {
    display: flex;
    align-items: baseline;
    gap: 0.5rem;
    border-top: 1px dashed var(--ui-border);
    border-bottom: 1px dashed var(--ui-border);
    padding: 0.75rem 0;
}

.cpn-percentage {
    font-size: 2rem;
    font-weight: 900;
    letter-spacing: -0.02em;
    background: linear-gradient(135deg, var(--ui-secondary), color-mix(in srgb, var(--ui-secondary) 70%, var(--ui-primary)));
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}

.cpn-percentage-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--ui-muted);
}

.cpn-usage-text {
    font-size: 0.78rem;
    color: var(--ui-muted);
}

.cpn-chip {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.25rem 0.7rem;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: capitalize;
}

.cpn-progress-track {
    height: 0.4rem;
    overflow: hidden;
    border-radius: 999px;
    background: var(--ui-border);
}

.cpn-progress-fill {
    height: 100%;
    border-radius: 999px;
    background: var(--ui-secondary);
    transition: width 400ms ease;
}
</style>
