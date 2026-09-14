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
    const isDraggingOver = ref(false);

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

    const applyFile = (file) => {
        const fileSizeLimit = 5 * 1024 * 1024;

        if (! file) {
            emit('update:modelValue', null);
            selectedFileName.value = '';
            imageViewUrl.value = props.imageUrl || props.fallbackImageUrl || null;

            return;
        }

        if (! file.type?.startsWith('image/')) {
            alert(t('Please choose an image file.'));

            return;
        }

        if (file.size > fileSizeLimit) {
            alert(t('The selected image is too large. Please upload a file smaller than 5 MB.'));

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

    const updateValue = (event) => {
        applyFile(event.target.files?.[0] ?? null);
    };

    const handleDrop = (event) => {
        isDraggingOver.value = false;

        if (props.disabled) {
            return;
        }

        const file = event.dataTransfer?.files?.[0] ?? null;
        applyFile(file);
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
    <div :class="className" class="fia-card">
        <div class="mb-3">
            <h3 class="fia-title">{{ name }}</h3>
            <p v-if="description" class="fia-description">{{ description }}</p>
        </div>

        <div
            :class="[previewClass, { 'fia-dropzone--over': isDraggingOver }]"
            class="fia-dropzone mb-4"
            @dragover.prevent="disabled ? null : (isDraggingOver = true)"
            @dragleave.prevent="isDraggingOver = false"
            @drop.prevent="handleDrop"
        >
            <img
                v-if="hasPreview"
                :src="imageViewUrl"
                :alt="name"
                class="h-full w-full object-contain"
                @error="onImageError"
            >
            <div v-else class="fia-empty">
                <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M4 5a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5Z"/><circle cx="9" cy="10" r="1.6" fill="none" stroke="currentColor" stroke-width="1.6"/><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="m5 17l5-5l3.5 3.5L18 11l2 2"/></svg>
                <span>{{ t('Drag & drop an image, or click upload') }}</span>
            </div>
        </div>

        <div class="mb-3 flex flex-wrap items-center gap-2 text-xs">
            <span class="fia-chip">
                {{ hasCustomImage || selectedFileName ? t('Custom upload') : t('Built-in default') }}
            </span>
            <span class="fia-hint">{{ t('Recommended: PNG, JPG, WEBP, or SVG up to 5 MB') }}</span>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <input
                :id="inputId"
                ref="inputRef"
                type="file"
                class="sr-only"
                :accept="accept"
                :disabled="disabled"
                @change="updateValue"
            >
            <label :for="inputId" class="fia-btn fia-btn--solid">
                {{ t('Upload replacement image') }}
            </label>
            <button
                v-if="clearable && (props.imageUrl || selectedFileName)"
                type="button"
                class="fia-btn fia-btn--ghost"
                @click="resetToDefault"
            >
                {{ t('Use built-in default') }}
            </button>
            <span v-if="selectedFileName" class="fia-filename">{{ selectedFileName }}</span>
        </div>

        <div v-if="error" class="ui-form-error mt-2">{{ error }}</div>
    </div>
</template>

<style scoped>
.fia-card {
    border-radius: 1rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    padding: 1.1rem;
}

.fia-title {
    font-size: 0.88rem;
    font-weight: 700;
    color: var(--ui-text);
}

.fia-description {
    margin-top: 0.25rem;
    font-size: 0.82rem;
    line-height: 1.5;
    color: var(--ui-muted);
}

.fia-dropzone {
    overflow: hidden;
    border-radius: 0.9rem;
    border: 2px dashed var(--ui-border-strong);
    background: var(--ui-surface-soft);
    transition: border-color 160ms ease, background-color 160ms ease;
}

.fia-dropzone--over {
    border-color: var(--ui-secondary);
    background: color-mix(in srgb, var(--ui-secondary) 8%, var(--ui-surface-soft));
}

.fia-empty {
    display: flex;
    height: 100%;
    min-height: 10rem;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 1rem;
    text-align: center;
    font-size: 0.8rem;
    color: var(--ui-muted);
}

.fia-chip {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    background: var(--ui-surface-soft);
    border: 1px solid var(--ui-border);
    padding: 0.2rem 0.65rem;
    font-weight: 600;
    color: var(--ui-text);
}

.fia-hint {
    color: var(--ui-muted);
}

.fia-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    border-radius: 0.7rem;
    padding: 0.5rem 0.95rem;
    font-size: 0.82rem;
    font-weight: 600;
    transition: filter 160ms ease, background-color 160ms ease, border-color 160ms ease;
}

.fia-btn--solid {
    background: var(--ui-secondary);
    color: #fff;
    box-shadow: var(--ui-shadow-1);
}

.fia-btn--solid:hover {
    filter: brightness(1.05);
}

.fia-btn--ghost {
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    color: var(--ui-text);
}

.fia-btn--ghost:hover {
    background: var(--ui-surface-soft);
    border-color: var(--ui-border-strong);
}

.fia-filename {
    font-size: 0.8rem;
    color: var(--ui-muted);
}
</style>
