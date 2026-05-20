<template>
    <div v-if="nodeType === 'send_buttons'" class="grid gap-3">
        <label class="block">
            <div class="mb-2 text-sm font-semibold text-slate-900">{{ $t('Body') }}</div>
            <FlowAutosizeTextarea rows="4" class="nodrag w-full min-h-[92px] rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm leading-6 text-slate-700 outline-none transition focus:border-emerald-300" :maxlength="interactiveBodyMaxLength" :value="config.body || ''" :placeholder="$t('Enter the main message for this message type')" @input="setConfigValue('body', $event)" />
            <div class="mt-1 text-xs text-slate-500">{{ counterLabel(config.body, interactiveBodyMaxLength) }}</div>
        </label>

        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3.5">
            <div class="flex items-center justify-between gap-3">
                <div class="text-sm font-semibold text-slate-900">{{ $t('Reply Buttons (at least 1 button)') }}</div>
                <button type="button" class="nodrag inline-flex items-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:text-slate-950 disabled:cursor-not-allowed disabled:opacity-60" :disabled="buttons.length >= maxButtons" @click="addButton">
                    {{ $t('Add Button') }}
                </button>
            </div>
            <div class="mt-2 text-xs text-slate-500">{{ maxButtonsHelpText }}</div>

            <div class="mt-4 space-y-3">
                <div v-for="(button, index) in buttons" :key="button.id || index" class="rounded-2xl border border-white bg-white p-3">
                    <div class="flex items-center justify-between gap-2">
                        <div class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">{{ $t('Button') }} {{ index + 1 }}</div>
                        <button v-if="buttons.length > 1" type="button" class="nodrag text-xs font-medium text-rose-600" @click="removeButton(index)">{{ $t('Remove') }}</button>
                    </div>
                    <input type="text" class="nodrag mt-3 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-emerald-300" :maxlength="buttonTitleMaxLength" :value="button.title || ''" :placeholder="`${$t('Button')} ${index + 1}`" @input="updateButton(index, 'title', $event.target.value)" />
                    <div class="mt-1 text-xs text-slate-500">{{ counterLabel(button.title, buttonTitleMaxLength) }}</div>
                </div>
            </div>
        </div>

        <details class="nodrag rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
            <summary class="nodrag cursor-pointer list-none text-sm font-semibold text-slate-900">
                {{ $t('Advanced settings') }}
            </summary>
            <div class="mt-3 grid gap-3">
                <label class="block">
                    <div class="mb-2 text-sm font-semibold text-slate-900">{{ $t('Header (Optional)') }}</div>
                    <input type="text" class="nodrag w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-emerald-300" :maxlength="interactiveHeaderMaxLength" :value="config.header || ''" :placeholder="$t('Enter text')" @input="setConfigValue('header', $event.target.value)" />
                    <div class="mt-1 text-xs text-slate-500">{{ counterLabel(config.header, interactiveHeaderMaxLength) }}</div>
                </label>

                <label class="block">
                    <div class="mb-2 text-sm font-semibold text-slate-900">{{ $t('Footer Text (Optional)') }}</div>
                    <input type="text" class="nodrag w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-emerald-300" :maxlength="interactiveFooterMaxLength" :value="config.footer || ''" :placeholder="$t('Enter footer text')" @input="setConfigValue('footer', $event.target.value)" />
                    <div class="mt-1 text-xs text-slate-500">{{ counterLabel(config.footer, interactiveFooterMaxLength) }}</div>
                </label>

                <label class="block">
                    <div class="mb-2 text-sm font-semibold text-slate-900">{{ $t('If the customer sends a normal message instead of tapping a reply') }}</div>
                    <select class="nodrag w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-emerald-300" :value="config.invalid_reply_behavior || runtimeInvalidReplyDefaultBehavior" @change="setConfigValue('invalid_reply_behavior', $event.target.value)">
                        <option v-for="option in invalidReplyBehaviorOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                    <div class="mt-2 text-xs text-slate-500">{{ $t('Choose what should happen when the customer types instead of using the interactive choice.') }}</div>
                </label>
            </div>
        </details>
    </div>

    <div v-else class="grid gap-3">
        <label class="block">
            <div class="mb-2 text-sm font-semibold text-slate-900">{{ $t('Body') }}</div>
            <FlowAutosizeTextarea rows="4" class="nodrag w-full min-h-[92px] rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm leading-6 text-slate-700 outline-none transition focus:border-emerald-300" :maxlength="interactiveBodyMaxLength" :value="config.body || ''" :placeholder="$t('Enter the main message for this message type')" @input="setConfigValue('body', $event)" />
            <div class="mt-1 text-xs text-slate-500">{{ counterLabel(config.body, interactiveBodyMaxLength) }}</div>
        </label>

        <label class="block">
            <div class="mb-2 text-sm font-semibold text-slate-900">{{ $t('Button Label') }}</div>
            <input type="text" class="nodrag w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-emerald-300" :maxlength="listButtonLabelMaxLength" :value="config.button_label || ''" :placeholder="$t('Button Label')" @input="setConfigValue('button_label', $event.target.value)" />
            <div class="mt-1 text-xs text-slate-500">{{ counterLabel(config.button_label, listButtonLabelMaxLength) }}</div>
        </label>

        <div class="space-y-3 rounded-2xl border border-slate-200 bg-slate-50 p-3.5">
            <div class="flex items-center justify-between gap-3">
                <div class="text-sm font-semibold text-slate-900">{{ $t('Sections (At least one section)') }}</div>
                <button type="button" class="nodrag inline-flex items-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:text-slate-950 disabled:cursor-not-allowed disabled:opacity-60" :disabled="!canAddSection" @click="addSection">
                    {{ $t('Add Section') }}
                </button>
            </div>
            <div class="mt-2 text-xs text-slate-500">{{ maxListSectionsHelpText }}</div>
            <div class="mt-1 text-xs text-slate-500">{{ maxListRowsHelpText }} ({{ totalListRows }} / {{ maxListTotalRows }})</div>

            <div v-for="(section, sectionIndex) in listSections" :key="sectionIndex" class="rounded-2xl border border-white bg-white p-4">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">{{ $t('Section') }} {{ sectionIndex + 1 }}</div>
                    <button v-if="listSections.length > 1" type="button" class="nodrag text-xs font-medium text-rose-600" @click="removeSection(sectionIndex)">{{ $t('Remove') }}</button>
                </div>

                <input type="text" class="nodrag mt-3 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-emerald-300" :value="section.title || ''" :placeholder="$t('Enter section title')" @input="updateSection(sectionIndex, 'title', $event.target.value)" />

                <div class="mt-4">
                    <div class="flex items-center justify-between gap-3">
                        <div class="text-sm font-semibold text-slate-900">{{ $t('Rows (At least one row)') }}</div>
                        <button type="button" class="nodrag inline-flex items-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:text-slate-950 disabled:cursor-not-allowed disabled:opacity-60" :disabled="!canAddRow" @click="addRow(sectionIndex)">
                            {{ $t('Add Row') }}
                        </button>
                    </div>

                    <div class="mt-3 space-y-3">
                        <div v-for="(row, rowIndex) in section.rows || []" :key="row.id || rowIndex" class="rounded-2xl border border-slate-200 p-3">
                            <div class="flex items-center justify-between gap-2">
                                <div class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">{{ $t('Row') }} {{ rowIndex + 1 }}</div>
                                <button v-if="(section.rows || []).length > 1" type="button" class="nodrag text-xs font-medium text-rose-600" @click="removeRow(sectionIndex, rowIndex)">{{ $t('Remove') }}</button>
                            </div>

                            <div class="mt-3 grid gap-3">
                                <input type="text" class="nodrag w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-emerald-300" :maxlength="listRowTitleMaxLength" :value="row.title || ''" :placeholder="$t('Enter row title')" @input="updateRow(sectionIndex, rowIndex, 'title', $event.target.value)" />
                                <div class="text-xs text-slate-500">{{ counterLabel(row.title, listRowTitleMaxLength) }}</div>
                                <input type="text" class="nodrag w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-emerald-300" :maxlength="listRowDescriptionMaxLength" :value="row.description || ''" :placeholder="$t('Enter description')" @input="updateRow(sectionIndex, rowIndex, 'description', $event.target.value)" />
                                <div class="text-xs text-slate-500">{{ counterLabel(row.description, listRowDescriptionMaxLength) }}</div>
                                <details class="nodrag rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2.5">
                                    <summary class="nodrag cursor-pointer list-none text-xs font-semibold text-slate-700">
                                        {{ $t('Advanced settings') }}
                                    </summary>
                                    <div class="mt-3 grid gap-2.5">
                                        <div class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">{{ $t('Internal Row ID') }}</div>
                                        <input type="text" class="nodrag w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-emerald-300" :maxlength="listRowIdMaxLength" :value="row.id || ''" :placeholder="$t('Internal row ID (advanced)')" @input="updateRow(sectionIndex, rowIndex, 'id', $event.target.value)" />
                                        <div class="text-xs text-slate-500">{{ counterLabel(row.id, listRowIdMaxLength) }}</div>
                                        <div class="text-[11px] leading-5 text-slate-500">{{ $t('This value is used for internal routing and advanced conditions. Change it only when needed.') }}</div>
                                    </div>
                                </details>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <details class="nodrag rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
            <summary class="nodrag cursor-pointer list-none text-sm font-semibold text-slate-900">
                {{ $t('Advanced settings') }}
            </summary>
            <div class="mt-3 grid gap-3">
                <label class="block">
                    <div class="mb-2 text-sm font-semibold text-slate-900">{{ $t('Header (Optional)') }}</div>
                    <input type="text" class="nodrag w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-emerald-300" :maxlength="interactiveHeaderMaxLength" :value="config.header || ''" :placeholder="$t('Enter text')" @input="setConfigValue('header', $event.target.value)" />
                    <div class="mt-1 text-xs text-slate-500">{{ counterLabel(config.header, interactiveHeaderMaxLength) }}</div>
                </label>

                <label class="block">
                    <div class="mb-2 text-sm font-semibold text-slate-900">{{ $t('Footer Text (Optional)') }}</div>
                    <input type="text" class="nodrag w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-emerald-300" :maxlength="interactiveFooterMaxLength" :value="config.footer || ''" :placeholder="$t('Enter footer text')" @input="setConfigValue('footer', $event.target.value)" />
                    <div class="mt-1 text-xs text-slate-500">{{ counterLabel(config.footer, interactiveFooterMaxLength) }}</div>
                </label>

                <label class="block">
                    <div class="mb-2 text-sm font-semibold text-slate-900">{{ $t('If the customer sends a normal message instead of tapping a reply') }}</div>
                    <select class="nodrag w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-emerald-300" :value="config.invalid_reply_behavior || runtimeInvalidReplyDefaultBehavior" @change="setConfigValue('invalid_reply_behavior', $event.target.value)">
                        <option v-for="option in invalidReplyBehaviorOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                    <div class="mt-2 text-xs text-slate-500">{{ $t('Choose what should happen when the customer types instead of using the interactive choice.') }}</div>
                </label>
            </div>
        </details>
    </div>
</template>

<script setup>
import FlowAutosizeTextarea from '@/Components/AutomationFlows/FlowAutosizeTextarea.vue';
import { useFlowNodeInspectorContext } from '@/Components/AutomationFlows/useFlowNodeInspector.js';

const {
    addButton,
    addRow,
    addSection,
    buttonTitleMaxLength,
    buttons,
    canAddRow,
    canAddSection,
    config,
    counterLabel,
    interactiveBodyMaxLength,
    interactiveFooterMaxLength,
    interactiveHeaderMaxLength,
    invalidReplyBehaviorOptions,
    listButtonLabelMaxLength,
    listRowDescriptionMaxLength,
    listRowIdMaxLength,
    listRowTitleMaxLength,
    listSections,
    maxButtons,
    maxButtonsHelpText,
    maxListRowsHelpText,
    maxListSectionsHelpText,
    maxListTotalRows,
    nodeType,
    removeButton,
    removeRow,
    removeSection,
    runtimeInvalidReplyDefaultBehavior,
    setConfigValue,
    totalListRows,
    updateButton,
    updateRow,
    updateSection,
} = useFlowNodeInspectorContext();
</script>
