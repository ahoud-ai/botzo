<?php

namespace App\Modules\WhatsApp\Infrastructure\CloudApi;

use Illuminate\Support\Facades\Http;
use stdClass;

class WhatsappAccountInspectionService
{
    public function __construct(
        private readonly ?string $accessToken,
        private readonly ?string $apiVersion,
        private readonly ?string $phoneNumberId,
        private readonly ?string $wabaId,
    ) {}

    public function getBusinessProfile(): stdClass
    {
        return $this->request(
            "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/whatsapp_business_profile",
            ['fields' => 'about,address,description,email,profile_picture_url,websites,vertical'],
            static fn (array $response): stdClass => (object) ($response['data'][0] ?? [])
        );
    }

    public function getPhoneNumberId(): stdClass
    {
        return $this->request(
            "https://graph.facebook.com/{$this->apiVersion}/{$this->wabaId}/phone_numbers",
            ['fields' => 'display_phone_number,certificate,name_status,new_certificate,new_name_status,verified_name,quality_rating,messaging_limit_tier'],
            static fn (array $response): stdClass => (object) ($response['data'][0] ?? [])
        );
    }

    public function getPhoneNumberStatus(): stdClass
    {
        return $this->request(
            "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}",
            ['fields' => 'status, code_verification_status , quality_score, health_status'],
            static fn (array $response): stdClass => (object) $response
        );
    }

    public function getAccountReviewStatus(): stdClass
    {
        return $this->request(
            "https://graph.facebook.com/{$this->apiVersion}/{$this->wabaId}",
            ['fields' => 'account_review_status'],
            static fn (array $response): stdClass => (object) $response
        );
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  callable(array<string, mixed>): stdClass  $mapper
     */
    private function request(string $url, array $query, callable $mapper): stdClass
    {
        $responseObject = new stdClass;

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->accessToken,
            ])->get($url, $query)->throw()->json();

            if (isset($response['data']['error'])) {
                $responseObject->success = false;
                $responseObject->data = (object) [
                    'error' => (object) [
                        'code' => $response['data']['error']['code'] ?? null,
                        'message' => $response['data']['error']['message'] ?? 'Unknown error',
                    ],
                ];

                return $responseObject;
            }

            $responseObject->success = true;
            $responseObject->data = $mapper($response);
        } catch (\Throwable $exception) {
            $responseObject->success = false;
            $responseObject->data = (object) [
                'error' => (object) [
                    'message' => $exception->getMessage(),
                ],
            ];
        }

        return $responseObject;
    }
}
