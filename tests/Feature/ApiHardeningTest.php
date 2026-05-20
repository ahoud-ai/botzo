<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiHardeningTest extends TestCase
{
    public function test_api_verify_requires_bearer_token(): void
    {
        $response = $this->getJson('/api/verify');

        $response->assertStatus(401);
        $response->assertJson([
            'statusCode' => 401,
            'code' => 'bearer_token_missing',
        ]);
        $response->assertJsonStructure(['statusCode', 'code', 'message', 'request_id']);
    }

    public function test_api_translations_rejects_php_prefix_locale(): void
    {
        $response = $this->getJson('/api/translations/php_malicious');

        $response->assertStatus(400);
        $response->assertJson([
            'error' => __('Invalid locale'),
        ]);
    }
}
