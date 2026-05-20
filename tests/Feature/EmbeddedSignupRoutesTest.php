<?php

namespace Tests\Feature;

use Tests\TestCase;

class EmbeddedSignupRoutesTest extends TestCase
{
    public function test_exchange_code_endpoint_is_protected_for_guests(): void
    {
        $response = $this->post('/whatsapp/exchange-code', [
            'token' => 'dummy-code',
        ]);

        $response->assertStatus(302);
    }

    public function test_org_toggle_endpoint_is_protected_for_guests(): void
    {
        $response = $this->post('/settings/features/embedded-signup/toggle', [
            'enabled' => true,
        ]);

        $response->assertStatus(302);
    }

    public function test_admin_health_endpoint_is_protected_for_guests(): void
    {
        $response = $this->get('/admin/settings/features/embedded-signup/health');

        $response->assertStatus(302);
    }
}
