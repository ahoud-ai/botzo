const KNOWN_FLOW_NODE_TYPES = new Set([
    'trigger',
    'send_text',
    'send_media',
    'send_buttons',
    'send_list',
    'save_reply_to_field',
    'condition',
    'add_to_group',
    'remove_from_group',
    'update_contact_field',
    'assign_to_agent',
    'human_handoff',
    'handoff_to_ai_assistant',
    'send_email',
    'delay',
    'end',
]);

const FLOW_NODE_TYPE_ALIASES = {
    text: 'send_text',
    media: 'send_media',
    interactive_buttons: 'send_buttons',
    interactive_button: 'send_buttons',
    buttons: 'send_buttons',
    interactive_list: 'send_list',
    list: 'send_list',
};

export const normalizeFlowNodeType = (value) => {
    const normalized = String(value || '').trim().toLowerCase().replace(/[\s-]+/g, '_');

    if (!normalized) {
        return '';
    }

    if (KNOWN_FLOW_NODE_TYPES.has(normalized)) {
        return normalized;
    }

    return FLOW_NODE_TYPE_ALIASES[normalized] || '';
};

export const inferFlowNodeTypeFromConfig = (shape = {}) => {
    if (!shape || typeof shape !== 'object') {
        return '';
    }

    if ('match_mode' in shape || 'starting_step' in shape || Array.isArray(shape.keywords)) {
        return 'trigger';
    }

    if (Array.isArray(shape.buttons) || 'button_type' in shape) {
        return 'send_buttons';
    }

    if (Array.isArray(shape.sections) || 'button_label' in shape) {
        return 'send_list';
    }

    if ('media_type' in shape || 'asset_id' in shape) {
        return 'send_media';
    }

    if ('subject' in shape || 'secret_ref' in shape) {
        return 'send_email';
    }

    if ('minutes' in shape) {
        return 'delay';
    }

    if ('source' in shape && 'operator' in shape) {
        return 'condition';
    }

    if ('field_uuid' in shape && ('mode' in shape || 'value_mode' in shape)) {
        return 'update_contact_field';
    }

    if ('assignment_mode' in shape || 'agent_user_id' in shape || 'reopen_closed_ticket' in shape) {
        return 'assign_to_agent';
    }

    return '';
};

export const inferFlowNodeTypeFromText = (...values) => {
    const text = values
        .filter(Boolean)
        .map((value) => String(value).toLowerCase())
        .join(' ');

    if (!text) {
        return '';
    }

    if (text.includes('button') || text.includes('زر')) {
        return 'send_buttons';
    }

    if (text.includes('list') || text.includes('قائمة')) {
        return 'send_list';
    }

    if (text.includes('condition') || text.includes('شرط')) {
        return 'condition';
    }

    if (text.includes('agent') || text.includes('وكيل') || text.includes('service')) {
        return 'assign_to_agent';
    }

    if (text.includes('handoff') || text.includes('خدمة العملاء') || text.includes('ai')) {
        return 'human_handoff';
    }

    if (text.includes('trigger') || text.includes('مشغل') || text.includes('بدء')) {
        return 'trigger';
    }

    if (text.includes('end') || text.includes('نهاية')) {
        return 'end';
    }

    return '';
};

export const resolveFlowNodeTypeFromData = (data = {}) => {
    const directType = normalizeFlowNodeType(data?.nodeType || data?.type);
    if (directType) {
        return directType;
    }

    const fromConfig = inferFlowNodeTypeFromConfig(data?.config || {});
    if (fromConfig) {
        return fromConfig;
    }

    const fromText = inferFlowNodeTypeFromText(
        data?.title,
        data?.label,
        ...(data?.errors || []),
    );

    if (fromText) {
        return fromText;
    }

    return 'send_text';
};

export const flowNodeLooksLikeMedia = (nodeType, config = {}, data = {}) => {
    if (nodeType === 'send_media') {
        return true;
    }

    const shape = config && typeof config === 'object' ? config : {};

    if (
        Object.prototype.hasOwnProperty.call(shape, 'media_type')
        || Object.prototype.hasOwnProperty.call(shape, 'asset_id')
    ) {
        return true;
    }

    const nodeText = `${data?.title || ''} ${data?.label || ''}`.toLowerCase();

    return nodeText.includes('media') || nodeText.includes('وسائط');
};

export const distributeFlowNodeHandles = (items = []) => {
    if (!items.length) {
        return [];
    }

    if (items.length === 1) {
        return [{ ...items[0], top: 50 }];
    }

    return items.map((item, index) => ({
        ...item,
        top: Math.round((index + 1) * (100 / (items.length + 1))),
    }));
};

export const buildFlowNodeSourceHandles = ({
    nodeType,
    buttons = [],
    listSections = [],
    t = (value) => value,
}) => {
    if (['end', 'human_handoff', 'handoff_to_ai_assistant'].includes(nodeType)) {
        return [];
    }

    if (nodeType === 'condition') {
        return distributeFlowNodeHandles([
            { id: 'matched', label: t('Matched') },
            { id: 'unmatched', label: t('Unmatched') },
        ]);
    }

    if (nodeType === 'send_buttons') {
        return distributeFlowNodeHandles(
            buttons
                .filter((button) => button.id)
                .map((button, index) => ({
                    id: button.id,
                    label: button.title || `${t('Button')} ${index + 1}`,
                })),
        );
    }

    if (nodeType === 'send_list') {
        return distributeFlowNodeHandles(
            listSections
                .flatMap((section) => section.rows || [])
                .filter((row) => row.id)
                .map((row, index) => ({
                    id: row.id,
                    label: row.title || `${t('Row')} ${index + 1}`,
                })),
        );
    }

    return [{ id: 'default', top: 50, label: t('Next') }];
};
