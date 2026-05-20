<?php

namespace Tests\Feature;

use Tests\TestCase;

class IntelliReplyRoutesTest extends TestCase
{
    public function test_ai_automation_routes_are_protected_for_guests(): void
    {
        $this->get('/automation/ai')->assertStatus(302);
        $this->post('/automation/ai/activate', ['active' => true])->assertStatus(302);
        $this->post('/automation/ai/setup', [])->assertStatus(302);
        $this->post('/automation/ai/assistant-setup', [])->assertStatus(302);
        $this->get('/automation/chat/suggestion?contact=test-uuid')->assertStatus(302);
        $this->post('/automation/upload/document', [])->assertStatus(302);
        $this->delete('/automation/upload/document/test-uuid')->assertStatus(302);
        $this->post('/automation/contact/test-uuid', ['ai_assistant' => true])->assertStatus(302);
    }

    public function test_admin_ai_setup_route_is_protected_for_guests(): void
    {
        $this->post('/admin/settings/features/ai-assistant', [
            'uuid' => 'dummy',
            'is_active' => 1,
        ])->assertStatus(302);
    }
}
