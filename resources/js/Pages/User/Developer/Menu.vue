<template>
    <div class="dev-menu">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-3">
                <div class="dev-menu-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none">
                        <path d="M7 7h10v10H7z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
                        <path d="M4 12h3m10 0h3M12 4v3m0 10v3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                    </svg>
                </div>

                <div>
                    <p class="dev-menu-eyebrow">{{ $t('Developer Tools') }}</p>
                    <h2 class="dev-menu-title">{{ $t('Integration workspace') }}</h2>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <Link
                    rel="noopener noreferrer"
                    href="/developer-tools/access-tokens"
                    class="dev-menu-tab"
                    :class="{ 'dev-menu-tab--active': $page.url === '/developer-tools/access-tokens' }"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none">
                        <path d="M8 10a4 4 0 0 1 7-2.646l1.293 1.293a4 4 0 1 1-5.657 5.657l-.543-.543" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M16 14a4 4 0 0 1-7 2.646l-1.293-1.293A4 4 0 0 1 13.364 9.7l.543.543" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <span>{{ $t('Access token') }}</span>
                </Link>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<style scoped>
.dev-menu {
    border-radius: 1.5rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    padding: 1rem;
}

.dev-menu-icon {
    display: flex;
    height: 3rem;
    width: 3rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 1rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface-soft);
    color: var(--ui-secondary);
}

.dev-menu-eyebrow {
    margin: 0;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.2em;
    color: var(--ui-muted);
}

.dev-menu-title {
    margin: 0.15rem 0 0;
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--ui-text);
}

.dev-menu-tab {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border-radius: 1rem;
    border: 1px solid var(--ui-border-strong);
    background: var(--ui-surface-soft);
    color: var(--ui-text);
    padding: 0.6rem 1.05rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: filter 160ms ease;
}

.dev-menu-tab:hover {
    filter: brightness(0.97);
}

.dark .dev-menu-tab:hover {
    filter: brightness(1.2);
}

.dev-menu-tab.dev-menu-tab--active {
    border-color: var(--ui-secondary);
    background: var(--ui-secondary);
    color: #fff;
}
</style>
