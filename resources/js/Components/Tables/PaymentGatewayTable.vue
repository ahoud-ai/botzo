<script setup>
    import { Link } from '@inertiajs/vue3';

    const props = defineProps({
        rows: {
            type: Object,
            required: true,
        },
    });

    const normalizeGatewayName = (value) => String(value ?? '').trim().toLowerCase();

    const pageEditHref = (item) => `/admin/payment-gateways/${encodeURIComponent(normalizeGatewayName(item?.name))}`;

    const gatewayInitial = (item) => (item?.name ?? '').trim().charAt(0).toUpperCase() || '#';
</script>
<template>
    <div class="pgt-grid">
        <article v-for="(item, index) in rows.data" :key="index" class="pgt-card">
            <div class="flex min-w-0 items-center gap-3.5">
                <span class="pgt-avatar" :class="item.is_active == '1' ? 'pgt-avatar--active' : ''">{{ gatewayInitial(item) }}</span>
                <div class="min-w-0">
                    <p class="pgt-name capitalize">{{ item.name }}</p>
                    <span class="pgt-chip" :class="item.is_active == '1' ? 'ui-chip-success' : 'ui-chip-neutral'">
                        {{ item.is_active == '1' ? $t('Active') : $t('Inactive') }}
                    </span>
                </div>
            </div>

            <Link :href="pageEditHref(item)" class="pgt-btn">
                {{ $t('Edit') }}
            </Link>
        </article>
    </div>
</template>

<style scoped>
.pgt-grid {
    display: grid;
    gap: 1rem;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
}

.pgt-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    border-radius: 1.1rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    padding: 1.1rem 1.25rem;
    box-shadow: var(--ui-shadow-1);
    transition: border-color 200ms ease, transform 200ms ease, box-shadow 200ms ease;
}

.pgt-card:hover {
    border-color: var(--ui-border-strong);
    box-shadow: var(--ui-shadow-2);
    transform: translateY(-2px);
}

.pgt-avatar {
    display: inline-flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    width: 2.75rem;
    height: 2.75rem;
    border-radius: 0.9rem;
    font-size: 1.05rem;
    font-weight: 800;
    background: var(--ui-surface-soft);
    color: var(--ui-muted);
    border: 1px solid var(--ui-border);
}

.pgt-avatar--active {
    background: linear-gradient(135deg, var(--ui-secondary), color-mix(in srgb, var(--ui-secondary) 70%, var(--ui-primary)));
    color: #fff;
    border-color: transparent;
    box-shadow: 0 8px 18px -10px color-mix(in srgb, var(--ui-secondary) 70%, transparent);
}

.pgt-name {
    font-size: 0.95rem;
    font-weight: 800;
    color: var(--ui-text);
}

.pgt-chip {
    display: inline-flex;
    align-items: center;
    margin-top: 0.35rem;
    border-radius: 999px;
    padding: 0.25rem 0.65rem;
    font-size: 0.72rem;
    font-weight: 700;
}

.pgt-btn {
    display: inline-flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 0.75rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    color: var(--ui-text);
    padding: 0.55rem 1rem;
    font-size: 0.83rem;
    font-weight: 600;
    transition: background-color 160ms ease, border-color 160ms ease;
}

.pgt-btn:hover {
    background: var(--ui-surface-soft);
    border-color: var(--ui-border-strong);
}
</style>
