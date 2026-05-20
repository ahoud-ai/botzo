<script setup>
    const props = defineProps({
        modelValue: [String, Number],
        label: String,
        name: String,
        placeholder: String,
        type: String,
        min: Number,
        className: String,
        labelClass: String,
        required: Boolean,
        error: String,
        disabled: Boolean
    })

    const emit = defineEmits(['update:modelValue']);
    const updateValue = (event) => {
        emit('update:modelValue', event.target.value);
    };
</script>
<template>
    <div :class="className">
        <label for="name" class="ui-form-label" :class="labelClass">{{ label ?? name }}</label>
        <div>
            <input
            class="ui-input placeholder:text-gray-400 sm:text-sm sm:leading-6"
            :class="error ? 'ui-input-error' : ''"
            :type="type"
            :value="props.modelValue"
            @input="updateValue"
            :step="'any'"
            :min="min"
            :placeholder="placeholder"
            :disabled="disabled"
            :required="required"
            />
        </div>
        <div v-if="error" class="ui-form-error">{{ error }}</div>
    </div>
</template>
