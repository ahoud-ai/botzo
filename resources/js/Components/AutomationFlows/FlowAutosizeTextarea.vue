<template>
    <textarea
        ref="textareaRef"
        v-bind="attrs"
        :rows="rows"
        :value="resolvedValue"
        @input="handleInput"
    />
</template>

<script setup>
import { computed, nextTick, onMounted, ref, useAttrs, watch } from 'vue';

defineOptions({
    inheritAttrs: false,
});

const props = defineProps({
    modelValue: {
        type: String,
        default: undefined,
    },
    value: {
        type: String,
        default: '',
    },
    rows: {
        type: Number,
        default: 3,
    },
});

const emit = defineEmits(['update:modelValue', 'input']);
const attrs = useAttrs();
const textareaRef = ref(null);
const resolvedValue = computed(() => (props.modelValue ?? props.value ?? ''));

const resize = () => {
    const element = textareaRef.value;
    if (!element) {
        return;
    }

    element.style.height = 'auto';
    const minHeight = Number(element.dataset.minHeight || 0);
    const nextHeight = Math.max(element.scrollHeight, minHeight || 0);
    element.style.height = `${nextHeight}px`;
};

const handleInput = (event) => {
    const nextValue = event?.target?.value ?? '';
    emit('update:modelValue', nextValue);
    emit('input', nextValue);
    resize();
};

watch(resolvedValue, () => {
    void nextTick(resize);
});

onMounted(() => {
    const element = textareaRef.value;
    if (element) {
        element.dataset.minHeight = String(element.offsetHeight || 0);
    }
    void nextTick(resize);
});
</script>
