import { translateFlowMessage } from '@/Components/AutomationFlows/flowBuilderCopy.js';

export const isEmailSecretComplete = (secret = {}) => {
    const port = Number(secret.port || 0);
    const encryption = String(secret.encryption || 'tls').trim().toLowerCase();
    const fromEmail = String(secret.from_email || '').trim();

    return Boolean(
        String(secret.host || '').trim()
        && port >= 1
        && port <= 65535
        && String(secret.username || '').trim()
        && (secret.has_password || String(secret.password || '').trim())
        && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(fromEmail)
        && ['tls', 'ssl', 'starttls', 'none'].includes(encryption)
    );
};

export const validationErrorsFromResponse = (error) => {
    const responseData = error?.response?.data || {};
    const graphErrors = Array.isArray(responseData?.errors?.graph) ? responseData.errors.graph : [];
    const inlineErrors = Array.isArray(responseData?.errors) ? responseData.errors : [];
    const message = typeof responseData?.message === 'string' ? responseData.message.trim() : '';

    return [...graphErrors, ...inlineErrors, ...(message ? [message] : [])]
        .map((entry) => String(entry || '').trim())
        .filter(Boolean);
};

export const saveStateLabelFor = (state, t) => ({
    dirty: t('Draft not saved yet'),
    autosaving: t('Saving draft in the background...'),
    saving: t('Saving...'),
    saved: t('Draft saved'),
    error: t('Unable to save draft'),
}[state] || t('Draft saved'));

const DEFAULT_WHATSAPP_COMPLIANCE = {
    interactive: {
        header_text_max_length: 60,
        body_max_length: 1024,
        footer_text_max_length: 60,
        buttons: {
            max_count: 3,
            title_max_length: 20,
        },
        list: {
            button_label_max_length: 20,
            max_sections: 10,
            max_total_rows: 10,
            row_id_max_length: 200,
            row_title_max_length: 24,
            row_description_max_length: 72,
        },
    },
    media: {
        image: {
            max_size_kb: 5 * 1024,
            accept: '.jpg,.jpeg,.png',
        },
        video: {
            max_size_kb: 16 * 1024,
            accept: '.mp4,.3gp',
        },
        audio: {
            max_size_kb: 16 * 1024,
            accept: '.aac,.amr,.mp3,.m4a,.ogg',
        },
        document: {
            max_size_kb: 100 * 1024,
            accept: '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt',
        },
    },
};

const normalizedButtonIds = (config = {}) => (config.buttons || [])
    .map((button) => String(button?.id || '').trim())
    .filter(Boolean);

const normalizedListRows = (config = {}) => (config.sections || [])
    .flatMap((section) => section?.rows || [])
    .map((row) => ({
        id: String(row?.id || '').trim(),
        title: String(row?.title || '').trim(),
        sectionTitle: String(row?.section_title || '').trim(),
    }));

const normalizedListRowIds = (config = {}) => normalizedListRows(config)
    .map((row) => row.id)
    .filter(Boolean);

const hasDuplicateValues = (values = []) => new Set(values).size !== values.length;
const exceedsLength = (value, maxLength) => maxLength > 0 && String(value || '').trim().length > maxLength;
const DEFAULT_BUILDER_POLICY = {
    channel: 'whatsapp',
    whatsapp_only_mode: true,
    allow_external_actions: false,
    allow_crm_actions: true,
};
const normalizeCompliance = (compliance = {}) => ({
    ...DEFAULT_WHATSAPP_COMPLIANCE,
    ...compliance,
    interactive: {
        ...DEFAULT_WHATSAPP_COMPLIANCE.interactive,
        ...(compliance?.interactive || {}),
        buttons: {
            ...DEFAULT_WHATSAPP_COMPLIANCE.interactive.buttons,
            ...(compliance?.interactive?.buttons || {}),
        },
        list: {
            ...DEFAULT_WHATSAPP_COMPLIANCE.interactive.list,
            ...(compliance?.interactive?.list || {}),
        },
    },
    media: {
        ...DEFAULT_WHATSAPP_COMPLIANCE.media,
        ...(compliance?.media || {}),
    },
});
const normalizeBuilderPolicy = (policy = {}) => ({
    ...DEFAULT_BUILDER_POLICY,
    ...policy,
    allow_external_actions: policy?.whatsapp_only_mode
        ? false
        : (policy?.allow_external_actions ?? DEFAULT_BUILDER_POLICY.allow_external_actions),
});
const EXTERNAL_ACTION_TYPES = ['send_email'];
const CRM_ACTION_TYPES = ['save_reply_to_field', 'add_to_group', 'remove_from_group', 'update_contact_field'];
const isBlockedByBuilderPolicy = (type, policy = {}) => {
    const normalized = normalizeBuilderPolicy(policy);

    if (!normalized.allow_external_actions && EXTERNAL_ACTION_TYPES.includes(type)) {
        return true;
    }

    if (!normalized.allow_crm_actions && CRM_ACTION_TYPES.includes(type)) {
        return true;
    }

    return false;
};

const VALID_INVALID_REPLY_BEHAVIORS = [
    'release_to_fallback',
    'repeat_prompt',
    'end_run',
];

const normalizeFlowVariableKey = (value) => String(value || '').trim().replace(/\s+/g, ' ');
const hasValidFlowVariableKey = (value) => {
    const key = normalizeFlowVariableKey(value);

    return key !== '' && key.length <= 80;
};

const hasCircularPath = (graphNodes = [], graphEdges = []) => {
    const trigger = graphNodes.find((node) => node.type === 'trigger');
    if (!trigger?.id) {
        return false;
    }

    const edgeSourceId = (edge) => String(edge?.source_id || edge?.source || '').trim();
    const edgeTargetId = (edge) => String(edge?.target_id || edge?.target || '').trim();

    const adjacency = graphEdges.reduce((carry, edge) => {
        const sourceId = edgeSourceId(edge);
        const targetId = edgeTargetId(edge);

        if (!sourceId || !targetId) {
            return carry;
        }

        if (!carry[sourceId]) {
            carry[sourceId] = [];
        }

        carry[sourceId].push(targetId);

        return carry;
    }, {});

    const visited = new Set();
    const visiting = new Set();

    const detectCycle = (nodeId) => {
        if (visiting.has(nodeId)) {
            return true;
        }

        if (visited.has(nodeId)) {
            return false;
        }

        visiting.add(nodeId);

        const hasCycle = (adjacency[nodeId] || []).some((targetId) => detectCycle(targetId));

        visiting.delete(nodeId);
        visited.add(nodeId);

        return hasCycle;
    };

    return detectCycle(trigger.id);
};

const edgeSourceId = (edge) => String(edge?.source_id || edge?.source || '').trim();
const edgeTargetId = (edge) => String(edge?.target_id || edge?.target || '').trim();

const reachableNodeIdsFromTrigger = (graphNodes = [], graphEdges = []) => {
    const trigger = graphNodes.find((node) => node.type === 'trigger');
    const triggerId = String(trigger?.id || '').trim();

    if (!triggerId) {
        return new Set();
    }

    const adjacency = graphEdges.reduce((carry, edge) => {
        const sourceId = edgeSourceId(edge);
        const targetId = edgeTargetId(edge);

        if (!sourceId || !targetId) {
            return carry;
        }

        if (!carry[sourceId]) {
            carry[sourceId] = [];
        }

        carry[sourceId].push(targetId);

        return carry;
    }, {});

    const visited = new Set();
    const queue = [triggerId];

    while (queue.length > 0) {
        const currentId = queue.shift();
        if (!currentId || visited.has(currentId)) {
            continue;
        }

        visited.add(currentId);

        (adjacency[currentId] || []).forEach((nextId) => {
            if (!visited.has(nextId)) {
                queue.push(nextId);
            }
        });
    }

    return visited;
};

export const buildNodeErrors = ({
    graphNode,
    graphEdges = [],
    assets = {},
    nodeSecrets = {},
    compliance = {},
    builderPolicy = {},
    assignableAgents = [],
    handoffCapabilities = {},
    t,
}) => {
    const type = graphNode?.type;
    const config = graphNode?.config || {};
    const errors = [];
    const policy = normalizeCompliance(compliance);
    if (isBlockedByBuilderPolicy(type, builderPolicy)) {
        errors.push(t('Flow Builder policy blocks this step type for the current workspace.'));
    }
    const interactivePolicy = policy.interactive || {};
    const buttonPolicy = interactivePolicy.buttons || {};
    const listPolicy = interactivePolicy.list || {};

    if (type === 'trigger') {
        if (!['any_incoming', 'first_in_conversation', 'keyword_match'].includes(config.match_mode || '')) {
            errors.push(t('Choose how this automation should start from an incoming WhatsApp message.'));
        }
        if ((config.match_mode || '') === 'keyword_match' && !(config.keywords || []).filter(Boolean).length) {
            errors.push(t('Add at least one keyword before publishing a keyword-based trigger.'));
        }
        const hasOutgoingEdges = graphEdges.some((edge) => edgeSourceId(edge) === String(graphNode?.id || ''));
        if (!hasOutgoingEdges) {
            errors.push(t('Choose the first step that should start after the trigger.'));
        }
    }

    if (type === 'send_text' && !String(config.text || '').trim()) {
        errors.push(t('Text messages need content before publishing.'));
    }

    if (type === 'send_media') {
        if (!['image', 'video', 'audio', 'document'].includes(config.media_type || '')) {
            errors.push(t('Choose the media type that should be sent to the customer.'));
        }
        if (!config.asset_id || !assets[config.asset_id]) {
            errors.push(t('Upload a media file before publishing this step.'));
        }
    }

    if (type === 'send_buttons') {
        const buttons = (config.buttons || []).filter((button) => button.id && button.title);
        if (!String(config.body || '').trim() || !buttons.length) {
            errors.push(t('Interactive button messages need a body and at least one button.'));
        }
        if (!VALID_INVALID_REPLY_BEHAVIORS.includes(String(config.invalid_reply_behavior || 'release_to_fallback'))) {
            errors.push(t('Choose how this step should react when the customer sends a normal message instead of tapping a reply.'));
        }
        if (buttons.length > Number(buttonPolicy.max_count || 3)) {
            errors.push(translateFlowMessage(t, 'WhatsApp reply buttons support up to :count choices per message.', { count: buttonPolicy.max_count || 3 }));
        }
        if (exceedsLength(config.header, Number(interactivePolicy.header_text_max_length || 60))) {
            errors.push(translateFlowMessage(t, 'Interactive headers support up to :count characters.', { count: interactivePolicy.header_text_max_length || 60 }));
        }
        if (exceedsLength(config.body, Number(interactivePolicy.body_max_length || 1024))) {
            errors.push(translateFlowMessage(t, 'Interactive message bodies support up to :count characters.', { count: interactivePolicy.body_max_length || 1024 }));
        }
        if (exceedsLength(config.footer, Number(interactivePolicy.footer_text_max_length || 60))) {
            errors.push(translateFlowMessage(t, 'Interactive footers support up to :count characters.', { count: interactivePolicy.footer_text_max_length || 60 }));
        }
        buttons.forEach((button, index) => {
            if (exceedsLength(button.title, Number(buttonPolicy.title_max_length || 20))) {
                errors.push(translateFlowMessage(t, 'Reply button :number supports up to :count characters.', {
                    number: index + 1,
                    count: buttonPolicy.title_max_length || 20,
                }));
            }
        });
        if (hasDuplicateValues(normalizedButtonIds(config))) {
            errors.push(t('Each button reply needs a unique internal ID before publishing.'));
        }
        const branches = graphEdges
            .filter((edge) => edgeSourceId(edge) === String(graphNode?.id || ''))
            .map((edge) => String(edge?.branch || '').trim())
            .filter(Boolean);
        normalizedButtonIds(config).forEach((buttonId) => {
            if (!branches.includes(buttonId)) {
                errors.push(t('Connect every button reply to its own next step before publishing.'));
            }
        });
    }

    if (type === 'send_list') {
        const hasRows = (config.sections || []).some((section) => (section.rows || []).some((row) => row.id && row.title));
        if (!String(config.body || '').trim() || !String(config.button_label || '').trim() || !hasRows) {
            errors.push(t('Interactive lists need a body, button label, and at least one row.'));
        }
        if (!VALID_INVALID_REPLY_BEHAVIORS.includes(String(config.invalid_reply_behavior || 'release_to_fallback'))) {
            errors.push(t('Choose how this step should react when the customer sends a normal message instead of tapping a reply.'));
        }
        if ((config.sections || []).filter((section) => (section.rows || []).length).length > Number(listPolicy.max_sections || 10)) {
            errors.push(translateFlowMessage(t, 'WhatsApp list messages support up to :count sections.', { count: listPolicy.max_sections || 10 }));
        }
        const totalRows = (config.sections || [])
            .flatMap((section) => section.rows || [])
            .filter((row) => String(row?.id || '').trim() || String(row?.title || '').trim() || String(row?.description || '').trim())
            .length;
        if (totalRows > Number(listPolicy.max_total_rows || 10)) {
            errors.push(translateFlowMessage(t, 'WhatsApp list messages support up to :count rows in total across all sections.', { count: listPolicy.max_total_rows || 10 }));
        }
        if (exceedsLength(config.header, Number(interactivePolicy.header_text_max_length || 60))) {
            errors.push(translateFlowMessage(t, 'Interactive headers support up to :count characters.', { count: interactivePolicy.header_text_max_length || 60 }));
        }
        if (exceedsLength(config.body, Number(interactivePolicy.body_max_length || 1024))) {
            errors.push(translateFlowMessage(t, 'Interactive message bodies support up to :count characters.', { count: interactivePolicy.body_max_length || 1024 }));
        }
        if (exceedsLength(config.footer, Number(interactivePolicy.footer_text_max_length || 60))) {
            errors.push(translateFlowMessage(t, 'Interactive footers support up to :count characters.', { count: interactivePolicy.footer_text_max_length || 60 }));
        }
        if (exceedsLength(config.button_label, Number(listPolicy.button_label_max_length || 20))) {
            errors.push(translateFlowMessage(t, 'List button labels support up to :count characters.', { count: listPolicy.button_label_max_length || 20 }));
        }
        const hasRowsWithoutSectionTitle = (config.sections || []).some((section) => {
            const hasSectionRows = (section.rows || []).some((row) => row.id && row.title);
            return hasSectionRows && !String(section.title || '').trim();
        });
        if (hasRowsWithoutSectionTitle) {
            errors.push(t('Every list section needs a title before publishing.'));
        }
        (config.sections || []).forEach((section, sectionIndex) => {
            (section.rows || []).forEach((row, rowIndex) => {
                if (exceedsLength(row.id, Number(listPolicy.row_id_max_length || 200))) {
                    errors.push(translateFlowMessage(t, 'List row IDs support up to :count characters.', { count: listPolicy.row_id_max_length || 200 }));
                }
                if (exceedsLength(row.title, Number(listPolicy.row_title_max_length || 24))) {
                    errors.push(translateFlowMessage(t, 'Row :row in section :section supports up to :count characters for the title.', {
                        row: rowIndex + 1,
                        section: sectionIndex + 1,
                        count: listPolicy.row_title_max_length || 24,
                    }));
                }
                if (exceedsLength(row.description, Number(listPolicy.row_description_max_length || 72))) {
                    errors.push(translateFlowMessage(t, 'Row :row in section :section supports up to :count characters for the description.', {
                        row: rowIndex + 1,
                        section: sectionIndex + 1,
                        count: listPolicy.row_description_max_length || 72,
                    }));
                }
            });
        });
        if (hasDuplicateValues(normalizedListRowIds(config))) {
            errors.push(t('Each list choice needs a unique internal ID before publishing.'));
        }
        const branches = graphEdges
            .filter((edge) => edgeSourceId(edge) === String(graphNode?.id || ''))
            .map((edge) => String(edge?.branch || '').trim())
            .filter(Boolean);
        normalizedListRowIds(config).forEach((rowId) => {
            if (!branches.includes(rowId)) {
                errors.push(t('Connect every list choice to its own next step before publishing.'));
            }
        });
    }

    if (type === 'save_reply_to_field') {
        const saveTarget = String(config.save_target || 'contact_field');

        if (!['contact_field', 'session_variable'].includes(saveTarget)) {
            errors.push(t('Choose where this step should save the customer reply.'));
        }

        if (saveTarget === 'contact_field' && !config.field_uuid) {
            errors.push(t('Choose the contact field that should store the customer reply.'));
        }

        if (saveTarget === 'session_variable' && !hasValidFlowVariableKey(config.variable_key)) {
            errors.push(t('Choose a valid flow variable key for this reply step.'));
        }
    }

    if (['add_to_group', 'remove_from_group'].includes(type) && !config.group_uuid) {
        errors.push(t('Choose a valid contact group for this action.'));
    }

    if (type === 'update_contact_field') {
        const saveTarget = String(config.save_target || 'contact_field');

        if (!['contact_field', 'session_variable'].includes(saveTarget)) {
            errors.push(t('Choose where this step should save its value.'));
        }

        if (saveTarget === 'contact_field' && !config.field_uuid) {
            errors.push(t('Choose the contact field that should be updated.'));
        }

        if (saveTarget === 'session_variable' && !hasValidFlowVariableKey(config.variable_key)) {
            errors.push(t('Choose a valid flow variable key for this step.'));
        }

        const mode = config.mode || config.value_mode || 'save_last_user_message';
        if (!['save_last_user_message', 'last_input', 'static', 'session_variable'].includes(mode)) {
            errors.push(t('Choose how the contact field should be updated.'));
        }
        if (mode === 'session_variable' && !hasValidFlowVariableKey(config.source_variable_key)) {
            errors.push(t('Choose which flow variable should supply the value for this step.'));
        }
        if (mode === 'static' && !String(config.value || '').trim()) {
            errors.push(t('Add the static value that should be stored in the contact field.'));
        }
    }

    if (type === 'assign_to_agent' || type === 'human_handoff') {
        const ticketingActive = Boolean(handoffCapabilities?.ticketing_active);
        const assignmentMode = String(config.assignment_mode || 'auto_assign');
        const validModes = ['auto_assign', 'specific_agent', 'unassigned'];
        const hasOutgoingEdges = graphEdges.some((edge) => edgeSourceId(edge) === String(graphNode?.id || ''));

        if (!ticketingActive) {
            errors.push(
                type === 'human_handoff'
                    ? t('Activate ticketing before handing the conversation to customer service.')
                    : t('Activate ticketing before assigning the conversation to a service agent.'),
            );
        }

        if (!validModes.includes(assignmentMode)) {
            errors.push(t('Choose how this step should assign the conversation.'));
        }

        if (assignmentMode === 'specific_agent') {
            const agentUserId = Number(config.agent_user_id || 0);
            const availableAgentIds = assignableAgents
                .map((agent) => Number(agent?.user_id || 0))
                .filter((value) => value > 0);

            if (!availableAgentIds.includes(agentUserId)) {
                errors.push(t('Choose a valid service agent for this step.'));
            }
        }

        if (assignmentMode === 'auto_assign' && !assignableAgents.length) {
            errors.push(t('Add at least one team member before using automatic assignment here.'));
        }

        if (type === 'human_handoff' && hasOutgoingEdges) {
            errors.push(t('Handoff steps should finish the journey instead of connecting to another step.'));
        }
    }

    if (type === 'handoff_to_ai_assistant') {
        const hasOutgoingEdges = graphEdges.some((edge) => edgeSourceId(edge) === String(graphNode?.id || ''));

        if (!handoffCapabilities?.ai_assistant_module_enabled || !handoffCapabilities?.ai_assistant_active) {
            errors.push(t('Activate the built-in AI assistant before publishing an AI handoff step.'));
        }

        if (hasOutgoingEdges) {
            errors.push(t('Handoff steps should finish the journey instead of connecting to another step.'));
        }
    }

    if (type === 'send_email') {
        if (!String(config.subject || '').trim() || !String(config.body || '').trim()) {
            errors.push(t('Email steps need a subject and body before publishing.'));
        }
        if (!isEmailSecretComplete(nodeSecrets[graphNode.id] || {})) {
            errors.push(t('Complete the SMTP settings for this email step before publishing.'));
        }
    }

    if (type === 'delay' && Number(config.minutes || 0) < 1) {
        errors.push(t('Delay steps need a duration of at least one minute.'));
    }

    if (type === 'condition') {
        if (String(config.source || 'last_user_message') === 'flow_variable' && !hasValidFlowVariableKey(config.variable_key)) {
            errors.push(t('Choose a valid flow variable key for this condition.'));
        }
        const branches = graphEdges
            .filter((edge) => edgeSourceId(edge) === String(graphNode?.id || ''))
            .map((edge) => String(edge?.branch || '').trim())
            .filter(Boolean);
        if (!branches.includes('matched') || !branches.includes('unmatched')) {
            errors.push(t('Condition steps need both matched and unmatched branches.'));
        }
    }

    return [...new Set(errors)];
};

export const buildValidationSummary = ({
    graphNodes = [],
    graphEdges = [],
    assets = {},
    nodeSecrets = {},
    compliance = {},
    builderPolicy = {},
    runtime = {},
    assignableAgents = [],
    handoffCapabilities = {},
    t,
}) => {
    const errors = [];
    const warnings = [];
    const triggerCount = graphNodes.filter((node) => node.type === 'trigger').length;

    if (triggerCount !== 1) {
        errors.push(t('Each automation needs exactly one trigger node.'));
    }

    if (triggerCount === 1) {
        const reachableIds = reachableNodeIdsFromTrigger(graphNodes, graphEdges);
        const hasDisconnectedNodes = graphNodes.some((node) => {
            const nodeId = String(node?.id || '').trim();

            return nodeId !== '' && !reachableIds.has(nodeId);
        });

        if (hasDisconnectedNodes) {
            errors.push(t('Remove disconnected steps or reconnect them to the main journey.'));
        }
    }

    graphNodes.forEach((node) => {
        buildNodeErrors({
            graphNode: node,
            graphEdges,
            assets,
            nodeSecrets,
            compliance,
            builderPolicy,
            assignableAgents,
            handoffCapabilities,
            t,
        }).forEach((error) => errors.push(error));
    });

    if (hasCircularPath(graphNodes, graphEdges)) {
        errors.push(t('Remove circular paths before publishing this automation.'));
    }

    const whatsappRuntime = runtime?.whatsapp || {};
    const enforceCustomerCareWindow = whatsappRuntime?.enforce_customer_care_window !== false;
    const customerCareWindowHours = Number(whatsappRuntime?.customer_care_window_hours || 24);
    const hasFreeformWhatsappSteps = graphNodes.some((node) => ['send_text', 'send_media', 'send_buttons', 'send_list'].includes(String(node?.type || '')));

    if (enforceCustomerCareWindow && hasFreeformWhatsappSteps) {
        warnings.push(translateFlowMessage(t, 'Free-form WhatsApp steps send only inside the customer care window (:hours hours). If this journey resumes later, send a template message first.', {
            hours: customerCareWindowHours > 0 ? customerCareWindowHours : 24,
        }));
    }

    return {
        valid: errors.length === 0,
        errors: [...new Set(errors)],
        warnings: [...new Set(warnings)],
    };
};
