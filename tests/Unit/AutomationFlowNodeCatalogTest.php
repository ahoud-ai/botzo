<?php

namespace Tests\Unit;

use App\Services\AutomationFlows\AutomationFlowNodeCatalog;
use Tests\TestCase;

class AutomationFlowNodeCatalogTest extends TestCase
{
    public function test_catalog_exposes_builder_scopes_for_safe_flow_builder_planning(): void
    {
        $catalog = new AutomationFlowNodeCatalog();

        $definitions = collect($catalog->all())->keyBy('type');

        $this->assertSame('whatsapp_message', $definitions->get('send_text')['builder_scope']);
        $this->assertTrue($definitions->get('send_list')['whatsapp_native']);
        $this->assertTrue($definitions->get('update_contact_field')['persists_contact_data']);
        $this->assertFalse($definitions->get('save_reply_to_field')['demo_safe']);
        $this->assertSame('service_action', $definitions->get('assign_to_agent')['builder_scope']);
        $this->assertSame('service_action', $definitions->get('human_handoff')['builder_scope']);
        $this->assertSame('service_action', $definitions->get('handoff_to_ai_assistant')['builder_scope']);
        $this->assertTrue($definitions->get('send_email')['requires_external_setup']);
        $this->assertFalse($definitions->has('webhook'));
    }

    public function test_catalog_can_filter_external_and_crm_actions_without_changing_default_behavior(): void
    {
        $catalog = new AutomationFlowNodeCatalog();

        $defaultTypes = collect($catalog->all())->pluck('type')->all();
        $safeTypes = collect($catalog->all([
            'allow_external_actions' => false,
            'allow_crm_actions' => false,
        ]))->pluck('type')->all();

        $this->assertContains('send_email', $defaultTypes);
        $this->assertContains('update_contact_field', $defaultTypes);
        $this->assertNotContains('send_email', $safeTypes);
        $this->assertNotContains('webhook', $defaultTypes);
        $this->assertNotContains('webhook', $safeTypes);
        $this->assertNotContains('save_reply_to_field', $safeTypes);
        $this->assertNotContains('update_contact_field', $safeTypes);
        $this->assertContains('send_text', $safeTypes);
        $this->assertContains('delay', $safeTypes);
        $this->assertContains('assign_to_agent', $safeTypes);
        $this->assertContains('human_handoff', $safeTypes);
        $this->assertContains('handoff_to_ai_assistant', $safeTypes);
    }
}
