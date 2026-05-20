<script setup>
    import { computed } from 'vue';

    const props = defineProps({
        modelValue: [String, Number, Boolean],
        value: { type: [Boolean, Object] },
        name: String,
        className: String,
        label: String,
        disabled: Boolean
    })

    const emit = defineEmits(['update:modelValue']);
    const model = computed({
        get() {
            return props.modelValue;
        },
        set(value) {
            emit("update:modelValue", value);
        },
    });
</script>
<template>
    <div :class="className">
        <div class="relative mb-2 flex items-center gap-2">
            <div class="flex h-6 items-center">
                <input 
                    v-model="model"
                    :value="value"
                    :id="name"
                    :name="name"
                    type="checkbox" 
                    class="ui-checkbox-input"
                >
            </div>
            <div class="text-sm leading-6">
                <label :for="name" class="ui-checkbox-label capitalize">{{ label }}</label>
            </div>
        </div>
    </div>
</template>
