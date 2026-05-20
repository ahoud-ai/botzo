<script setup>
    const props = defineProps({
        modelValue: [String, Number],
        showLabel: {
            type: Boolean,
            default: true
        },
        name: String,
        type: String,
        className: String,
        placeholder: String,
        textAreaRows: Number,
        error: String,
    })

    const emit = defineEmits(['update:modelValue']);
    const updateValue = (event) => {
        emit('update:modelValue', event.target.value);
    };
</script>
<template>
    <div :class="className">
        <label v-if="showLabel" for="name" class="ui-form-label">{{ name }}</label>
        <div>
            <textarea 
                class="ui-textarea placeholder:text-gray-400 sm:text-sm sm:leading-6"
                :class="error ? 'ui-input-error' : ''"
                :value="props.modelValue"
                @input="updateValue"
                :placeholder="placeholder"
                :rows="textAreaRows"
                >
                {{ props.modelValue }}
            </textarea>
        </div>
        <div v-if="error" class="ui-form-error">{{ error }}</div>
    </div>
</template>
