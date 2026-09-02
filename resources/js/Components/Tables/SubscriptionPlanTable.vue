<script setup>
    import { ref } from 'vue';
    import debounce from 'lodash/debounce';
    import { Link, router } from '@inertiajs/vue3';
    import { useForm } from "@inertiajs/vue3";
    import { useI18n } from 'vue-i18n';
    import AlertModal from '@/Components/AlertModal.vue';
    import { useAlertModal } from '@/Composables/useAlertModal';
    import PlanTransferModal from '@/Components/Modals/PlanTransferModal.vue';
    import 'vue3-toastify/dist/index.css';
    import Dropdown from '@/Components/Dropdown.vue';
    import DropdownItemGroup from '@/Components/DropdownItemGroup.vue';
    import DropdownItem from '@/Components/DropdownItem.vue';
    import Pagination from '@/Components/Pagination.vue';
    import UiEmptyState from '@/Components/UI/UiEmptyState.vue';

    const props = defineProps({
        rows: {
            type: Object,
            required: true,
        },
        filters: {
            type: Object
        }
    });

    const { t } = useI18n();
    const { isOpenAlert, openAlert, confirmAlert } = useAlertModal();

    const form = useForm({'test': null});
    const isTransferModalOpen = ref(false);
    const selectedPlanUuid = ref(null);

    const deleteAction = async (key) => {
        selectedPlanUuid.value = key;

        // First check if there are subscribers
        try {
            const response = await fetch(`/admin/plans/${key}/check-subscribers`);
            const data = await response.json();

            if (data.has_subscribers) {
                // Show transfer modal if subscribers exist
                isTransferModalOpen.value = true;
            } else {
                // Delete directly if no subscribers
                form.delete('/admin/plans/' + key);
            }
        } catch (error) {
            console.error('Error checking subscribers:', error);
            // Fallback to direct delete if check fails
            form.delete('/admin/plans/' + key);
        }
    }

    const handlePlanDeleted = () => {
        // Refresh the page to show updated data
        router.reload();
    }

    const params = ref({
        search: props.filters?.search ?? null,
    });

    const isSearching = ref(false);

    const clearSearch = () => {
        params.value.search = null;
        runSearch();
    }

    const search = debounce(() => {
        isSearching.value = true;
        runSearch();
    }, 600);

    const runSearch = () => {
        router.visit('/admin/plans', {
            method: 'get',
            data: params.value,
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                isSearching.value = false;
            },
        })
    }

    const planInitial = (item) => (item.display_name ?? item.name ?? '').trim().charAt(0).toUpperCase() || '#';

    const periodLabel = (period) => period === 'yearly' ? t('yr') : t('mo');
</script>
<template>
    <div class="ui-table-search plt-search">
        <span class="ui-table-search-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 15l6 6m-11-4a7 7 0 1 1 0-14a7 7 0 0 1 0 14Z"/></svg>
        </span>
        <input @input="search" v-model="params.search" type="text" class="ui-table-search-input" :placeholder="$t('Search plans')">
        <button v-if="isSearching === false && params.search" @click="clearSearch" type="button" class="ui-table-search-action">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10s10-4.5 10-10S17.5 2 12 2zm3.7 12.3c.4.4.4 1 0 1.4c-.4.4-1 .4-1.4 0L12 13.4l-2.3 2.3c-.4.4-1 .4-1.4 0c-.4-.4-.4-1 0-1.4l2.3-2.3l-2.3-2.3c-.4-.4-.4-1 0-1.4c.4-.4 1-.4 1.4 0l2.3 2.3l2.3-2.3c.4-.4 1-.4 1.4 0c.4.4.4 1 0 1.4L13.4 12l2.3 2.3z"/></svg>
        </button>
        <span v-if="isSearching" class="ui-table-search-action">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><circle cx="12" cy="3.5" r="1.5" fill="currentColor" opacity="0"><animateTransform attributeName="transform" calcMode="discrete" dur="2.4s" repeatCount="indefinite" type="rotate" values="0 12 12;90 12 12;180 12 12;270 12 12"/><animate attributeName="opacity" dur="0.6s" keyTimes="0;0.5;1" repeatCount="indefinite" values="1;1;0"/></circle><circle cx="12" cy="3.5" r="1.5" fill="currentColor" opacity="0"><animateTransform attributeName="transform" begin="0.2s" calcMode="discrete" dur="2.4s" repeatCount="indefinite" type="rotate" values="30 12 12;120 12 12;210 12 12;300 12 12"/><animate attributeName="opacity" begin="0.2s" dur="0.6s" keyTimes="0;0.5;1" repeatCount="indefinite" values="1;1;0"/></circle><circle cx="12" cy="3.5" r="1.5" fill="currentColor" opacity="0"><animateTransform attributeName="transform" begin="0.4s" calcMode="discrete" dur="2.4s" repeatCount="indefinite" type="rotate" values="60 12 12;150 12 12;240 12 12;330 12 12"/><animate attributeName="opacity" begin="0.4s" dur="0.6s" keyTimes="0;0.5;1" repeatCount="indefinite" values="1;1;0"/></circle></svg>
        </span>
    </div>

    <div v-if="rows.data.length" class="plt-grid">
        <article v-for="item in rows.data" :key="item.uuid" class="plt-card" :class="{ 'plt-card--inactive': item.status !== 'active' }">
            <div class="plt-card-top">
                <span class="plt-avatar">{{ planInitial(item) }}</span>
                <Dropdown :align="'right'">
                    <button type="button" class="plt-kebab-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24">
                            <path fill="currentColor" d="M12 16a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2a2 2 0 0 1 2-2m0-6a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2a2 2 0 0 1 2-2m0-6a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2a2 2 0 0 1 2-2Z"/>
                        </svg>
                    </button>
                    <template #items>
                        <DropdownItemGroup>
                            <DropdownItem :href="'/admin/plans/' + item.uuid">{{ $t('View/edit') }}</DropdownItem>
                            <DropdownItem as="button" @click="openAlert(item.uuid)">{{ $t('Delete') }}</DropdownItem>
                        </DropdownItemGroup>
                    </template>
                </Dropdown>
            </div>

            <h3 class="plt-name">{{ item.display_name || item.name }}</h3>

            <div class="plt-price-row">
                <span class="plt-price">{{ item.price }}</span>
                <span class="plt-period">/{{ periodLabel(item.period) }}</span>
            </div>

            <div class="plt-meta-row">
                <span class="plt-chip" :class="item.status === 'active' ? 'ui-chip-success' : 'ui-chip-neutral'">{{ $t(item.status) }}</span>
                <span v-if="item.tier_rank" class="plt-chip ui-chip-info">{{ $t('Tier') }} {{ item.tier_rank }}</span>
            </div>

            <div class="plt-footer">
                <span>{{ $t('Last updated') }}</span>
                <span>{{ item.updated_at }}</span>
            </div>
        </article>
    </div>

    <UiEmptyState
        v-else
        :title="$t('No plans found')"
    >
        <template #icon>
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24"><path fill="currentColor" d="M7 3h10a2 2 0 0 1 2 2v14l-7-3l-7 3V4a2 2 0 0 1 2-2m0 2v12.97l5-2.14l5 2.14V4zm5 2a3 3 0 1 1 0 6a3 3 0 0 1 0-6"/></svg>
        </template>
    </UiEmptyState>

    <Pagination v-if="rows.meta" class="mt-4" :pagination="rows.meta"/>

    <!-- Alert Modal Component-->
    <AlertModal
        v-model="isOpenAlert"
        @confirm="() => confirmAlert(deleteAction)"
        :label = "$t('Delete Plan')"
        :description = "$t('Are you sure you want to delete this plan? This action cannot be undone')"
    />

    <!-- Plan Transfer Modal -->
    <PlanTransferModal
        :isOpen="isTransferModalOpen"
        :planUuid="selectedPlanUuid"
        @close="isTransferModalOpen = false"
        @deleted="handlePlanDeleted"
    />
</template>

<style scoped>
.plt-search {
    margin-bottom: 1.25rem;
    min-height: 2.9rem;
    width: min(100%, 24rem);
}

.plt-grid {
    display: grid;
    grid-template-columns: repeat(1, 1fr);
    gap: 1.1rem;
}

@media (min-width: 768px) {
    .plt-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (min-width: 1280px) {
    .plt-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

.plt-card {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    padding: 1.4rem;
    border: 1px solid var(--ui-border);
    border-radius: var(--ui-radius-lg, 1.25rem);
    background: var(--ui-surface);
    overflow: hidden;
    transition: box-shadow 200ms ease, border-color 200ms ease, transform 200ms ease;
}

.plt-card::before {
    content: '';
    position: absolute;
    inset-inline: 0;
    top: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--ui-secondary), color-mix(in srgb, var(--ui-secondary) 40%, transparent));
}

.plt-card--inactive::before {
    background: linear-gradient(90deg, var(--ui-muted), color-mix(in srgb, var(--ui-muted) 40%, transparent));
}

.plt-card:hover {
    border-color: var(--ui-border-strong);
    box-shadow: var(--ui-shadow-2);
    transform: translateY(-3px);
}

.plt-card-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
}

.plt-avatar {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.75rem;
    height: 2.75rem;
    border-radius: 0.9rem;
    font-size: 1.05rem;
    font-weight: 800;
    color: #fff;
    background: linear-gradient(135deg, var(--ui-secondary), color-mix(in srgb, var(--ui-secondary) 70%, var(--ui-primary)));
    box-shadow: 0 8px 18px -10px color-mix(in srgb, var(--ui-secondary) 70%, transparent);
}

.plt-kebab-btn {
    display: inline-flex;
    height: 2.5rem;
    width: 2.5rem;
    align-items: center;
    justify-content: center;
    border-radius: 0.75rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    color: var(--ui-muted);
    transition: background-color 160ms ease, color 160ms ease;
}

.plt-kebab-btn:hover {
    background: var(--ui-surface-soft);
    color: var(--ui-text);
}

.plt-name {
    font-size: 1.1rem;
    font-weight: 800;
    color: var(--ui-text);
}

.plt-price-row {
    display: flex;
    align-items: baseline;
    gap: 0.3rem;
}

.plt-price {
    font-size: 2rem;
    font-weight: 900;
    letter-spacing: -0.02em;
    color: var(--ui-text);
}

.plt-period {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--ui-muted);
}

.plt-meta-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}

.plt-chip {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.3rem 0.75rem;
    font-size: 0.76rem;
    font-weight: 700;
    text-transform: capitalize;
}

.plt-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 0.4rem;
    padding-top: 0.75rem;
    border-top: 1px solid var(--ui-border);
    font-size: 0.78rem;
    color: var(--ui-muted);
}
</style>
