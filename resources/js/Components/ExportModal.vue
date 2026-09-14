<template>
    <Modal :label="$t('Export')" :closeBtn="false" :isOpen="modelValue" @close="$emit('update:modelValue', false)">
        <div class="mt-5">
            <p class="export-desc">{{ $t('Choose the format you want to export your data in:') }}</p>

            <div class="space-y-3">
                <label class="export-option" :class="{ 'export-option--active': selectedFormat === 'xlsx' }">
                    <input
                        type="radio"
                        name="exportFormat"
                        value="xlsx"
                        v-model="selectedFormat"
                        class="export-radio"
                    >
                    <div>
                        <div class="export-option-title">{{ $t('Excel (.xlsx)') }}</div>
                        <div class="export-option-desc">{{ $t('Best for formatting, formulas, and complex data') }}</div>
                    </div>
                </label>

                <label class="export-option" :class="{ 'export-option--active': selectedFormat === 'csv' }">
                    <input
                        type="radio"
                        name="exportFormat"
                        value="csv"
                        v-model="selectedFormat"
                        class="export-radio"
                    >
                    <div>
                        <div class="export-option-title">{{ $t('CSV (.csv)') }}</div>
                        <div class="export-option-desc">{{ $t('Universal format, smaller file size, faster processing') }}</div>
                    </div>
                </label>
            </div>

            <div class="mt-5 flex justify-end gap-x-3">
                <button
                    type="button"
                    @click="$emit('update:modelValue', false)"
                    class="export-btn export-btn--ghost"
                >
                    {{ $t('Cancel') }}
                </button>
                <button type="button"
                    @click="exportData"
                    :disabled="!selectedFormat"
                    class="export-btn export-btn--solid"
                >
                    {{ $t('Export') }}
                </button>
            </div>
        </div>
    </Modal>
</template>

<script setup>
import { ref } from 'vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    modelValue: Boolean,
    type: {
        type: String,
        default: 'contact'
    }
});

const emit = defineEmits(['update:modelValue']);

const selectedFormat = ref('xlsx');

const exportData = () => {
    if (!selectedFormat.value) return;

    const url = props.type === 'contact'
        ? `/contacts/export?format=${selectedFormat.value}`
        : `/contact-groups/export?format=${selectedFormat.value}`;

    window.open(url, '_blank');
    emit('update:modelValue', false);
};
</script>

<style scoped>
.export-desc {
    font-size: 0.85rem;
    color: var(--ui-muted);
    margin-bottom: 1rem;
}

.export-option {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.85rem 1rem;
    border: 1px solid var(--ui-border);
    border-radius: 0.85rem;
    background: var(--ui-surface-soft);
    cursor: pointer;
    transition: border-color 160ms ease, background-color 160ms ease;
}

.export-option:hover {
    border-color: var(--ui-border-strong);
}

.export-option--active {
    border-color: var(--ui-secondary);
    background: color-mix(in srgb, var(--ui-secondary) 8%, var(--ui-surface));
}

.export-radio {
    accent-color: var(--ui-secondary);
    width: 1.05rem;
    height: 1.05rem;
    flex-shrink: 0;
}

.export-option-title {
    font-weight: 700;
    font-size: 0.9rem;
    color: var(--ui-text);
}

.export-option-desc {
    font-size: 0.78rem;
    color: var(--ui-muted);
    margin-top: 0.15rem;
}

.export-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.7rem;
    padding: 0.55rem 1.2rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: background-color 160ms ease, filter 160ms ease;
}

.export-btn--ghost {
    color: var(--ui-text);
    background: var(--ui-surface-soft);
    border: 1px solid var(--ui-border);
}

.export-btn--ghost:hover {
    background: var(--ui-border);
}

.export-btn--solid {
    color: #fff;
    background: var(--ui-secondary);
    border: 1px solid var(--ui-secondary);
}

.export-btn--solid:hover:not(:disabled) {
    filter: brightness(1.05);
}

.export-btn--solid:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    filter: none;
}
</style>
