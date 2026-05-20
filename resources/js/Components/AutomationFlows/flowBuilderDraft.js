const EMPTY_TEXT = '';

export const cloneFlowValue = (value, fallback = null) => {
    try {
        return JSON.parse(JSON.stringify(value ?? fallback));
    } catch (error) {
        return fallback;
    }
};

export const makeFlowBuilderUuid = (prefix) => `${prefix}-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`;

export const buildFlowEdge = (sourceId, targetId, branch = 'default') => ({
    id: `edge-${sourceId}-${branch}-${targetId}`,
    source_id: sourceId,
    target_id: targetId,
    branch,
});

export const defaultNodeConfig = (type, t = (value) => value) => {
    switch (type) {
        case 'trigger':
            return { match_mode: 'any_incoming', keywords: [], starting_step: EMPTY_TEXT };
        case 'send_text':
            return { text: EMPTY_TEXT };
        case 'send_media':
            return { media_type: 'image', asset_id: EMPTY_TEXT, caption: EMPTY_TEXT };
        case 'send_buttons':
            return {
                header: EMPTY_TEXT,
                body: EMPTY_TEXT,
                footer: EMPTY_TEXT,
                invalid_reply_behavior: 'release_to_fallback',
                button_type: 'reply_buttons',
                buttons: [
                    { id: makeFlowBuilderUuid('button'), title: EMPTY_TEXT },
                ],
            };
        case 'send_list':
            return {
                header: EMPTY_TEXT,
                body: EMPTY_TEXT,
                footer: EMPTY_TEXT,
                button_label: EMPTY_TEXT,
                invalid_reply_behavior: 'release_to_fallback',
                sections: [
                    {
                        title: t('Section 1'),
                        rows: [
                            { id: makeFlowBuilderUuid('row'), title: EMPTY_TEXT, description: EMPTY_TEXT },
                        ],
                    },
                ],
            };
        case 'save_reply_to_field':
            return { save_target: 'contact_field', field_uuid: EMPTY_TEXT, variable_key: EMPTY_TEXT };
        case 'condition':
            return { source: 'last_user_message', operator: 'equals', value: EMPTY_TEXT };
        case 'add_to_group':
        case 'remove_from_group':
            return { group_uuid: EMPTY_TEXT, active: true };
        case 'update_contact_field':
            return {
                save_target: 'contact_field',
                field_uuid: EMPTY_TEXT,
                variable_key: EMPTY_TEXT,
                mode: 'save_last_user_message',
                source_variable_key: EMPTY_TEXT,
                value: EMPTY_TEXT,
                active: true,
            };
        case 'assign_to_agent':
            return {
                assignment_mode: 'auto_assign',
                agent_user_id: EMPTY_TEXT,
                reopen_closed_ticket: true,
            };
        case 'human_handoff':
            return {
                assignment_mode: 'auto_assign',
                agent_user_id: EMPTY_TEXT,
                reopen_closed_ticket: true,
            };
        case 'handoff_to_ai_assistant':
            return {};
        case 'send_email':
            return { subject: EMPTY_TEXT, body: EMPTY_TEXT, active: true, secret_ref: EMPTY_TEXT };
        case 'delay':
            return { minutes: 5, active: true };
        case 'end':
        default:
            return {};
    }
};
