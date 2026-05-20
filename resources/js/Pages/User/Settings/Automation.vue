<template>
    <SettingLayout :modules="props.modules">
        <div class="min-h-0">
            <div class="flex justify-center items-center">
                <div class="md:w-[60em]">
                    <div class="bg-white border border-slate-200 rounded-lg py-2 text-sm mb-4 pb-4 px-4 mb-20">
                        <div class="w-full py-2 mb-2 mt-2">
                            <div class="flex w-full mb-4">
                                <div class="text-md">
                                    <h4 class="text-[16px]">{{ $t('Response Sequence Priority') }}</h4>
                                    <span class="flex items-center mt-1 text-slate-500">
                                        {{ $t('Choose the order in which the system searches for a matching response when replying to messages. Set the sequence for Automation Flows, Basic Replies, and AI Assistant to control how replies are prioritized.') }}
                                    </span>
                                </div>
                            </div>
                            <div>
                                <span class="mb-2 flex items-center text-slate-700">
                                    <svg class="me-2" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"></path></svg>
                                    {{ $t('Drag to reorder') }}
                                </span>
                                <draggable :list="form.response_sequence" class="mt-2" @end="submitForm()">
                                    <template #item="{ index, element }">
                                        <div class="flex gap-x-2 bg-slate-50 rounded-md mb-2 p-2 border w-[20em] cursor-pointer">
                                            <span>
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M9 19.23q-.508 0-.87-.36q-.36-.362-.36-.87t.36-.87t.87-.36t.87.36q.36.362.36.87t-.36.87t-.87.36m6 0q-.508 0-.87-.36q-.36-.362-.36-.87t.36-.87t.87-.36t.87.36q.36.362.36.87t-.36.87t-.87.36m-6-6q-.508 0-.87-.36q-.36-.362-.36-.87t.36-.87t.87-.36t.87.36q.36.362.36.87t-.36.87t-.87.36m6 0q-.508 0-.87-.36q-.36-.362-.36-.87t.36-.87t.87-.36t.87.36q.36.362.36.87t-.36.87t-.87.36m-6-6q-.508 0-.87-.36q-.36-.362-.36-.87t.36-.87t.87-.36t.87.36q.36.362.36.87t-.36.87t-.87.36m6 0q-.508 0-.87-.36q-.36-.362-.36-.87t.36-.87t.87-.36t.87.36q.36.362.36.87t-.36.87t-.87.36"></path></svg>
                                            </span>
                                            <span>{{ $t(element) }}</span>
                                        </div>
                                    </template>
                                </draggable>
                            </div>
                        </div>
                    </div>
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


