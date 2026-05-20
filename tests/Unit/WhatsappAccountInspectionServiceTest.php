<?php

namespace Tests\Unit;

use App\Modules\WhatsApp\Infrastructure\CloudApi\WhatsappAccountInspectionService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsappAccountInspectionServiceTest extends TestCase
{
    public function test_get_phone_number_id_returns_the_first_phone_number_payload(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    ['id' => 'phone-1', 'display_phone_number' => '+966501234567'],
                    ['id' => 'phone-2', 'display_phone_number' => '+209999'],
                ],
            ], 200),
        ]);

        $service = new WhatsappAccountInspectionService('token', 'v22.0', '123', '456');

        $response = $service->getPhoneNumberId();

        $this->assertTrue($response->success);
        $this->assertSame('phone-1', $response->data->id);
        $this->assertSame('+966501234567', $response->data->display_phone_number);
    }

    public function test_get_business_profile_returns_error_shape_when_the_request_fails(): void
    {
        Http::fake([
            '*' => Http::response(['error' => ['message' => __('bad token')]], 500),
        ]);

        $service = new WhatsappAccountInspectionService('token', 'v22.0', '123', '456');

        $response = $service->getBusinessProfile();

        $this->assertFalse($response->success);
        $this->assertObjectHasProperty('error', $response->data);
        $this->assertNotEmpty($response->data->error->message);
    }
}
