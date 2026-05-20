<?php

namespace Tests\Feature;

use Tests\TestCase;

class DeveloperApiDocumentationContractTest extends TestCase
{
    public function test_api_guide_documents_all_protected_developer_api_endpoints(): void
    {
        $routes = $this->documentedRoutes();

        $expectedRoutes = [
            '/api/campaigns',
            '/api/canned-replies',
            '/api/canned-replies/{uuid}',
            '/api/contact-groups',
            '/api/contact-groups/{uuid}',
            '/api/contacts',
            '/api/contacts/{uuid}',
            '/api/send',
            '/api/send/media',
            '/api/send/template',
            '/api/templates',
            '/api/verify',
        ];

        foreach ($expectedRoutes as $route) {
            $this->assertContains($route, $routes, "Missing API guide route: {$route}");
        }
    }

    public function test_api_guide_examples_do_not_contain_unknown_placeholders(): void
    {
        $allowed = ['base_url', 'uuid', 'token'];

        foreach ($this->documentedExamples() as $example) {
            preg_match_all('/\{\{\s*([a-zA-Z_]+)\s*\}\}/', $example, $matches);

            foreach ($matches[1] as $placeholder) {
                $this->assertContains($placeholder, $allowed, "Unexpected API guide placeholder: {$placeholder}");
            }
        }
    }

    public function test_api_guide_contains_standard_error_contract_example(): void
    {
        $examples = implode("\n", $this->documentedExamples());

        $this->assertStringContainsString('"statusCode": 403', $examples);
        $this->assertStringContainsString('"code": "message_limit_reached"', $examples);
        $this->assertStringContainsString('"request_id":', $examples);
    }

    private function documentedRoutes(): array
    {
        return collect(config('apiguide'))
            ->flatMap(fn (array $section) => $section['value'] ?? [])
            ->pluck('route')
            ->unique()
            ->values()
            ->all();
    }

    private function documentedExamples(): array
    {
        return collect(config('apiguide'))
            ->flatMap(fn (array $section) => $section['value'] ?? [])
            ->flatMap(fn (array $endpoint) => array_values($endpoint['request'] ?? []))
            ->filter(fn ($example) => is_string($example))
            ->values()
            ->all();
    }
}
