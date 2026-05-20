<?php

namespace Tests\Unit;

use App\Support\SaClientPlanProfile;
use Tests\TestCase;

class SaClientPlanProfileTest extends TestCase
{
    public function test_sanitize_plan_metadata_strips_mobile_app_and_firebase_payloads(): void
    {
        $metadata = SaClientPlanProfile::sanitizePlanMetadata([
            'campaign_limit' => 20,
            'mobile_app' => true,
            'mobile_push' => true,
            'mobile_device_limit' => 5,
            'firebase_project_id' => 'previous-project',
            'firebase_credentials' => ['client_email' => 'previous@example.com'],
            'firebase_web_api_key' => 'previous-key',
            'fcm_server_key' => 'previous-fcm',
            'push_notifications' => true,
            'nested' => [
                'mobile_app' => true,
                'firebase_project_id' => 'nested-project',
                'allowed_note' => 'keep',
            ],
            'addons' => [
                'Flow builder' => true,
                'Mobile App' => true,
                'Firebase Push' => true,
            ],
        ]);

        $this->assertSame(20, $metadata['campaign_limit']);
        $this->assertSame('keep', $metadata['nested']['allowed_note']);
        $this->assertSame(['Flow builder' => true], $metadata['addons']);

        foreach ([
            'mobile_app',
            'mobile_push',
            'mobile_device_limit',
            'firebase_project_id',
            'firebase_credentials',
            'firebase_web_api_key',
            'fcm_server_key',
            'push_notifications',
        ] as $key) {
            $this->assertArrayNotHasKey($key, $metadata);
        }

        $this->assertArrayNotHasKey('mobile_app', $metadata['nested']);
        $this->assertArrayNotHasKey('firebase_project_id', $metadata['nested']);
    }
}
