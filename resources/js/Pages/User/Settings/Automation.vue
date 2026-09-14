<template>
    <SettingLayout :modules="props.modules" :title="$t('Automation settings')" :subtitle="$t('Control automation and AI assistant behavior')">
        <div class="min-h-0">
            <div class="flex justify-center items-center">
                <div class="w-full md:w-[60em]">
                    <UiSectionCard :title="$t('Response Sequence Priority')" :subtitle="$t('Choose the order in which the system searches for a matching response when replying to messages. Set the sequence for Automation Flows, Basic Replies, and AI Assistant to control how replies are prioritized.')" class="mb-20">
                        <div class="settings-drag-hint">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"></path></svg>
                            {{ $t('Drag to reorder') }}
                        </div>
                        <draggable :list="form.response_sequence" class="flex flex-col gap-2" @end="submitForm()">
                            <template #item="{ index, element }">
                                <div class="settings-sequence-item">
                                    <span class="settings-sequence-rank">{{ index + 1 }}</span>
                                    <span class="settings-sequence-handle">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M9 19.23q-.508 0-.87-.36q-.36-.362-.36-.87t.36-.87t.87-.36t.87.36q.36.362.36.87t-.36.87t-.87.36m6 0q-.508 0-.87-.36q-.36-.362-.36-.87t.36-.87t.87-.36t.87.36q.36.362.36.87t-.36.87t-.87.36m-6-6q-.508 0-.87-.36q-.36-.362-.36-.87t.36-.87t.87-.36t.87.36q.36.362.36.87t-.36.87t-.87.36m6 0q-.508 0-.87-.36q-.36-.362-.36-.87t.36-.87t.87-.36t.87.36q.36.362.36.87t-.36.87t-.87.36m-6-6q-.508 0-.87-.36q-.36-.362-.36-.87t.36-.87t.87-.36t.87.36q.36.362.36.87t-.36.87t-.87.36m6 0q-.508 0-.87-.36q-.36-.362-.36-.87t.36-.87t.87-.36t.87.36q.36.362.36.87t-.36.87t-.87.36"></path></svg>
                                    </span>
                                    <span class="settings-field-label">{{ $t(element) }}</span>
                                </div>
                            </template>
                        </draggable>
                    </UiSectionCard>
                </div>
            </div>
        </div>
    </SettingLayout>
</template>
<script setup>
    import SettingLayout from "./Layout.vue";
    import { ref } from 'vue';
    import { useForm } from "@inertiajs/vue3";
    import draggable from "vuedraggable";
    import UiSectionCard from '@/Components/UI/UiSectionCard.vue';
    const props = defineProps(['rows', 'filters', 'settings', 'modules', 'automationSequenceOptions']);
    const config = ref(props.settings.metadata);
    const settings = ref(config.value ? JSON.parse(config.value) : null);

    const moduleActive = (moduleName) => {
        const module = props.modules.find((mod) => mod.name === moduleName);
        if (!module) {
            return false;
        }

        return Number(module.status) === 1 && Number(module.is_active ?? 1) === 1;
    };

    const fallbackOptions = [
        'Basic Replies',
        moduleActive('Flow builder') ? 'Automation Flows' : null,
        moduleActive('AI Assistant') ? 'AI Reply Assistant' : null,
    ].filter((item) => item !== null);

    const availableOptions = ref(
        Array.isArray(props.automationSequenceOptions) && props.automationSequenceOptions.length > 0
            ? props.automationSequenceOptions
            : fallbackOptions
    );

    const normalizeSequence = (sequence) => {
        const source = Array.isArray(sequence) ? sequence : [];
        const filtered = source.filter((item) => availableOptions.value.includes(item));

        availableOptions.value.forEach((item) => {
            if (!filtered.includes(item)) {
                filtered.push(item);
            }
        });

        return filtered;
    };

    const initialSequence = normalizeSequence(settings.value?.automation?.response_sequence ?? availableOptions.value);

    const form = useForm({
        response_sequence: initialSequence,
    });

    const submitForm = async () => {
        form.post('/settings/automation', {
            preserveScroll: true,
        })
    };
</script>

<style scoped>
.settings-drag-hint {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    margin-bottom: 0.9rem;
    font-size: 0.85rem;
    color: var(--ui-muted);
}

.settings-field-label {
    font-size: 0.96rem;
    font-weight: 600;
    color: var(--ui-text);
}

.settings-sequence-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    border-radius: 0.85rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface-soft);
    padding: 0.85rem 1rem;
    cursor: grab;
    transition: border-color 160ms ease, background-color 160ms ease;
}

.settings-sequence-item:hover {
    border-color: var(--ui-secondary);
}

.settings-sequence-rank {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.6rem;
    height: 1.6rem;
    flex-shrink: 0;
    border-radius: 999px;
    background: color-mix(in srgb, var(--ui-secondary) 14%, var(--ui-surface));
    color: var(--ui-secondary);
    font-size: 0.8rem;
    font-weight: 700;
}

.settings-sequence-handle {
    display: inline-flex;
    color: var(--ui-muted);
}
</style>
