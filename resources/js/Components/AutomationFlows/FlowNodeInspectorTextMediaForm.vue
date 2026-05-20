<template>
    <label v-if="nodeType === 'send_text' && !looksLikeMediaNode" class="block">
        <FlowAutosizeTextarea rows="4" class="nodrag w-full min-h-[92px] rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm leading-6 text-slate-700 outline-none transition focus:border-emerald-300" :value="config.text || ''" :placeholder="$t('Enter text')" @input="setConfigValue('text', $event)" />
    </label>

    <div v-else class="grid gap-4">
        <label class="block">
            <div class="mb-2 text-sm font-semibold text-slate-900">{{ $t('Media Type') }}</div>
            <select class="nodrag w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-emerald-300" :value="config.media_type || 'image'" @change="setConfigValue('media_type', $event.target.value)">
                <option value="image">{{ $t('Image') }}</option>
                <option value="video">{{ $t('Video') }}</option>
                <option value="audio">{{ $t('Audio') }}</option>
                <option value="document">{{ $t('Document') }}</option>
            </select>
        </label>

        <label v-if="showMediaCaption" class="block">
            <div class="mb-2 text-sm font-semibold text-slate-900">{{ $t('Caption (Optional)') }}</div>
            <FlowAutosizeTextarea rows="3" class="nodrag w-full min-h-[72px] rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm leading-6 text-slate-700 outline-none transition focus:border-emerald-300" :value="config.caption || ''" :placeholder="$t('Enter text')" @input="setConfigValue('caption', $event)" />
        </label>

        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-3.5">
            <div v-if="asset" class="space-y-3">
                <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                    <div class="flow-media-file-name text-sm font-semibold text-slate-950">{{ asset.original_name }}</div>
                    <div class="mt-1 text-xs text-slate-500">{{ asset.mime_type }} · {{ humanFileSize(asset.size) }}</div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a :href="asset.url" target="_blank" class="nodrag inline-flex items-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:text-slate-950">
                        {{ $t('Preview file') }}
                    </a>
                    <button type="button" class="nodrag inline-flex items-center rounded-xl border border-rose-200 px-4 py-2 text-sm font-medium text-rose-600 transition hover:bg-rose-50" @click="removeAsset">
                        {{ $t('Remove file') }}
                    </button>
                </div>
            </div>

            <div v-else class="text-center">
                <div class="text-sm font-semibold text-slate-900">{{ $t('Upload media') }}</div>
                <div v-if="mediaAcceptLabel || mediaMaxSizeLabel" class="mt-2 text-xs text-slate-500">
                    <span v-if="mediaAcceptLabel">{{ mediaAcceptLabel }}</span>
                    <span v-if="mediaAcceptLabel && mediaMaxSizeLabel"> · </span>
                    <span v-if="mediaMaxSizeLabel">{{ mediaMaxSizeHelpText }}</span>
                </div>
            </div>

            <input ref="fileInput" type="file" class="hidden" :accept="fileAccept" @change="handleFileSelected" />

            <button type="button" class="nodrag mt-4 inline-flex items-center rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:text-slate-950" @click="openFilePicker">
                {{ asset ? $t('Replace file') : $t('Upload file') }}
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import FlowAutosizeTextarea from '@/Components/AutomationFlows/FlowAutosizeTextarea.vue';
import { useFlowNodeInspectorContext } from '@/Components/AutomationFlows/useFlowNodeInspector.js';

const { asset, config, fileAccept, humanFileSize, looksLikeMediaNode, mediaAcceptLabel, mediaMaxSizeHelpText, mediaMaxSizeLabel, nodeType, removeAsset, setConfigValue, showMediaCaption, uploadSelectedAsset } = useFlowNodeInspectorContext();

const fileInput = ref(null);

const openFilePicker = () => {
    fileInput.value?.click();
};

const handleFileSelected = async (event) => {
    const file = event?.target?.files?.[0];
    await uploadSelectedAsset(file);
    if (event?.target) {
        event.target.value = '';
    }
};
</script>

<style scoped>
.flow-media-file-name {
    overflow-wrap: anywhere;
    word-break: break-word;
    line-height: 1.35;
}
</style>
