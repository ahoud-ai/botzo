import { computed, inject, provide } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    flowNodeCategory,
    flowNodeCustomerOutcome,
    flowNodeHasActiveToggle,
    flowNodeIcon,
    flowNodeSummary,
    flowNodeVisualTone,
    resolveFlowNodeTitle,
} from '@/Components/AutomationFlows/flowBuilderMeta.js';
import { translateFlowMessage } from '@/Components/AutomationFlows/flowBuilderCopy.js';
import { flowNodeLooksLikeMedia, resolveFlowNodeTypeFromData } from '@/Components/AutomationFlows/flowNodePresenter.js';
import { tryUseFlowCanvasRuntime } from '@/Components/AutomationFlows/flowCanvasRuntime.js';

const FLOW_NODE_INSPECTOR_CONTEXT_KEY = Symbol('flow-node-inspector');
const EMPTY_TEXT = '';

const normalizeInteger = (value, fallback = 0) => {
    const parsed = Number.parseInt(value, 10);
    return Number.isFinite(parsed) ? parsed : fallback;
};

const humanFileSize = (size) => {
    const numeric = Number(size || 0);

    if (numeric < 1024) {
        return `${numeric} B`;
    }

    if (numeric < 1024 * 1024) {
        return `${(numeric / 1024).toFixed(1)} KB`;
    }

    return `${(numeric / (1024 * 1024)).toFixed(1)} MB`;
};

const counterLabel = (value, maxLength) => `${String(value || '').length} / ${maxLength}`;
const makeId = (prefix) => `${prefix}-${Math.random().toString(36).slice(2, 8)}`;

export const createFlowNodeInspector = (props) => {
    const { t } = useI18n();
    const canvasRuntime = tryUseFlowCanvasRuntime();

    const nodeType = computed(() => resolveFlowNodeTypeFromData(props.data || {}));
    const config = computed(() => props.data?.config || {});
    const secret = computed(() => props.data?.nodeSecret || {});
    const errors = computed(() => props.data?.errors || []);
    const visibleErrors = computed(() => errors.value.slice(0, 3));
    const hiddenErrorCount = computed(() => Math.max(0, errors.value.length - visibleErrors.value.length));
    const asset = computed(() => props.data?.asset || null);
    const contactFields = computed(() => props.data?.contactFields || []);
    const contactGroups = computed(() => props.data?.contactGroups || []);
    const assignableAgents = computed(() => props.data?.assignableAgents || []);
    const compliance = computed(() => props.data?.compliance || {});
    const runtimeConfig = computed(() => props.data?.runtime || {});
    const handoffCapabilities = computed(() => props.data?.handoffCapabilities || {});
    const interactiveCompliance = computed(() => compliance.value?.interactive || {});
    const buttonCompliance = computed(() => interactiveCompliance.value?.buttons || {});
    const listCompliance = computed(() => interactiveCompliance.value?.list || {});
    const mediaCompliance = computed(() => compliance.value?.media || {});
    const startingSteps = computed(() => props.data?.startingSteps || []);
    const uiEnhanced = computed(() => (
        canvasRuntime?.uiEnhanced?.value
        ?? (props.data?.uiEnhanced !== false)
    ));
    const isTrigger = computed(() => nodeType.value === 'trigger');
    const isInactive = computed(() => flowNodeHasActiveToggle(nodeType.value) && config.value.active === false);
    const looksLikeMediaNode = computed(() => flowNodeLooksLikeMedia(nodeType.value, config.value, props.data || {}));
    const resolvedDisplayType = computed(() => (looksLikeMediaNode.value ? 'send_media' : nodeType.value));

    const interactiveHeaderMaxLength = computed(() => Number(interactiveCompliance.value?.header_text_max_length || 60));
    const interactiveBodyMaxLength = computed(() => Number(interactiveCompliance.value?.body_max_length || 1024));
    const interactiveFooterMaxLength = computed(() => Number(interactiveCompliance.value?.footer_text_max_length || 60));
    const buttonTitleMaxLength = computed(() => Number(buttonCompliance.value?.title_max_length || 20));
    const maxButtons = computed(() => Number(buttonCompliance.value?.max_count || 3));
    const listButtonLabelMaxLength = computed(() => Number(listCompliance.value?.button_label_max_length || 20));
    const maxListSections = computed(() => Number(listCompliance.value?.max_sections || 10));
    const maxListTotalRows = computed(() => Number(listCompliance.value?.max_total_rows || 10));
    const listRowIdMaxLength = computed(() => Number(listCompliance.value?.row_id_max_length || 200));
    const listRowTitleMaxLength = computed(() => Number(listCompliance.value?.row_title_max_length || 24));
    const listRowDescriptionMaxLength = computed(() => Number(listCompliance.value?.row_description_max_length || 72));
    const currentMediaPolicy = computed(() => mediaCompliance.value?.[config.value.media_type || 'image'] || {});

    const maxButtonsHelpText = computed(() => translateFlowMessage(t, 'WhatsApp supports up to :count reply buttons here.', { count: maxButtons.value }));
    const maxListSectionsHelpText = computed(() => translateFlowMessage(t, 'WhatsApp supports up to :count list sections here.', { count: maxListSections.value }));
    const maxListRowsHelpText = computed(() => translateFlowMessage(t, 'WhatsApp list messages support up to :count rows in total across all sections.', { count: maxListTotalRows.value }));
    const runtimeInvalidReplyDefaultBehavior = computed(() => String(runtimeConfig.value?.invalid_reply_default_behavior || 'release_to_fallback'));
    const handoffAssignmentModeOptions = computed(() => ([
        { value: 'auto_assign', label: t('Assign automatically') },
        { value: 'specific_agent', label: t('Choose a specific agent') },
        { value: 'unassigned', label: t('Leave the ticket unassigned') },
    ]));
    const ticketingIsAvailable = computed(() => Boolean(handoffCapabilities.value?.ticketing_active));
    const hasAssignableAgents = computed(() => Boolean(assignableAgents.value.length));
    const aiAssistantAvailable = computed(() => Boolean(
        handoffCapabilities.value?.ai_assistant_module_enabled
        && handoffCapabilities.value?.ai_assistant_active,
    ));

    const invalidReplyBehaviorOptions = computed(() => {
        const allowed = Array.isArray(runtimeConfig.value?.invalid_reply_behaviors) && runtimeConfig.value.invalid_reply_behaviors.length
            ? runtimeConfig.value.invalid_reply_behaviors
            : ['release_to_fallback'];

        const labels = {
            release_to_fallback: t('Keep waiting and let the next responder handle the message'),
            repeat_prompt: t('Repeat this step and keep waiting'),
            end_run: t('End this flow and release the message'),
        };

        return allowed.map((value) => ({
            value,
            label: labels[value] || value,
        }));
    });

    const mediaAcceptLabel = computed(() => String(currentMediaPolicy.value?.accept || '')
        .split(',')
        .map((value) => String(value || '').trim().replace(/^\./, '').toUpperCase())
        .filter(Boolean)
        .join(', '));
    const mediaMaxSizeLabel = computed(() => {
        const maxSizeKb = Number(currentMediaPolicy.value?.max_size_kb || 0);

        if (!maxSizeKb) {
            return '';
        }

        return maxSizeKb >= 1024
            ? `${(maxSizeKb / 1024).toFixed(maxSizeKb % 1024 === 0 ? 0 : 1)} MB`
            : `${maxSizeKb} KB`;
    });
    const mediaMaxSizeHelpText = computed(() => translateFlowMessage(t, 'Up to :size', { size: mediaMaxSizeLabel.value }));
    const showMediaCaption = computed(() => (config.value.media_type || 'image') !== 'audio');
    const flowVariableKeyMaxLength = computed(() => 80);

    const displayTitle = computed(() => resolveFlowNodeTitle({
        nodeType: nodeType.value,
        type: nodeType.value,
        title: props.data?.title,
        label: props.data?.label,
    }, t));
    const nodeCategoryLabel = computed(() => flowNodeCategory(nodeType.value, t));
    const IconComponent = computed(() => flowNodeIcon(nodeType.value));
    const cardTone = computed(() => flowNodeVisualTone(nodeType.value));
    const compactSummary = computed(() => flowNodeSummary(
        resolvedDisplayType.value,
        config.value,
        { asset: asset.value },
        t,
    ));
    const customerOutcomeText = computed(() => flowNodeCustomerOutcome(
        resolvedDisplayType.value,
        config.value,
        { asset: asset.value },
        t,
    ));
    const hiddenIssuesLabel = computed(() => translateFlowMessage(t, '+ :count more issue(s)', { count: hiddenErrorCount.value }));
    const statusBadge = computed(() => {
        if (errors.value.length) {
            return { label: t('Needs attention'), className: 'border-rose-200 bg-rose-50 text-rose-700' };
        }

        if (isInactive.value) {
            return { label: t('Paused'), className: 'border-slate-200 bg-slate-100 text-slate-600' };
        }

        return null;
    });

    const normalizedSaveTarget = computed(() => config.value.save_target || 'contact_field');
    const normalizedUpdateMode = computed(() => config.value.mode || config.value.value_mode || 'save_last_user_message');

    const buttons = computed(() => {
        if (!Array.isArray(config.value.buttons) || !config.value.buttons.length) {
            return [{ id: makeId('button'), title: EMPTY_TEXT }];
        }

        return config.value.buttons;
    });

    const listSections = computed(() => {
        if (!Array.isArray(config.value.sections) || !config.value.sections.length) {
            return [{ title: t('Section 1'), rows: [{ id: makeId('row'), title: EMPTY_TEXT, description: EMPTY_TEXT }] }];
        }

        return config.value.sections;
    });
    const totalListRows = computed(() => listSections.value
        .flatMap((section) => section.rows || [])
        .filter((row) => String(row?.id || '').trim() || String(row?.title || '').trim() || String(row?.description || '').trim())
        .length);
    const canAddRow = computed(() => totalListRows.value < maxListTotalRows.value);
    const canAddSection = computed(() => listSections.value.length < maxListSections.value && canAddRow.value);
    const keywordInput = computed(() => (config.value.keywords || []).filter(Boolean).join(', '));
    const fileAccept = computed(() => ({
        image: mediaCompliance.value?.image?.accept || '.jpg,.jpeg,.png',
        video: mediaCompliance.value?.video?.accept || '.mp4,.3gp',
        audio: mediaCompliance.value?.audio?.accept || '.aac,.amr,.mp3,.m4a,.ogg',
        document: mediaCompliance.value?.document?.accept || '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt',
    }[config.value.media_type || 'image']));

    const setConfigValue = (key, value) => {
        props.data?.onUpdate?.(props.id, {
            config: {
                [key]: value,
            },
        });
    };
    const setSecretValue = (key, value) => {
        props.data?.onUpdateSecret?.(props.id, {
            [key]: value,
        });
    };
    const pruneBranches = (branchIds = []) => {
        props.data?.onPruneBranches?.(props.id, branchIds);
    };
    const remapBranches = (mappings = []) => {
        props.data?.onRemapBranches?.(props.id, mappings);
    };
    const updateStartingStep = (value) => {
        props.data?.onUpdateStartingStep?.(props.id, value);
    };
    const updateKeywords = (value) => {
        setConfigValue('keywords', value.split(',').map((item) => item.trim()).filter(Boolean));
    };
    const uploadSelectedAsset = async (file) => {
        if (!file) {
            return;
        }

        await props.data?.onUploadAsset?.(props.id, file, config.value.media_type || 'image');
    };
    const removeAsset = async () => {
        await props.data?.onRemoveAsset?.(props.id);
    };

    const addButton = () => {
        if (buttons.value.length >= maxButtons.value) {
            return;
        }

        setConfigValue('buttons', [...buttons.value, { id: makeId('button'), title: EMPTY_TEXT }]);
    };
    const updateButton = (index, key, value) => {
        setConfigValue('buttons', buttons.value.map((button, currentIndex) => currentIndex === index ? { ...button, [key]: value } : button));
    };
    const removeButton = (index) => {
        const removedButtonId = String(buttons.value[index]?.id || '').trim();
        if (removedButtonId) {
            pruneBranches([removedButtonId]);
        }

        const next = buttons.value.filter((_button, currentIndex) => currentIndex !== index);
        setConfigValue('buttons', next.length ? next : [{ id: makeId('button'), title: EMPTY_TEXT }]);
    };
    const addSection = () => {
        if (!canAddSection.value) {
            return;
        }

        setConfigValue('sections', [...listSections.value, { title: EMPTY_TEXT, rows: [{ id: makeId('row'), title: EMPTY_TEXT, description: EMPTY_TEXT }] }]);
    };
    const updateSection = (sectionIndex, key, value) => {
        setConfigValue('sections', listSections.value.map((section, currentIndex) => currentIndex === sectionIndex ? { ...section, [key]: value } : section));
    };
    const removeSection = (sectionIndex) => {
        const removedBranchIds = ((listSections.value[sectionIndex]?.rows) || []).map((row) => String(row?.id || '').trim()).filter(Boolean);
        if (removedBranchIds.length) {
            pruneBranches(removedBranchIds);
        }

        const next = listSections.value.filter((_section, currentIndex) => currentIndex !== sectionIndex);
        setConfigValue('sections', next.length ? next : [{ title: EMPTY_TEXT, rows: [{ id: makeId('row'), title: EMPTY_TEXT, description: EMPTY_TEXT }] }]);
    };
    const addRow = (sectionIndex) => {
        if (!canAddRow.value) {
            return;
        }

        setConfigValue('sections', listSections.value.map((section, currentIndex) => currentIndex === sectionIndex ? {
            ...section,
            rows: [...(section.rows || []), { id: makeId('row'), title: EMPTY_TEXT, description: EMPTY_TEXT }],
        } : section));
    };
    const updateRow = (sectionIndex, rowIndex, key, value) => {
        const currentRow = (listSections.value[sectionIndex]?.rows || [])[rowIndex] || {};
        const previousId = String(currentRow.id || '').trim();

        setConfigValue('sections', listSections.value.map((section, currentIndex) => currentIndex === sectionIndex ? {
            ...section,
            rows: (section.rows || []).map((row, currentRowIndex) => currentRowIndex === rowIndex ? { ...row, [key]: value } : row),
        } : section));

        if (key === 'id') {
            const nextId = String(value || '').trim();
            if (previousId !== '' && nextId !== '' && previousId !== nextId) {
                remapBranches([{ from: previousId, to: nextId }]);
            }
        }
    };
    const removeRow = (sectionIndex, rowIndex) => {
        const removedRowId = String(((listSections.value[sectionIndex]?.rows) || [])[rowIndex]?.id || '').trim();
        if (removedRowId) {
            pruneBranches([removedRowId]);
        }

        setConfigValue('sections', listSections.value.map((section, currentIndex) => {
            if (currentIndex !== sectionIndex) {
                return section;
            }

            const rows = (section.rows || []).filter((_row, currentRowIndex) => currentRowIndex !== rowIndex);
            return { ...section, rows: rows.length ? rows : [{ id: makeId('row'), title: EMPTY_TEXT, description: EMPTY_TEXT }] };
        }));
    };

    return {
        t,
        uiEnhanced,
        nodeType,
        config,
        secret,
        errors,
        visibleErrors,
        hiddenErrorCount,
        hiddenIssuesLabel,
        asset,
        contactFields,
        contactGroups,
        assignableAgents,
        runtimeConfig,
        handoffCapabilities,
        startingSteps,
        isTrigger,
        isInactive,
        looksLikeMediaNode,
        interactiveHeaderMaxLength,
        interactiveBodyMaxLength,
        interactiveFooterMaxLength,
        buttonTitleMaxLength,
        maxButtons,
        listButtonLabelMaxLength,
        maxListSections,
        maxListTotalRows,
        listRowIdMaxLength,
        listRowTitleMaxLength,
        listRowDescriptionMaxLength,
        maxButtonsHelpText,
        maxListSectionsHelpText,
        maxListRowsHelpText,
        runtimeInvalidReplyDefaultBehavior,
        handoffAssignmentModeOptions,
        ticketingIsAvailable,
        hasAssignableAgents,
        aiAssistantAvailable,
        invalidReplyBehaviorOptions,
        mediaAcceptLabel,
        mediaMaxSizeLabel,
        mediaMaxSizeHelpText,
        showMediaCaption,
        flowVariableKeyMaxLength,
        displayTitle,
        nodeCategoryLabel,
        IconComponent,
        cardTone,
        compactSummary,
        customerOutcomeText,
        statusBadge,
        normalizedSaveTarget,
        normalizedUpdateMode,
        buttons,
        listSections,
        totalListRows,
        canAddRow,
        canAddSection,
        keywordInput,
        fileAccept,
        setConfigValue,
        setSecretValue,
        updateStartingStep,
        updateKeywords,
        uploadSelectedAsset,
        removeAsset,
        addButton,
        updateButton,
        removeButton,
        addSection,
        updateSection,
        removeSection,
        addRow,
        updateRow,
        removeRow,
        normalizeInteger,
        humanFileSize,
        counterLabel,
    };
};

export const provideFlowNodeInspector = (context) => {
    provide(FLOW_NODE_INSPECTOR_CONTEXT_KEY, context);
};

export const useFlowNodeInspectorContext = () => {
    const context = inject(FLOW_NODE_INSPECTOR_CONTEXT_KEY, null);

    if (!context) {
        throw new Error('Flow node inspector context is not available.');
    }

    return context;
};
