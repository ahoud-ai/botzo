<script setup>
    import { ref } from 'vue';
    import debounce from 'lodash/debounce';
    import { Link, router } from "@inertiajs/vue3";
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
</script>
<template>
    <div class="pgt-toolbar">
        <div class="ui-table-search pgt-search">
            <span class="ui-table-search-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 15l6 6m-11-4a7 7 0 1 1 0-14a7 7 0 0 1 0 14Z"/></svg>
            </span>
            <input @input="search" v-model="params.search" type="text" class="ui-table-search-input" :placeholder="$t('Search here')">
            <button v-if="isSearching === false && params.search" @click="clearSearch" type="button" class="ui-table-search-action">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10s10-4.5 10-10S17.5 2 12 2zm3.7 12.3c.4.4.4 1 0 1.4c-.4.4-1 .4-1.4 0L12 13.4l-2.3 2.3c-.4.4-1 .4-1.4 0c-.4-.4-.4-1 0-1.4l2.3-2.3l-2.3-2.3c-.4-.4-.4-1 0-1.4c.4-.4 1-.4 1.4 0l2.3 2.3l2.3-2.3c.4-.4 1-.4 1.4 0c.4.4.4 1 0 1.4L13.4 12l2.3 2.3z"/></svg>
            </button>
            <span v-if="isSearching" class="ui-table-search-action">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><circle cx="12" cy="3.5" r="1.5" fill="currentColor" opacity="0"><animateTransform attributeName="transform" calcMode="discrete" dur="2.4s" repeatCount="indefinite" type="rotate" values="0 12 12;90 12 12;180 12 12;270 12 12"/><animate attributeName="opacity" dur="0.6s" keyTimes="0;0.5;1" repeatCount="indefinite" values="1;1;0"/></circle><circle cx="12" cy="3.5" r="1.5" fill="currentColor" opacity="0"><animateTransform attributeName="transform" begin="0.2s" calcMode="discrete" dur="2.4s" repeatCount="indefinite" type="rotate" values="30 12 12;120 12 12;210 12 12;300 12 12"/><animate attributeName="opacity" begin="0.2s" dur="0.6s" keyTimes="0;0.5;1" repeatCount="indefinite" values="1;1;0"/></circle><circle cx="12" cy="3.5" r="1.5" fill="currentColor" opacity="0"><animateTransform attributeName="transform" begin="0.4s" calcMode="discrete" dur="2.4s" repeatCount="indefinite" type="rotate" values="60 12 12;150 12 12;240 12 12;330 12 12"/><animate attributeName="opacity" begin="0.4s" dur="0.6s" keyTimes="0;0.5;1" repeatCount="indefinite" values="1;1;0"/></circle></svg>
            </span>
        </div>
    </div>

    <div v-if="isSearching" class="pgt-grid" aria-hidden="true">
        <div v-for="n in 6" :key="n" class="pgt-skeleton-card">
            <div class="pgt-skeleton-line pgt-skeleton-line--title"></div>
            <div class="pgt-skeleton-line pgt-skeleton-line--chip"></div>
            <div class="pgt-skeleton-line pgt-skeleton-line--short"></div>
        </div>
    </div>

    <template v-else-if="rows.data.length">
        <div class="pgt-grid">
            <Link v-for="item in rows.data" :key="item.id" :href="'/admin/settings/page/' + item.id" class="pgt-card">
                <div class="pgt-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Zm7 0v5h5M9 13h6m-6 4h6"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="pgt-name">{{ item.name_ar || item.display_name || item.name }}</h3>
                    <p class="pgt-name-en">{{ item.name_en || item.name }}</p>
                    <p class="pgt-updated">{{ $t('Last updated') }}: {{ item.updated_at }}</p>
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" class="pgt-chevron"><path fill="currentColor" fill-rule="evenodd" d="M9.97 7.47a.75.75 0 0 1 1.06 0l4 4a.75.75 0 0 1 0 1.06l-4 4a.75.75 0 1 1-1.06-1.06L13.44 12L9.97 8.53a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
            </Link>
        </div>
    </template>

    <UiEmptyState
        v-else
        :title="$t('No pages found')"
    >
        <template #icon>
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Zm7 0v5h5M9 13h6m-6 4h6"/></svg>
        </template>
    </UiEmptyState>
</template>

<style scoped>
.pgt-toolbar {
    margin-bottom: 1.25rem;
}

.pgt-search {
    max-width: 26rem;
}

.pgt-grid {
    display: grid;
    grid-template-columns: repeat(1, 1fr);
    gap: 1.1rem;
}

@media (min-width: 768px) {
    .pgt-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (min-width: 1280px) {
    .pgt-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

.pgt-card {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    border: 1px solid var(--ui-border);
    border-radius: 1.1rem;
    background: var(--ui-surface);
    padding: 1.1rem 1.25rem;
    transition: border-color 200ms ease, transform 200ms ease, box-shadow 200ms ease;
}

.pgt-card:hover {
    border-color: var(--ui-secondary);
    box-shadow: var(--ui-shadow-2);
    transform: translateY(-3px);
}

.pgt-icon {
    display: inline-flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    height: 2.6rem;
    width: 2.6rem;
    border-radius: 0.85rem;
    background: color-mix(in srgb, var(--ui-secondary) 14%, transparent);
    color: var(--ui-secondary);
}

.pgt-name {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.95rem;
    font-weight: 800;
    color: var(--ui-text);
}

.pgt-name-en {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    margin-top: 0.1rem;
    font-size: 0.78rem;
    color: var(--ui-muted);
}

.pgt-updated {
    margin-top: 0.3rem;
    font-size: 0.72rem;
    color: var(--ui-muted);
}

.pgt-chevron {
    flex-shrink: 0;
    color: var(--ui-muted);
}

.pgt-skeleton-card {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
    border: 1px solid var(--ui-border);
    border-radius: 1.1rem;
    background: var(--ui-surface);
    padding: 1.25rem;
}

.pgt-skeleton-line {
    height: 0.75rem;
    border-radius: 999px;
    background: var(--ui-surface-soft);
    animation: pgt-pulse 1.4s ease-in-out infinite;
}

.pgt-skeleton-line--title {
    width: 55%;
    height: 1rem;
}

.pgt-skeleton-line--chip {
    width: 80%;
}

.pgt-skeleton-line--short {
    width: 40%;
}

@keyframes pgt-pulse {
    0%, 100% { opacity: 0.6; }
    50% { opacity: 1; }
}
</style>
