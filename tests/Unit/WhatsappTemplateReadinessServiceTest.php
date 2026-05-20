<?php

namespace Tests\Unit;

use App\Services\Whatsapp\WhatsappTemplateReadinessService;
use Tests\TestCase;

class WhatsappTemplateReadinessServiceTest extends TestCase
{
    public function test_it_flags_pending_account_or_number_status(): void
    {
        $service = new WhatsappTemplateReadinessService();

        $result = $service->buildForMetadata([
            'whatsapp' => [
                'account_review_status' => 'PENDING',
                'number_status' => 'PENDING',
                'code_verification_status' => 'VERIFIED',
            ],
        ]);

        $this->assertSame(
            __('Your WhatsApp number or account is still pending activation/review in Meta. Template creation may fail until both statuses are completed.'),
            $result['hint']
        );
        $this->assertStringContainsString('PENDING', $result['status_summary']);
    }

    public function test_it_flags_unverified_phone_numbers_first(): void
    {
        $service = new WhatsappTemplateReadinessService();

        $result = $service->buildForMetadata([
            'whatsapp' => [
                'account_review_status' => 'APPROVED',
                'number_status' => 'CONNECTED',
                'code_verification_status' => 'UNVERIFIED',
            ],
        ]);

        $this->assertSame(
            __('Your WhatsApp phone number is not fully verified yet. Complete verification before creating templates.'),
            $result['hint']
        );
    }

    public function test_it_flags_limited_test_review_state(): void
    {
        $service = new WhatsappTemplateReadinessService();

        $result = $service->buildForMetadata([
            'whatsapp' => [
                'account_review_status' => 'APPROVED',
                'number_status' => 'CONNECTED',
                'code_verification_status' => 'VERIFIED',
                'name_status' => 'AVAILABLE_WITHOUT_REVIEW',
                'quality_rating' => 'UNKNOWN',
            ],
        ]);

        $this->assertSame(
            __('This WhatsApp number still appears to be in a limited test/review state. If Meta rejects template creation, try again after account review is completed or use a fully activated business number.'),
            $result['hint']
        );
    }

    public function test_it_returns_no_hint_for_ready_accounts(): void
    {
        $service = new WhatsappTemplateReadinessService();

        $result = $service->buildForMetadata([
            'whatsapp' => [
                'account_review_status' => 'APPROVED',
                'number_status' => 'CONNECTED',
                'code_verification_status' => 'VERIFIED',
                'name_status' => 'APPROVED',
                'quality_rating' => 'GREEN',
            ],
        ]);

        $this->assertNull($result['hint']);
        $this->assertStringContainsString('APPROVED', $result['status_summary']);
    }
}
