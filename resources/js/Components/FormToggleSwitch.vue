<script setup>
    import { computed } from 'vue';

    const props = defineProps({
        modelValue: Boolean,
        className: String,
        disabled:{
            type: Boolean,
            default: false
        },
    })

    const emit = defineEmits(['update:modelValue']);
    const isActive = computed(() => props.modelValue === true || props.modelValue == 1);

    const updateValue = () => {
        if(props.disabled == false){
            emit('update:modelValue', !isActive.value);
        }
    };
</script>
<template>
    <button
        type="button"
        :class="['inline-flex items-center justify-center align-middle', className]"
        :disabled="props.disabled"
        :aria-pressed="isActive"
        @click="updateValue"
    >
        <span
            class="ui-toggle-track"
            :class="{
                'ui-toggle-track-on': isActive,
                'ui-toggle-track-disabled': props.disabled
            }"
        >
            <span class="ui-toggle-thumb"></span>
        </span>
    </button>
</template>
