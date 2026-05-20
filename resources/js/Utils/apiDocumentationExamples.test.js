import test from 'node:test';
import assert from 'node:assert/strict';
import { resolveApiDocExample } from './apiDocumentationExamples.js';

test('resolveApiDocExample replaces all supported placeholders', () => {
    const result = resolveApiDocExample(
        'curl {{base_url}}/api/contacts/{{uuid}}/{{id}} -H "Authorization: Bearer {{token}}"',
        'https://botzo.test/',
        {
            uuid: 'contact-uuid',
            id: 'group-uuid',
            token: 'secret-token',
        },
    );

    assert.equal(
        result,
        'curl https://botzo.test/api/contacts/contact-uuid/group-uuid -H "Authorization: Bearer secret-token"',
    );
});

test('resolveApiDocExample leaves unknown placeholders untouched', () => {
    const result = resolveApiDocExample('{{base_url}}/{{unknown}}', 'https://botzo.test');

    assert.equal(result, 'https://botzo.test/{{unknown}}');
});
