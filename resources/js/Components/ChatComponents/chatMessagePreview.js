export const parseChatMetadata = (metadata) => {
    if (!metadata) {
        return {};
    }

    if (typeof metadata === 'object') {
        return metadata;
    }

    try {
        const parsed = JSON.parse(metadata);
        return parsed && typeof parsed === 'object' ? parsed : {};
    } catch (error) {
        return {};
    }
};

export const chatContentType = (metadata) => {
    return parseChatMetadata(metadata).type || '';
};

export const interactiveBodyText = (metadata) => {
    const parsed = parseChatMetadata(metadata);

    return parsed?.interactive?.body?.text
        || parsed?.text?.body
        || parsed?.body?.text
        || parsed?.body
        || '';
};

export const interactiveReplyText = (metadata) => {
    const parsed = parseChatMetadata(metadata);
    const interactive = parsed?.interactive || {};

    if (interactive.type === 'button_reply') {
        return interactive?.button_reply?.title || interactive?.button_reply?.id || '';
    }

    if (interactive.type === 'list_reply') {
        return interactive?.list_reply?.title || interactive?.list_reply?.id || '';
    }

    return '';
};

export const chatPreviewText = (metadata, translate = (value) => value) => {
    const parsed = parseChatMetadata(metadata);
    const type = parsed.type || '';

    if (type === 'interactive') {
        const replyText = interactiveReplyText(parsed);
        if (replyText) {
            return replyText;
        }

        const body = interactiveBodyText(parsed);
        const interactiveType = parsed?.interactive?.type;

        if (interactiveType === 'button') {
            return body ? `${translate('Message with buttons')}: ${body}` : translate('Message with buttons');
        }

        if (interactiveType === 'list') {
            return body ? `${translate('Interactive list')}: ${body}` : translate('Interactive list');
        }

        if (interactiveType === 'cta_url') {
            return body ? `${translate('Call to action')}: ${body}` : translate('Call to action');
        }

        return body || translate('Interactive message');
    }

    if (type === 'text') {
        return parsed?.text?.body || '';
    }

    if (type === 'button') {
        return parsed?.button?.text || '';
    }

    return '';
};
