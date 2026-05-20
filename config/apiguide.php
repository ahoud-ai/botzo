<?php

$json = static fn (array $payload): string => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

$examples = static function (string $method, string $route, ?array $payload = null) use ($json): array {
    $upperMethod = strtoupper($method === 'delete' ? 'DELETE' : $method);
    $url = '{{base_url}}'.$route;
    $body = $payload ? $json($payload) : null;
    $curlBody = $body ? " \\\n  -H \"Content-Type: application/json\" \\\n  -d '{$body}'" : '';
    $phpBody = $body ? "\n\$body = <<<'JSON'\n{$body}\nJSON;\n" : "\n\$body = null;\n";
    $nodeData = $body ? ",\n  data: {$body}" : '';
    $pythonBody = $body ? "\npayload = {$json($payload)}\n" : "\npayload = None\n";
    $javaBody = $body ? str_replace('"', '\"', $body) : '';
    $rubyBody = $body ? "\nrequest.body = <<~JSON\n{$body}\nJSON\n" : '';
    $rubyRequestClass = $upperMethod === 'DELETE' ? 'Delete' : ucfirst(strtolower($upperMethod));

    return [
        'curl' => "curl -X {$upperMethod} \"{$url}\" \\\n  -H \"Authorization: Bearer {{token}}\"{$curlBody}",
        'php' => "<?php\nuse GuzzleHttp\\Client;\n\n\$client = new Client();{$phpBody}\$response = \$client->request('{$upperMethod}', '{$url}', [\n    'headers' => [\n        'Authorization' => 'Bearer {{token}}',\n        'Accept' => 'application/json',\n        'Content-Type' => 'application/json',\n    ],\n    'body' => \$body,\n]);\n\necho \$response->getBody();\n",
        'nodejs' => "import axios from 'axios';\n\nconst response = await axios({\n  method: '{$method}',\n  url: '{$url}',\n  headers: {\n    Authorization: 'Bearer {{token}}',\n    Accept: 'application/json',\n    'Content-Type': 'application/json'\n  }{$nodeData}\n});\n\nconsole.log(response.data);",
        'python' => "import requests\n\nurl = \"{$url}\"{$pythonBody}headers = {\n    \"Authorization\": \"Bearer {{token}}\",\n    \"Accept\": \"application/json\",\n    \"Content-Type\": \"application/json\",\n}\n\nresponse = requests.request(\"{$upperMethod}\", url, headers=headers, json=payload)\nprint(response.json())",
        'java' => "OkHttpClient client = new OkHttpClient();\nMediaType mediaType = MediaType.parse(\"application/json\");\nRequestBody body = ".($body ? "RequestBody.create(mediaType, \"{$javaBody}\")" : 'RequestBody.create(mediaType, "")').";\nRequest request = new Request.Builder()\n  .url(\"{$url}\")\n  .method(\"{$upperMethod}\", ".($upperMethod === 'GET' ? 'null' : 'body').")\n  .addHeader(\"Authorization\", \"Bearer {{token}}\")\n  .addHeader(\"Accept\", \"application/json\")\n  .addHeader(\"Content-Type\", \"application/json\")\n  .build();\nResponse response = client.newCall(request).execute();",
        'ruby' => "require 'net/http'\nrequire 'json'\n\nuri = URI('{$url}')\nrequest = Net::HTTP::{$rubyRequestClass}.new(uri)\nrequest['Authorization'] = 'Bearer {{token}}'\nrequest['Accept'] = 'application/json'\nrequest['Content-Type'] = 'application/json'{$rubyBody}\nresponse = Net::HTTP.start(uri.hostname, uri.port, use_ssl: uri.scheme == 'https') do |http|\n  http.request(request)\nend\n\nputs response.body",
    ];
};

$errorExample = [
    'statusCode' => 403,
    'code' => 'message_limit_reached',
    'message' => 'You have reached your message limit for the current subscription cycle. Please upgrade your plan to continue sending messages.',
    'request_id' => '8d8e1a0a-2e24-4f44-b79a-91ed1f5d7c00',
];

$errorExamples = [
    'curl' => $json($errorExample),
    'php' => $json($errorExample),
    'nodejs' => $json($errorExample),
    'python' => $json($errorExample),
    'java' => $json($errorExample),
    'ruby' => $json($errorExample),
];

return [
    [
        'title' => 'contacts',
        'value' => [
            [
                'title' => 'Get contact list',
                'method' => 'get',
                'route' => '/api/contacts',
                'request' => $examples('get', '/api/contacts?page=1&per_page=25'),
            ],
            [
                'title' => 'Add contact',
                'method' => 'post',
                'route' => '/api/contacts',
                'request' => $examples('post', '/api/contacts', [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'email' => 'john@example.com',
                    'phone' => '+966500000001',
                    'group' => ['{{uuid}}'],
                ]),
            ],
            [
                'title' => 'Edit contact',
                'method' => 'put',
                'route' => '/api/contacts/{uuid}',
                'request' => $examples('put', '/api/contacts/{{uuid}}', [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'email' => 'john.updated@example.com',
                    'phone' => '+966500000001',
                ]),
            ],
            [
                'title' => 'Delete contact',
                'method' => 'delete',
                'route' => '/api/contacts/{uuid}',
                'request' => $examples('delete', '/api/contacts/{{uuid}}'),
            ],
        ],
    ],
    [
        'title' => 'Contact groups',
        'value' => [
            [
                'title' => 'Get contact group list',
                'method' => 'get',
                'route' => '/api/contact-groups',
                'request' => $examples('get', '/api/contact-groups?page=1&per_page=25'),
            ],
            [
                'title' => 'Add contact group',
                'method' => 'post',
                'route' => '/api/contact-groups',
                'request' => $examples('post', '/api/contact-groups', [
                    'name' => 'VIP leads',
                ]),
            ],
            [
                'title' => 'Edit contact group',
                'method' => 'put',
                'route' => '/api/contact-groups/{uuid}',
                'request' => $examples('put', '/api/contact-groups/{{uuid}}', [
                    'name' => 'Qualified buyers',
                ]),
            ],
            [
                'title' => 'Delete contact group',
                'method' => 'delete',
                'route' => '/api/contact-groups/{uuid}',
                'request' => $examples('delete', '/api/contact-groups/{{uuid}}'),
            ],
        ],
    ],
    [
        'title' => 'Automated replies',
        'value' => [
            [
                'title' => 'Get automated replies list',
                'method' => 'get',
                'route' => '/api/canned-replies',
                'request' => $examples('get', '/api/canned-replies?page=1&per_page=25'),
            ],
            [
                'title' => 'Add automated reply',
                'method' => 'post',
                'route' => '/api/canned-replies',
                'request' => $examples('post', '/api/canned-replies', [
                    'name' => 'Business hours',
                    'trigger' => 'hours',
                    'match_criteria' => 'contains',
                    'response_type' => 'text',
                    'response' => 'Our team is available Sunday to Thursday from 9 AM to 6 PM.',
                ]),
            ],
            [
                'title' => 'Edit automated reply',
                'method' => 'put',
                'route' => '/api/canned-replies/{uuid}',
                'request' => $examples('put', '/api/canned-replies/{{uuid}}', [
                    'name' => 'Business hours',
                    'trigger' => 'working hours',
                    'match_criteria' => 'contains',
                    'response_type' => 'text',
                    'response' => 'Our team is available during official working hours.',
                ]),
            ],
            [
                'title' => 'Delete automated reply',
                'method' => 'delete',
                'route' => '/api/canned-replies/{uuid}',
                'request' => $examples('delete', '/api/canned-replies/{{uuid}}'),
            ],
        ],
    ],
    [
        'title' => 'Messages',
        'value' => [
            [
                'title' => 'Send message',
                'method' => 'post',
                'route' => '/api/send',
                'request' => $examples('post', '/api/send', [
                    'phone' => '+966500000001',
                    'message' => 'Hello John, how can we help you today?',
                    'header' => 'Customer support',
                    'footer' => 'Reply with one of the options below',
                    'buttons' => [
                        ['id' => 'sales', 'title' => 'Sales'],
                        ['id' => 'support', 'title' => 'Support'],
                    ],
                ]),
            ],
            [
                'title' => 'Send media',
                'method' => 'post',
                'route' => '/api/send/media',
                'request' => $examples('post', '/api/send/media', [
                    'phone' => '+966500000001',
                    'media_type' => 'image',
                    'media_url' => 'https://example.com/property.jpg',
                    'caption' => 'Attached property image',
                    'file_name' => 'property.jpg',
                ]),
            ],
            [
                'title' => 'Send template message',
                'method' => 'post',
                'route' => '/api/send/template',
                'request' => $examples('post', '/api/send/template', [
                    'phone' => '+966500000001',
                    'template' => [
                        'name' => 'appointment_reminder',
                        'language' => ['code' => 'ar'],
                        'components' => [
                            [
                                'type' => 'body',
                                'parameters' => [
                                    ['type' => 'text', 'text' => 'Ahmed'],
                                    ['type' => 'text', 'text' => 'Thursday 4 PM'],
                                ],
                            ],
                        ],
                    ],
                ]),
            ],
        ],
    ],
    [
        'title' => 'Campaigns',
        'value' => [
            [
                'title' => 'Create campaign',
                'method' => 'post',
                'route' => '/api/campaigns',
                'request' => $examples('post', '/api/campaigns', [
                    'name' => 'April property follow-up',
                    'template' => '{{uuid}}',
                    'contacts' => 'all',
                    'skip_schedule' => true,
                    'body' => [
                        'parameters' => [
                            ['type' => 'text', 'text' => 'Ahmed'],
                        ],
                    ],
                ]),
            ],
        ],
    ],
    [
        'title' => 'Templates',
        'value' => [
            [
                'title' => 'List templates',
                'method' => 'get',
                'route' => '/api/templates',
                'request' => $examples('get', '/api/templates?page=1&per_page=25'),
            ],
        ],
    ],
    [
        'title' => 'Verification',
        'value' => [
            [
                'title' => 'Verify API key',
                'method' => 'get',
                'route' => '/api/verify',
                'request' => $examples('get', '/api/verify'),
            ],
        ],
    ],
    [
        'title' => 'Error responses',
        'value' => [
            [
                'title' => 'Standard error response',
                'method' => 'error',
                'route' => 'All protected /api/* endpoints',
                'request' => $errorExamples,
            ],
        ],
    ],
];
