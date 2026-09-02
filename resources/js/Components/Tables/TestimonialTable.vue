<script setup>
    import { ref } from 'vue';
    import debounce from 'lodash/debounce';
    import { Link, router, useForm } from '@inertiajs/vue3';
    import AlertModal from '@/Components/AlertModal.vue';
    import { useAlertModal } from '@/Composables/useAlertModal';
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
        },
    });

    const { isOpenAlert, openAlert, confirmAlert } = useAlertModal();

    const form = useForm({'test': null});

    const deleteAction = (key) => {
        form.delete('/admin/testimonials/' + key);
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
        const url = window.location.pathname;

        router.visit(url, {
            method: 'get',
            data: params.value,
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                isSearching.value = false;
            },
        })
    }

    const defaultAvatar = '/images/defaults/review-avatar.svg';

    const avatarUrl = (item) => {
        if (!item.image) {
            return defaultAvatar;
        }

        if (typeof item.image === 'string' && (
            item.image.startsWith('http://') ||
            item.image.startsWith('https://') ||
            item.image.startsWith('data:') ||
            item.image.startsWith('/')
        )) {
            return item.image;
        }

        return `/storage/${item.image}`;
    };

    const starArray = () => [1, 2, 3, 4, 5];
</script>
<template>
    <div class="ui-table-search tst-search">
        <span class="ui-table-search-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 15l6 6m-11-4a7 7 0 1 1 0-14a7 7 0 0 1 0 14Z"/></svg>
        </span>
        <input @input="search" v-model="params.search" type="text" class="ui-table-search-input" :placeholder="$t('Search reviews')">
        <button v-if="isSearching === false && params.search" @click="clearSearch" type="button" class="ui-table-search-action">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10s10-4.5 10-10S17.5 2 12 2zm3.7 12.3c.4.4.4 1 0 1.4c-.4.4-1 .4-1.4 0L12 13.4l-2.3 2.3c-.4.4-1 .4-1.4 0c-.4-.4-.4-1 0-1.4l2.3-2.3l-2.3-2.3c-.4-.4-.4-1 0-1.4c.4-.4 1-.4 1.4 0l2.3 2.3l2.3-2.3c.4-.4 1-.4 1.4 0c.4.4.4 1 0 1.4L13.4 12l2.3 2.3z"/></svg>
        </button>
        <span v-if="isSearching" class="ui-table-search-action">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><circle cx="12" cy="3.5" r="1.5" fill="currentColor" opacity="0"><animateTransform attributeName="transform" calcMode="discrete" dur="2.4s" repeatCount="indefinite" type="rotate" values="0 12 12;90 12 12;180 12 12;270 12 12"/><animate attributeName="opacity" dur="0.6s" keyTimes="0;0.5;1" repeatCount="indefinite" values="1;1;0"/></circle><circle cx="12" cy="3.5" r="1.5" fill="currentColor" opacity="0"><animateTransform attributeName="transform" begin="0.2s" calcMode="discrete" dur="2.4s" repeatCount="indefinite" type="rotate" values="30 12 12;120 12 12;210 12 12;300 12 12"/><animate attributeName="opacity" begin="0.2s" dur="0.6s" keyTimes="0;0.5;1" repeatCount="indefinite" values="1;1;0"/></circle><circle cx="12" cy="3.5" r="1.5" fill="currentColor" opacity="0"><animateTransform attributeName="transform" begin="0.4s" calcMode="discrete" dur="2.4s" repeatCount="indefinite" type="rotate" values="60 12 12;150 12 12;240 12 12;330 12 12"/><animate attributeName="opacity" begin="0.4s" dur="0.6s" keyTimes="0;0.5;1" repeatCount="indefinite" values="1;1;0"/></circle></svg>
        </span>
    </div>

    <div v-if="rows.data.length" class="tst-grid">
        <article v-for="item in rows.data" :key="item.id" class="tst-card" :class="{ 'tst-card--hidden': item.status !== 1 }">
            <div class="tst-card-top">
                <div class="flex min-w-0 items-center gap-3">
                    <img :src="avatarUrl(item)" :alt="item.name" class="tst-avatar">
                    <div class="min-w-0">
                        <p class="tst-name">{{ item.name }}</p>
                        <p v-if="item.position" class="tst-position">{{ item.position }}</p>
                    </div>
                </div>
                <Dropdown :align="'right'">
                    <button type="button" class="tst-kebab-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24">
                            <path fill="currentColor" d="M12 16a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2a2 2 0 0 1 2-2m0-6a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2a2 2 0 0 1 2-2m0-6a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2a2 2 0 0 1 2-2Z"/>
                        </svg>
                    </button>
                    <template #items>
                        <DropdownItemGroup>
                            <DropdownItem :href="'/admin/testimonials/' + item.id">{{ $t('View/edit') }}</DropdownItem>
                            <DropdownItem as="button" @click="openAlert(item.id)">{{ $t('Delete') }}</DropdownItem>
                        </DropdownItemGroup>
                    </template>
                </Dropdown>
            </div>

            <div class="tst-stars">
                <svg v-for="star in starArray()" :key="star" width="16" height="16" viewBox="0 0 24 24" :class="star <= (item.rating ?? 0) ? 'tst-star--on' : 'tst-star--off'">
                    <path fill="currentColor" d="m12 17.27l4.15 2.51c.76.46 1.69-.22 1.49-1.08l-1.1-4.72l3.67-3.18c.67-.58.31-1.68-.57-1.75l-4.83-.41l-1.89-4.46c-.34-.81-1.5-.81-1.84 0L9.19 8.63l-4.83.41c-.88.07-1.24 1.17-.57 1.75l3.67 3.18l-1.1 4.72c-.2.86.73 1.54 1.49 1.08z"/>
                </svg>
            </div>

            <p class="tst-review">{{ item.review }}</p>

            <div class="tst-footer">
                <span class="tst-chip" :class="item.status === 1 ? 'ui-chip-success' : 'ui-chip-neutral'">{{ item.status === 1 ? $t('Displayed') : $t('Hidden') }}</span>
                <span>{{ item.updated_at }}</span>
            </div>
        </article>
    </div>

    <UiEmptyState
        v-else
        :title="$t('No reviews found')"
    >
        <template #icon>
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24"><path fill="currentColor" d="m12 17.27l4.15 2.51c.76.46 1.69-.22 1.49-1.08l-1.1-4.72l3.67-3.18c.67-.58.31-1.68-.57-1.75l-4.83-.41l-1.89-4.46c-.34-.81-1.5-.81-1.84 0L9.19 8.63l-4.83.41c-.88.07-1.24 1.17-.57 1.75l3.67 3.18l-1.1 4.72c-.2.86.73 1.54 1.49 1.08z"/></svg>
        </template>
    </UiEmptyState>

    <Pagination v-if="rows.meta" class="mt-4" :pagination="rows.meta"/>

    <!-- Alert Modal Component-->
    <AlertModal
        v-model="isOpenAlert"
        @confirm="() => confirmAlert(deleteAction)"
        :label = "$t('Delete row')"
        :description = "$t('Are you sure you want to delete this row? This action can not be undone')"
    />
</template>

<style scoped>
.tst-search {
    margin-bottom: 1.25rem;
    min-height: 2.9rem;
    width: min(100%, 24rem);
}

.tst-grid {
    display: grid;
    grid-template-columns: repeat(1, 1fr);
    gap: 1.1rem;
}

@media (min-width: 768px) {
    .tst-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (min-width: 1280px) {
    .tst-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

.tst-card {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 1.3rem;
    border: 1px solid var(--ui-border);
    border-radius: var(--ui-radius-lg, 1.25rem);
    background: var(--ui-surface);
    transition: box-shadow 200ms ease, border-color 200ms ease, transform 200ms ease;
}

.tst-card:hover {
    border-color: var(--ui-border-strong);
    box-shadow: var(--ui-shadow-2);
    transform: translateY(-3px);
}

.tst-card--hidden {
    opacity: 0.72;
}

.tst-card-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
}

.tst-avatar {
    width: 2.9rem;
    height: 2.9rem;
    border-radius: 999px;
    object-fit: cover;
    border: 1px solid var(--ui-border);
    flex-shrink: 0;
}

.tst-name {
    font-size: 0.95rem;
    font-weight: 800;
    color: var(--ui-text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.tst-position {
    font-size: 0.78rem;
    color: var(--ui-muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.tst-kebab-btn {
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

.tst-kebab-btn:hover {
    background: var(--ui-surface-soft);
    color: var(--ui-text);
}

.tst-stars {
    display: flex;
    gap: 0.15rem;
}

.tst-star--on {
    color: var(--ui-warning);
}

.tst-star--off {
    color: var(--ui-border-strong);
}

.tst-review {
    font-size: 0.86rem;
    line-height: 1.6;
    color: var(--ui-text);
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.tst-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: auto;
    padding-top: 0.7rem;
    border-top: 1px solid var(--ui-border);
    font-size: 0.75rem;
    color: var(--ui-muted);
}

.tst-chip {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.25rem 0.65rem;
    font-size: 0.72rem;
    font-weight: 700;
}
</style>
