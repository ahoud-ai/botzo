import test from 'node:test';
import assert from 'node:assert/strict';
import { chatPreviewText } from './chatMessagePreview.js';

const t = (value) => value;
const salesButtonTitle = 'Sales';
const appointmentReplyTitle = 'Tomorrow 10 AM';

test('chatPreviewText labels outbound interactive buttons without exposing automation source', () => {
    const metadata = {
        type: 'interactive',
        text: { body: 'Choose a service' },
        interactive: {
            type: 'button',
            body: { text: 'Choose a service' },
            action: {
                buttons: [
                    { type: 'reply', reply: { id: 'sales', title: salesButtonTitle } },
                ],
            },
        },
    };

    assert.equal(chatPreviewText(metadata, t), 'Message with buttons: Choose a service');
});

test('chatPreviewText labels outbound interactive lists', () => {
    const metadata = {
        type: 'interactive',
        text: { body: 'Pick an appointment' },
        interactive: {
            type: 'list',
            body: { text: 'Pick an appointment' },
            action: {
                button: 'Open list',
                sections: [],
            },
        },
    };

    assert.equal(chatPreviewText(JSON.stringify(metadata), t), 'Interactive list: Pick an appointment');
});

test('chatPreviewText keeps inbound interactive replies as the selected customer value', () => {
    assert.equal(chatPreviewText({
        type: 'interactive',
        interactive: {
            type: 'list_reply',
            list_reply: {
                id: 'slot-1',
                title: appointmentReplyTitle,
            },
        },
    }, t), appointmentReplyTitle);
});
