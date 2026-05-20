<?php

namespace Tests\Unit;

use App\Services\Whatsapp\WhatsappTemplateRequestGuardService;
use Illuminate\Http\Request;
use Tests\TestCase;

class WhatsappTemplateRequestGuardServiceTest extends TestCase
{
    public function test_normalize_template_payload_uppercases_button_type(): void
    {
        $service = new WhatsappTemplateRequestGuardService();
        $request = Request::create('/templates/create', 'POST', [
            'buttons' => [
                ['type' => 'copy_code', 'example' => '123456'],
                ['type' => 'url', 'text' => 'Visit', 'url' => 'https://example.com'],
            ],
        ]);

        $normalized = $service->normalizeTemplateRequestPayload($request);

        $this->assertSame('COPY_CODE', $normalized->input('buttons.0.type'));
        $this->assertSame('URL', $normalized->input('buttons.1.type'));
    }

    public function test_validate_template_payload_rejects_body_over_limit(): void
    {
        $service = new WhatsappTemplateRequestGuardService();
        $request = Request::create('/templates/create', 'POST', [
            'category' => 'MARKETING',
            'body' => ['text' => str_repeat('a', 1025)],
            'buttons' => [],
        ]);

        $response = $service->validateTemplateRequestPayload($request);

        $this->assertNotNull($response);
        $this->assertFalse($response->success);
        $this->assertSame('body.text', $response->data->error->field);
        $this->assertSame(
            __('Template body cannot exceed :max characters.', ['max' => 1024]),
            $response->message
        );
    }

    public function test_validate_template_payload_rejects_invalid_website_url(): void
    {
        $service = new WhatsappTemplateRequestGuardService();
        $request = Request::create('/templates/create', 'POST', [
            'category' => 'MARKETING',
            'body' => ['text' => 'Valid body text'],
            'buttons' => [
                ['type' => 'URL', 'text' => 'Visit', 'url' => 'app.example.com'],
            ],
        ]);

        $response = $service->validateTemplateRequestPayload($request);

        $this->assertNotNull($response);
        $this->assertFalse($response->success);
        $this->assertSame('buttons', $response->data->error->field);
        $this->assertSame(
            __('Website URL must start with http:// or https:// and be valid.'),
            $response->message
        );
    }

    public function test_validate_template_payload_accepts_url_with_variable_placeholder(): void
    {
        $service = new WhatsappTemplateRequestGuardService();
        $request = Request::create('/templates/create', 'POST', [
            'category' => 'MARKETING',
            'body' => ['text' => 'Valid body text'],
            'buttons' => [
                ['type' => 'URL', 'text' => 'Visit', 'url' => 'https://app.example.com/{{1}}'],
            ],
        ]);

        $response = $service->validateTemplateRequestPayload($request);

        $this->assertNull($response);
    }

    public function test_build_template_api_error_message_prefers_details_when_available(): void
    {
        $service = new WhatsappTemplateRequestGuardService();
        $apiError = (object) [
            'error' => (object) [
                'message' => __('Invalid parameter'),
                'error_data' => (object) [
                    'details' => 'Param body has invalid format',
                ],
            ],
        ];

        $message = $service->buildTemplateApiErrorMessage($apiError);

        $this->assertStringContainsString('Param body has invalid format', $message);
    }
}
