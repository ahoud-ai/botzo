<script setup>
    import { computed, ref, watch } from 'vue';
    import { useI18n } from 'vue-i18n';

    const { t } = useI18n();

    const props = defineProps({
        modelValue: {
            type: File,
            default: null,
        },
        name: {
            type: String,
            required: true,
        },
        description: {
            type: String,
            default: '',
        },
        className: {
            type: String,
            default: '',
        },
        error: {
            type: String,
            default: '',
        },
        disabled: {
            type: Boolean,
            default: false,
        },
        imageUrl: {
            type: String,
            default: null,
        },
        fallbackImageUrl: {
            type: String,
            default: null,
        },
        accept: {
            type: String,
            default: '.png,.jpg,.jpeg,.webp,.svg',
        },
        previewClass: {
            type: String,
            default: 'aspect-[4/3] w-full',
        },
        inputId: {
            type: String,
            required: true,
        },
        clearable: {
            type: Boolean,
            default: true,
        },
    });

    const emit = defineEmits(['update:modelValue', 'clear']);

    const inputRef = ref(null);
    const selectedFileName = ref('');
    const imageViewUrl = ref(props.imageUrl || props.fallbackImageUrl || null);

    watch(
        () => [props.imageUrl, props.fallbackImageUrl],
        ([nextImageUrl, nextFallbackImageUrl]) => {
            if (! selectedFileName.value) {
                imageViewUrl.value = nextImageUrl || nextFallbackImageUrl || null;
            }
        }
    );

    const hasCustomImage = computed(() => Boolean(props.imageUrl) && imageViewUrl.value === props.imageUrl);
    const hasPreview = computed(() => Boolean(imageViewUrl.value));

    const updateValue = (event) => {
        const fileSizeLimit = 5 * 1024 * 1024;
        const file = event.target.files?.[0] ?? null;

        if (! file) {
            emit('update:modelValue', null);
            selectedFileName.value = '';
            imageViewUrl.value = props.imageUrl || props.fallbackImageUrl || null;

            return;
        }

        if (file.size > fileSizeLimit) {
            alert(t('The selected image is too large. Please upload a file smaller than 5 MB.'));
            event.target.value = null;

            return;
        }

        const reader = new FileReader();

        reader.onload = (loadEvent) => {
            imageViewUrl.value = loadEvent.target?.result ?? null;
        };

        selectedFileName.value = file.name;
        emit('update:modelValue', file);
        reader.readAsDataURL(file);
    };

    const resetToDefault = () => {
        if (inputRef.value) {
            inputRef.value.value = '';
        }

        selectedFileName.value = '';
        imageViewUrl.value = props.fallbackImageUrl || null;
        emit('update:modelValue', null);
        emit('clear');
    };

    const onImageError = () => {
        if (props.fallbackImageUrl && imageViewUrl.value !== props.fallbackImageUrl) {
            imageViewUrl.value = props.fallbackImageUrl;

            return;
        }

        imageViewUrl.value = null;
    };
</script>

<template>
    <div :class="className" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <div class="mb-3">
            <h3 class="text-sm font-semibold text-gray-900">{{ name }}</h3>
            <p v-if="description" class="mt-1 text-sm leading-6 text-gray-600">{{ description }}</p>
        </div>

        <div :class="previewClass" class="mb-4 overflow-hidden rounded-xl border border-dashed border-gray-300 bg-gray-50">
            <img
                v-if="hasPreview"
                :src="imageViewUrl"
                :alt="name"
                class="h-full w-full object-contain"
                @error="onImageError"
            >
            <div v-else class="flex h-full min-h-40 items-center justify-center text-sm text-gray-400">
                {{ t('No image selected') }}
            </div>
        </div>

        <div class="mb-3 flex flex-wrap items-center gap-2 text-xs">
            <span class="rounded-full bg-slate-100 px-2.5 py-1 font-medium text-slate-700">
                {{ hasCustomImage || selectedFileName ? t('Custom upload') : t('Built-in default') }}
            </span>
            <span class="text-gray-500">{{ t('Recommended: PNG, JPG, WEBP, or SVG up to 5 MB') }}</span>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <input
                :id="inputId"
                ref="inputRef"
                type="file"
                class="hidden"
                :accept="accept"
                :disabled="disabled"
                @change="updateValue"
            >
            <label
                :for="inputId"
                class="cursor-pointer rounded-md bg-primary px-3 py-2 text-sm text-white transition hover:opacity-90"
            >
                {{ t('Upload replacement image') }}
            </label>
            <button
                v-if="clearable && (props.imageUrl || selectedFileName)"
                type="button"
                class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 transition hover:border-gray-400 hover:text-gray-900"
                @click="resetToDefault"
            >
                {{ t('Use built-in default') }}
            </button>
            <span v-if="selectedFileName" class="text-sm text-gray-500">{{ selectedFileName }}</span>
        </div>

        <div v-if="error" class="form-error ui-danger-text mt-2 text-xs">{{ error }}</div>
    </div>
</template>
