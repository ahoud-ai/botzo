<script setup>
const props = defineProps({
    type: {
        type: String,
        default: 'button',
    },
    variant: {
        type: String,
        default: 'secondary',
    },
    size: {
        type: String,
        default: 'md',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    block: {
        type: Boolean,
        default: false,
    },
});

const variantClass = {
    primary: 'border border-[var(--ui-primary)] bg-[var(--ui-primary)] text-white hover:opacity-90',
    secondary: 'border border-[var(--ui-border-strong)] bg-[var(--ui-surface-soft)] text-[var(--ui-text)] shadow-[var(--ui-shadow-1)] hover:bg-[var(--ui-surface)] hover:border-[var(--ui-secondary)]',
    subtle: 'border border-transparent bg-[var(--ui-surface-soft)] text-[var(--ui-text)] hover:bg-[color-mix(in_srgb,var(--ui-surface-soft)_60%,var(--ui-border))]',
    danger: 'border border-[color-mix(in_srgb,var(--ui-danger)_35%,var(--ui-border))] bg-[var(--ui-surface)] text-[var(--ui-danger)] hover:bg-[color-mix(in_srgb,var(--ui-danger)_10%,transparent)]',
    ghost: 'border border-transparent bg-transparent text-[var(--ui-muted)] hover:bg-[var(--ui-surface-soft)] hover:text-[var(--ui-text)]',
};

const sizeClass = {
    sm: 'rounded-xl px-3 py-2 text-xs font-semibold',
    md: 'rounded-2xl px-4 py-3 text-sm font-medium',
    lg: 'rounded-2xl px-5 py-3 text-sm font-semibold',
    icon: 'rounded-xl p-2.5 text-sm font-medium',
};
</script>

<template>
    <button
        :type="props.type"
        class="inline-flex items-center justify-center gap-2 transition disabled:cursor-not-allowed disabled:opacity-50"
        :class="[variantClass[props.variant] || variantClass.secondary, sizeClass[props.size] || sizeClass.md, props.block ? 'w-full' : '']"
        :disabled="props.disabled"
    >
        <slot />
    </button>
</template>
