<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Services\Whatsapp\WhatsappTokenVault;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class WhatsappRegisterPhonePinTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    public function test_first_registration_generates_a_pin_and_stores_it_encrypted(): void
    {
        [, $organization] = $this->createOwnerContext(['whatsapp' => ['phone_number_id' => 'phone-pin']]);
        $sentPin = null;

        $service = $this->serviceExpectingRegistration($organization, true, function (string $pin) use (&$sentPin): void {
            $sentPin = $pin;
        });

        $this->assertTrue($service->registerPhone()->success);

        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $sentPin);
        $this->assertSame($sentPin, $this->storedPin($organization));
        $this->assertStringNotContainsString((string) $sentPin, (string) $organization->fresh()->metadata);
    }

    public function test_reconnect_reuses_the_stored_pin(): void
    {
        [, $organization] = $this->createOwnerContext([
            'whatsapp' => [
                'phone_number_id' => 'phone-pin',
                'two_step_pin_encrypted' => (new WhatsappTokenVault())->encryptTwoStepPin('482913'),
            ],
        ]);
        $sentPin = null;

        $service = $this->serviceExpectingRegistration($organization, true, function (string $pin) use (&$sentPin): void {
            $sentPin = $pin;
        });
        $service->registerPhone();

        $this->assertSame('482913', $sentPin);
        $this->assertSame('482913', $this->storedPin($organization));
    }

    public function test_failed_registration_does_not_store_a_pin(): void
    {
        [, $organization] = $this->createOwnerContext(['whatsapp' => ['phone_number_id' => 'phone-pin']]);

        $service = $this->serviceExpectingRegistration($organization, false);

        $this->assertFalse($service->registerPhone()->success);
        $this->assertNull($this->storedPin($organization));
    }

    private function serviceExpectingRegistration(Organization $organization, bool $succeeds, ?callable $capturePin = null): WhatsappService
    {
        $service = Mockery::mock(WhatsappService::class, ['token', 'v21.0', 'app-id', 'phone-pin', 'waba-pin', $organization->id])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $service->shouldReceive('sendHttpRequest')
            ->once()
            ->withArgs(function (string $method, string $url, array $data) use ($capturePin): bool {
                $capturePin && $capturePin((string) $data['pin']);

                return $method === 'POST' && str_ends_with($url, '/phone-pin/register') && $data['messaging_product'] === 'whatsapp';
            })
            ->andReturn($succeeds
                ? (object) ['success' => true, 'data' => (object) ['success' => true]]
                : (object) ['success' => false, 'data' => (object) ['error' => (object) ['message' => 'PIN mismatch']]]);

        return $service;
    }

    private function storedPin(Organization $organization): ?string
    {
        $metadata = json_decode((string) $organization->fresh()->metadata, true) ?: [];

        return (new WhatsappTokenVault())->resolveTwoStepPinFromMetadata($metadata);
    }
}
