<template>
    <AppLayout>
        <UiPageHeader :title="$t('Frontend Management')" :subtitle="$t('Manage homepage visibility and visitor experience from one place')" />

        <div class="fe-grid mt-6">
            <article class="fe-card" style="--tone: var(--ui-secondary)">
                <div class="fe-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Zm7 0v5h5M9 13h6m-6 4h6"/></svg>
                </div>
                <h3 class="fe-title">{{ $t('Content pages') }}</h3>
                <p class="fe-desc">{{ $t('Edit bilingual public pages using the advanced editor') }}</p>
                <Link href="/admin/settings/pages" class="fe-btn fe-btn--solid">{{ $t('Open pages manager') }}</Link>
            </article>

            <article class="fe-card" style="--tone: var(--ui-primary)">
                <div class="fe-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M12 3a9 9 0 1 0 0 18c1.1 0 1.5-.6 1.5-1.3c0-.4-.15-.7-.4-1c-.25-.3-.4-.6-.4-1c0-.8.7-1.5 1.5-1.5H16a4 4 0 0 0 4-4c0-4.4-3.6-8-8-8Z"/><circle cx="7.5" cy="11.5" r="1.1" fill="currentColor" stroke="none"/><circle cx="10" cy="8" r="1.1" fill="currentColor" stroke="none"/><circle cx="14.5" cy="8" r="1.1" fill="currentColor" stroke="none"/><circle cx="16.5" cy="11.5" r="1.1" fill="currentColor" stroke="none"/></svg>
                </div>
                <h3 class="fe-title">{{ $t('Brand and appearance') }}</h3>
                <p class="fe-desc">{{ $t('Control logo, favicon, and active frontend variant') }}</p>
                <Link href="/admin/settings/general" class="fe-btn fe-btn--ghost">{{ $t('Open general frontend settings') }}</Link>
            </article>

            <article class="fe-card" style="--tone: var(--ui-success)">
                <div class="fe-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M20 10c0 5.5-6.5 10.5-7.4 11.1a1 1 0 0 1-1.2 0C10.5 20.5 4 15.5 4 10a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="2.6" fill="none" stroke="currentColor" stroke-width="1.6"/></svg>
                </div>
                <h3 class="fe-title">{{ $t('Frontend contact details') }}</h3>
                <p class="fe-desc">{{ $t('Manage bilingual addresses and up to two mobile numbers shown in the public footer and contact page') }}</p>
                <Link href="/admin/settings/frontend/contact-details" class="fe-btn fe-btn--solid">{{ $t('Open frontend contact details') }}</Link>
            </article>

            <article class="fe-card" style="--tone: var(--ui-warning)">
                <div class="fe-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M4 5a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5Z"/><circle cx="9" cy="10" r="1.6" fill="none" stroke="currentColor" stroke-width="1.6"/><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="m5 17l5-5l3.5 3.5L18 11l2 2"/></svg>
                </div>
                <h3 class="fe-title">{{ $t('Premium homepage images') }}</h3>
                <p class="fe-desc">{{ $t('Manage Premium homepage visuals with safe fallbacks to the built-in design assets') }}</p>
                <Link href="/admin/settings/frontend/premium-home" class="fe-btn fe-btn--ghost">{{ $t('Open premium homepage images') }}</Link>
            </article>

            <article class="fe-card" style="--tone: var(--ui-accent, var(--ui-secondary))">
                <div class="fe-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><circle cx="11" cy="11" r="6.5" fill="none" stroke="currentColor" stroke-width="1.6"/><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.6" d="m20 20l-4.3-4.3"/></svg>
                </div>
                <h3 class="fe-title">{{ $t('SEO and tracking') }}</h3>
                <p class="fe-desc">{{ $t('Control indexing, search snippets, social sharing cards, and marketing tracking tags from one place') }}</p>
                <Link href="/admin/settings/frontend/seo" class="fe-btn fe-btn--solid">{{ $t('Open SEO and tracking settings') }}</Link>
            </article>
        </div>

        <UiSectionCard :title="$t('Frontend status')" class="mt-6">
            <div class="fe-status-grid">
                <div class="fe-status-tile">
                    <span class="fe-status-label">{{ $t('Default public frontend') }}</span>
                    <span class="fe-status-value">{{ frontendVariantLabel }}</span>
                </div>
                <div class="fe-status-tile">
                    <span class="fe-status-label">{{ $t('Status') }}</span>
                    <span class="fe-chip" :class="isFrontendVisible ? 'ui-chip-success' : 'ui-chip-warning'">
                        {{ isFrontendVisible ? $t('Show') : $t('Hidden') }}
                    </span>
                </div>
            </div>
        </UiSectionCard>
    </AppLayout>
</template>

<script setup>
    import { computed } from 'vue';
    import { Link } from '@inertiajs/vue3';
    import { useI18n } from 'vue-i18n';
    import AppLayout from '../Layout/App.vue';
    import UiPageHeader from '@/Components/UI/UiPageHeader.vue';
    import UiSectionCard from '@/Components/UI/UiSectionCard.vue';

    const { t } = useI18n();

    const props = defineProps({
        config: {
            type: Array,
            required: true,
        },
    });

    const getValueByKey = (key) => {
        const found = props.config.find((item) => item.key === key);

        return found ? found.value : '';
    };

    const frontendVariantLabel = computed(() => {
        const variant = getValueByKey('frontend_variant');

        return variant === 'premium' ? t('Premium Frontend') : t('Classic Frontend');
    });

    const isFrontendVisible = computed(() => {
        const value = getValueByKey('display_frontend');

        return value === '' || value === null || value === '1' || value === 1 || value === true || value === 'true';
    });
</script>

<style scoped>
.fe-grid {
    display: grid;
    grid-template-columns: repeat(1, 1fr);
    gap: 1.1rem;
}

@media (min-width: 768px) {
    .fe-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (min-width: 1280px) {
    .fe-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

.fe-card {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
    border: 1px solid var(--ui-border);
    border-radius: 1.1rem;
    background: var(--ui-surface);
    padding: 1.35rem;
    transition: border-color 200ms ease, transform 200ms ease, box-shadow 200ms ease;
}

.fe-card:hover {
    border-color: var(--tone, var(--ui-secondary));
    box-shadow: var(--ui-shadow-2);
    transform: translateY(-3px);
}

.fe-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 2.75rem;
    width: 2.75rem;
    border-radius: 0.9rem;
    background: color-mix(in srgb, var(--tone, var(--ui-secondary)) 14%, transparent);
    color: var(--tone, var(--ui-secondary));
    margin-bottom: 0.25rem;
}

.fe-title {
    font-size: 1rem;
    font-weight: 800;
    color: var(--ui-text);
}

.fe-desc {
    flex: 1;
    font-size: 0.85rem;
    line-height: 1.5;
    color: var(--ui-muted);
}

.fe-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    align-self: flex-start;
    margin-top: 0.5rem;
    border-radius: 0.75rem;
    padding: 0.55rem 1.05rem;
    font-size: 0.82rem;
    font-weight: 600;
    transition: filter 160ms ease, background-color 160ms ease, border-color 160ms ease;
}

.fe-btn--solid {
    background: var(--ui-secondary);
    color: #fff;
    box-shadow: var(--ui-shadow-1);
}

.fe-btn--solid:hover {
    filter: brightness(1.05);
}

.fe-btn--ghost {
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    color: var(--ui-text);
}

.fe-btn--ghost:hover {
    background: var(--ui-surface-soft);
    border-color: var(--ui-border-strong);
}

.fe-status-grid {
    display: grid;
    grid-template-columns: repeat(1, 1fr);
    gap: 0.85rem;
}

@media (min-width: 640px) {
    .fe-status-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

.fe-status-tile {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    border-radius: 0.85rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface-soft);
    padding: 0.85rem 1.05rem;
}

.fe-status-label {
    font-size: 0.82rem;
    color: var(--ui-muted);
}

.fe-status-value {
    font-size: 0.88rem;
    font-weight: 700;
    color: var(--ui-text);
}

.fe-chip {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.25rem 0.7rem;
    font-size: 0.75rem;
    font-weight: 700;
}
</style>
