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
    primary: 'border border-primary bg-primary text-white hover:opacity-90',
    secondary: 'border border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:text-slate-950',
    subtle: 'border border-transparent bg-slate-100 text-slate-700 hover:bg-slate-200',
    danger: 'border border-rose-200 bg-white text-rose-600 hover:bg-rose-50',
    ghost: 'border border-transparent bg-transparent text-slate-600 hover:bg-slate-100 hover:text-slate-950',
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
