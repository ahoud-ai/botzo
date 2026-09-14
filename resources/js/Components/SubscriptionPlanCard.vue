<script setup>
import { computed } from 'vue';

const props = defineProps({
    planName: { type: String, required: true },
    periodLabel: { type: String, default: '' },
    price: { type: [String, Number], required: true },
    currencyLabel: { type: String, default: '' },
    priceCaption: { type: String, default: '' },
    rankLabel: { type: String, default: null },
    features: { type: Array, default: () => [] },
    ctaLabel: { type: String, required: true },
    current: { type: Boolean, default: false },
    scheduled: { type: Boolean, default: false },
    selected: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['select']);

const cardState = computed(() => {
    if (props.current) return 'current';
    if (props.scheduled) return 'scheduled';
    if (props.selected) return 'selected';
    return null;
});

const onSelect = () => {
    if (props.disabled) return;
    emit('select');
};
</script>

<template>
    <div
        class="plan-card"
        :class="[cardState ? `plan-card--${cardState}` : '', disabled ? 'plan-card--disabled' : '']"
        @click="onSelect"
    >
        <span v-if="current" class="plan-card-badge plan-card-badge--current">{{ $t('Current plan') }}</span>
        <span v-else-if="scheduled" class="plan-card-badge plan-card-badge--scheduled">{{ $t('Downgrade scheduled') }}</span>

        <div class="plan-card-head">
            <div class="min-w-0">
                <h3 class="plan-card-name">{{ planName }}</h3>
                <p v-if="periodLabel" class="plan-card-tier">{{ periodLabel }}</p>
            </div>
            <span v-if="rankLabel" class="plan-card-rank">{{ rankLabel }}</span>
        </div>

        <div class="plan-card-price-row">
            <span class="plan-card-price">{{ price }}</span>
            <span v-if="currencyLabel" class="plan-card-currency">{{ currencyLabel }}</span>
        </div>
        <p v-if="priceCaption" class="plan-card-caption">{{ priceCaption }}</p>

        <div class="plan-card-features">
            <div v-for="(feature, index) in features" :key="index" class="plan-card-feature">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M14.25 8.75c-.5 2.5-2.385 4.854-5.03 5.38A6.25 6.25 0 0 1 3.373 3.798C5.187 1.8 8.25 1.25 10.75 2.25" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="m5.75 7.75 2.5 2.5 6-6.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span>{{ feature.label }}</span>
            </div>
        </div>

        <div class="plan-card-cta" :class="cardState ? `plan-card-cta--${cardState}` : ''">
            {{ ctaLabel }}
        </div>
    </div>
</template>

<style scoped>
.plan-card {
    position: relative;
    display: flex;
    flex-direction: column;
    border-radius: 1.4rem;
    border: 2px solid var(--ui-border);
    background: var(--ui-surface);
    padding: 1.4rem;
    cursor: pointer;
    transition: border-color 180ms ease, box-shadow 180ms ease, transform 180ms ease, background-color 180ms ease;
}

.plan-card:hover {
    border-color: var(--ui-border-strong);
    transform: translateY(-2px);
}

.plan-card--current {
    border-color: var(--ui-secondary);
    background: color-mix(in srgb, var(--ui-secondary) 6%, var(--ui-surface));
}

.plan-card--selected {
    border-color: var(--ui-secondary);
    box-shadow: 0 14px 30px -18px color-mix(in srgb, var(--ui-secondary) 55%, transparent);
}

.plan-card--scheduled {
    border-color: var(--ui-warning);
    background: color-mix(in srgb, var(--ui-warning) 6%, var(--ui-surface));
}

.plan-card--disabled {
    cursor: not-allowed;
    opacity: 0.65;
}

.plan-card--disabled:hover {
    transform: none;
    border-color: var(--ui-border);
}

.plan-card-badge {
    position: absolute;
    top: -0.8rem;
    left: 50%;
    transform: translateX(-50%);
    white-space: nowrap;
    border-radius: 999px;
    padding: 0.3rem 1rem;
    font-size: 0.72rem;
    font-weight: 800;
    box-shadow: 0 8px 16px -8px rgba(15, 23, 42, 0.35);
}

.plan-card-badge--current {
    background: var(--ui-secondary);
    color: #fff;
}

.plan-card-badge--scheduled {
    background: var(--ui-warning);
    color: #fff;
}

.plan-card-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.6rem;
    margin-bottom: 1.1rem;
}

.plan-card-name {
    font-size: 1.05rem;
    font-weight: 800;
    margin: 0 0 0.2rem;
    color: var(--ui-text);
}

.plan-card-tier {
    font-size: 0.78rem;
    color: var(--ui-muted);
    margin: 0;
}

.plan-card-rank {
    flex: none;
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--ui-muted);
    background: var(--ui-surface-soft);
    border: 1px solid var(--ui-border);
    border-radius: 999px;
    padding: 0.2rem 0.55rem;
}

.plan-card-price-row {
    display: flex;
    align-items: baseline;
    gap: 0.4rem;
    margin-bottom: 0.2rem;
}

.plan-card-price {
    font-size: 2.1rem;
    font-weight: 800;
    line-height: 1;
    background: linear-gradient(104deg, var(--ui-secondary) 0%, color-mix(in srgb, var(--ui-secondary) 65%, var(--ui-primary)) 100%);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}

.plan-card-currency {
    font-size: 0.85rem;
    color: var(--ui-muted);
    font-weight: 600;
}

.plan-card-caption {
    font-size: 0.78rem;
    color: var(--ui-muted);
    margin: 0 0 1.1rem;
}

.plan-card-features {
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
    margin-bottom: 1.2rem;
    flex: 1;
}

.plan-card-feature {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    font-size: 0.82rem;
    color: var(--ui-text);
    line-height: 1.4;
}

.plan-card-feature svg {
    color: var(--ui-success);
    flex: none;
    margin-top: 0.15rem;
}

.plan-card-cta {
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.85rem;
    padding: 0.7rem;
    font-size: 0.85rem;
    font-weight: 700;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface-soft);
    color: var(--ui-text);
    transition: background-color 160ms ease, color 160ms ease, border-color 160ms ease;
}

.plan-card-cta--current {
    background: transparent;
    color: var(--ui-secondary);
    border-color: var(--ui-secondary);
}

.plan-card-cta--scheduled {
    background: transparent;
    color: var(--ui-warning);
    border-color: var(--ui-warning);
}

.plan-card-cta--selected {
    background: var(--ui-secondary);
    color: #fff;
    border-color: var(--ui-secondary);
}
</style>
