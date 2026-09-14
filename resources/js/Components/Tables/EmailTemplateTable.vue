<script setup>
    import { reactive, ref } from 'vue';
    import debounce from 'lodash/debounce';
    import { Link, router } from "@inertiajs/vue3";
    import { useI18n } from 'vue-i18n';
    import Pagination from '@/Components/Pagination.vue';
    import UiEmptyState from '@/Components/UI/UiEmptyState.vue';

    const { t } = useI18n();

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
    const expanded = reactive({});

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

    const togglePreview = (id) => {
        expanded[id] = !expanded[id];
    }

    const snippetFor = (html) => {
        const container = document.createElement('div');
        container.innerHTML = String(html || '');
        const text = (container.textContent || '').replace(/\s+/g, ' ').trim();

        return text.length > 0 ? text : t('No preview content');
    }
</script>
<template>
    <div class="emt-toolbar">
        <div class="ui-table-search emt-search">
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
        <span v-if="rows.meta" class="emt-count">{{ rows.meta.total }} {{ $t('templates') }}</span>
    </div>

    <!-- Loading skeleton -->
    <div v-if="isSearching" class="emt-grid" aria-hidden="true">
        <div v-for="n in 6" :key="n" class="emt-skeleton-card">
            <div class="emt-skeleton-line emt-skeleton-line--title"></div>
            <div class="emt-skeleton-line emt-skeleton-line--chip"></div>
            <div class="emt-skeleton-line"></div>
            <div class="emt-skeleton-line emt-skeleton-line--short"></div>
        </div>
    </div>

    <template v-else-if="rows.data.length">
        <div class="emt-grid">
            <article v-for="item in rows.data" :key="item.id" class="emt-card">
                <div class="emt-card-top">
                    <div class="emt-avatar">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16v12H4zm0 0l8 7l8-7"/></svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="emt-name capitalize">{{ item.name }}</h3>
                        <p class="emt-updated">{{ $t('Last updated') }}: {{ item.updated_at }}</p>
                    </div>
                </div>

                <div class="emt-subject" :title="item.subject">{{ item.subject }}</div>

                <p class="emt-snippet">{{ snippetFor(item.body) }}</p>

                <div class="emt-card-footer">
                    <Link :href="'/admin/settings/email-template/' + item.id" class="emt-btn emt-btn--solid">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7m-1.5-9.5a2.121 2.121 0 0 1 3 3L12 15l-4 1l1-4l9.5-9.5Z"/></svg>
                        {{ $t('Edit') }}
                    </Link>
                    <button type="button" class="emt-btn emt-btn--ghost" :aria-expanded="Boolean(expanded[item.id])" @click="togglePreview(item.id)">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2 12s3.6-7 10-7s10 7 10 7s-3.6 7-10 7s-10-7-10-7Z"/><circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                        {{ expanded[item.id] ? $t('Hide preview') : $t('Preview') }}
                    </button>
                </div>

                <Transition name="emt-collapse">
                    <div v-if="expanded[item.id]" class="emt-preview-panel">
                        <p class="emt-preview-note">{{ $t('HTML preview may differ after sanitization') }}</p>
                        <div class="emt-preview-frame prose prose-sm max-w-none" v-html="item.body"></div>
                    </div>
                </Transition>
            </article>
        </div>

        <Pagination class="emt-pagination" :pagination="rows.meta"/>
    </template>

    <UiEmptyState
        v-else
        :title="$t('No email templates found')"
    >
        <template #icon>
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M4 6h16v12H4zm0 0l8 7l8-7"/></svg>
        </template>
    </UiEmptyState>
</template>

<style scoped>
.emt-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.85rem;
    margin-bottom: 1.25rem;
}

.emt-search {
    max-width: 26rem;
    flex: 1;
}

.emt-count {
    flex-shrink: 0;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--ui-muted);
}

.emt-grid {
    display: grid;
    grid-template-columns: repeat(1, 1fr);
    gap: 1.1rem;
    align-items: start;
}

@media (min-width: 768px) {
    .emt-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (min-width: 1280px) {
    .emt-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

.emt-card {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    border: 1px solid var(--ui-border);
    border-radius: 1.1rem;
    background: var(--ui-surface);
    padding: 1.25rem;
    transition: border-color 200ms ease, transform 200ms ease, box-shadow 200ms ease;
}

.emt-card:hover {
    border-color: var(--ui-secondary);
    box-shadow: var(--ui-shadow-2);
    transform: translateY(-3px);
}

.emt-card-top {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}

.emt-avatar {
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

.emt-name {
    font-size: 0.98rem;
    font-weight: 800;
    color: var(--ui-text);
}

.emt-updated {
    margin-top: 0.15rem;
    font-size: 0.75rem;
    color: var(--ui-muted);
}

.emt-subject {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    border: 1px dashed var(--ui-border-strong);
    border-radius: 0.6rem;
    background: var(--ui-surface-soft);
    padding: 0.35rem 0.65rem;
    font-size: 0.8rem;
    color: var(--ui-text);
}

.emt-snippet {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 2.6em;
    font-size: 0.82rem;
    line-height: 1.4;
    color: var(--ui-muted);
}

.emt-card-footer {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin-top: auto;
}

.emt-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    border-radius: 0.7rem;
    padding: 0.5rem 0.85rem;
    font-size: 0.8rem;
    font-weight: 600;
    transition: filter 160ms ease, background-color 160ms ease, border-color 160ms ease;
}

.emt-btn--solid {
    background: var(--ui-secondary);
    color: #fff;
    box-shadow: var(--ui-shadow-1);
}

.emt-btn--solid:hover {
    filter: brightness(1.05);
}

.emt-btn--ghost {
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    color: var(--ui-text);
}

.emt-btn--ghost:hover {
    background: var(--ui-surface-soft);
    border-color: var(--ui-border-strong);
}

.emt-preview-panel {
    border-top: 1px solid var(--ui-border);
    padding-top: 0.75rem;
}

.emt-preview-note {
    margin-bottom: 0.5rem;
    font-size: 0.72rem;
    color: var(--ui-warning);
}

.emt-preview-frame {
    max-height: 16rem;
    overflow: auto;
    border-radius: 0.7rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface-soft);
    padding: 0.85rem;
    font-size: 0.82rem;
    color: var(--ui-text);
}

.emt-collapse-enter-active,
.emt-collapse-leave-active {
    transition: opacity 160ms ease;
}

.emt-collapse-enter-from,
.emt-collapse-leave-to {
    opacity: 0;
}

.emt-pagination {
    margin-top: 1.25rem;
}

.emt-skeleton-card {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
    border: 1px solid var(--ui-border);
    border-radius: 1.1rem;
    background: var(--ui-surface);
    padding: 1.25rem;
}

.emt-skeleton-line {
    height: 0.75rem;
    border-radius: 999px;
    background: var(--ui-surface-soft);
    animation: emt-pulse 1.4s ease-in-out infinite;
}

.emt-skeleton-line--title {
    width: 55%;
    height: 1rem;
}

.emt-skeleton-line--chip {
    width: 80%;
}

.emt-skeleton-line--short {
    width: 40%;
}

@keyframes emt-pulse {
    0%, 100% { opacity: 0.6; }
    50% { opacity: 1; }
}
</style>
