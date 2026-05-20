<?php

namespace App\Services\Concerns;

use App\Models\Contact;
use App\Models\Organization;
use App\Modules\WhatsApp\Infrastructure\CloudApi\WhatsappAccountInspectionService;
use App\Services\ChatTicketProvisioningService;
use App\Services\Whatsapp\WhatsappTemplateReadinessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

trait InteractsWithWhatsappServiceSupport
{
    private function ensureTicketProvisioned(?Contact $contact): void
    {
        if (! $contact) {
            return;
        }

        (new ChatTicketProvisioningService((int) $contact->organization_id))->ensureForContact((int) $contact->id);
    }

    public function setHeaders()
    {
        return [
            'Authorization' => 'Bearer '.$this->accessToken,
            'Content-Type' => 'application/json',
        ];
    }

    private function accountInspectionService(): WhatsappAccountInspectionService
    {
        return new WhatsappAccountInspectionService(
            $this->accessToken,
            $this->apiVersion,
            $this->phoneNumberId,
            $this->wabaId,
        );
    }

    private function appendTemplateReadinessHint(string $message): string
    {
        return app(WhatsappTemplateReadinessService::class)
            ->appendHintToMessage($message, $this->organizationWhatsappMetadata());
    }

    private function buildTemplateReadinessContext(): array
    {
        return app(WhatsappTemplateReadinessService::class)
            ->buildForMetadata($this->organizationWhatsappMetadata());
    }

    private function organizationWhatsappMetadata(): array
    {
        $organization = Organization::find($this->organizationId);

        return $organization && ! empty($organization->metadata)
            ? (json_decode($organization->metadata, true) ?: [])
            : [];
    }

    private function logTemplateRequestFailure(string $operation, ?Request $request, object $apiData, ?string $templateUuid = null): void
    {
        $metadata = $this->organizationWhatsappMetadata();

        Log::warning('WhatsApp template request failed.', [
            'operation' => $operation,
            'organization_id' => $this->organizationId,
            'user_id' => auth()->id(),
            'template_uuid' => $templateUuid,
            'template_name' => $request?->input('name'),
            'template_category' => $request?->input('category'),
            'template_language' => $request?->input('language'),
            'waba_id' => $this->wabaId,
            'phone_number_id' => $this->phoneNumberId,
            'account_review_status' => data_get($metadata, 'whatsapp.account_review_status'),
            'number_status' => data_get($metadata, 'whatsapp.number_status'),
            'code_verification_status' => data_get($metadata, 'whatsapp.code_verification_status'),
            'name_status' => data_get($metadata, 'whatsapp.name_status'),
            'quality_rating' => data_get($metadata, 'whatsapp.quality_rating'),
            'api_error_code' => data_get($apiData, 'error.code'),
            'api_error_subcode' => data_get($apiData, 'error.error_subcode'),
            'api_error_message' => data_get($apiData, 'error.message'),
            'api_error_user_msg' => data_get($apiData, 'error.error_user_msg'),
            'api_error_details' => data_get($apiData, 'error.error_data.details'),
            'api_fbtrace_id' => data_get($apiData, 'error.fbtrace_id'),
        ]);
    }

    private function inspectRemoteMediaUrl($url): array
    {
        try {
            $response = Http::timeout(10)->connectTimeout(5)->head($url);

            return [
                'content_type' => $response->hasHeader('Content-Type')
                    ? $response->header('Content-Type')
                    : null,
                'size' => $this->parseContentLengthHeader($response->header('Content-Length')),
            ];
        } catch (\Throwable $e) {
            Log::warning('Unable to inspect remote media URL.', [
                'organization_id' => $this->organizationId,
                'url' => $url,
                'message' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            return [
                'content_type' => null,
                'size' => null,
            ];
        }
    }

    private function parseContentLengthHeader($contentLength): ?int
    {
        if (is_array($contentLength)) {
            $contentLength = reset($contentLength);
        }

        $normalized = trim((string) $contentLength);
        if ($normalized === '' || ! ctype_digit($normalized)) {
            return null;
        }

        return (int) $normalized;
    }

    public function getContentTypeFromUrl($url)
    {
        return $this->inspectRemoteMediaUrl($url)['content_type'] ?? 'application/octet-stream';
    }

    public function formatMediaResponse($wamId, $mediaUrl, $mediaType, $contentType, $transcription = null)
    {
        $response = [
            'channel' => 'whatsapp',
            'provider' => 'whatsapp_cloud',
            'id' => $wamId,
            'type' => $mediaType,
            $mediaType => [
                'mime_type' => $contentType,
            ],
        ];

        if ($mediaType === 'audio' && $transcription) {
            $response['transcript'] = $transcription;
        }

        return $response;
    }

    private function enrichMediaResponseMetadata(array $response, string $mediaType, ?string $caption, array $mediaMetadata): array
    {
        if ($caption !== null && $caption !== '' && $mediaType !== 'audio') {
            $response[$mediaType]['caption'] = $caption;
            $response['caption'] = $caption;
        }

        foreach (['duration_ms', 'local_recording_id', 'waveform', 'client_message_id_hash', 'source'] as $key) {
            if (array_key_exists($key, $mediaMetadata) && $mediaMetadata[$key] !== null && $mediaMetadata[$key] !== '') {
                $response[$key] = $mediaMetadata[$key];
                $response[$mediaType][$key] = $mediaMetadata[$key];
            }
        }

        return $response;
    }

    public function getMediaSizeInBytesFromUrl($url)
    {
        return $this->normalizeMediaSize($this->inspectRemoteMediaUrl($url)['size'] ?? null);
    }

    private function resolveOutboundMediaMetadata($mediaUrl, $mediaFilePath, string $mediaType, array $metadata): array
    {
        $contentType = $metadata['content_type'] ?? $metadata['mime_type'] ?? null;
        $size = $metadata['size'] ?? null;

        if ($contentType === null || $size === null) {
            $localMetadata = $this->inspectLocalMediaFile($mediaFilePath);
            $contentType ??= $localMetadata['content_type'];
            $size ??= $localMetadata['size'];
        }

        if ($contentType === null || $size === null) {
            $remoteMetadata = $this->inspectRemoteMediaUrl($mediaUrl);
            $contentType ??= $remoteMetadata['content_type'];
            $size ??= $remoteMetadata['size'];
        }

        return [
            'content_type' => $contentType ?: $this->defaultContentTypeForMediaType($mediaType),
            'size' => $this->normalizeMediaSize($size),
        ];
    }

    private function inspectLocalMediaFile($mediaFilePath): array
    {
        $metadata = [
            'content_type' => null,
            'size' => null,
        ];

        if (! is_string($mediaFilePath) || trim($mediaFilePath) === '') {
            return $metadata;
        }

        $normalizedPath = str_replace('\\', '/', $mediaFilePath);

        try {
            if (Storage::disk('local')->exists($normalizedPath)) {
                $metadata['content_type'] = Storage::disk('local')->mimeType($normalizedPath) ?: null;
                $metadata['size'] = Storage::disk('local')->size($normalizedPath);

                return $metadata;
            }
        } catch (\Throwable $e) {
            Log::warning('Unable to inspect stored local media file.', [
                'organization_id' => $this->organizationId,
                'path' => $normalizedPath,
                'message' => $e->getMessage(),
            ]);
        }

        if (is_file($normalizedPath)) {
            $metadata['content_type'] = mime_content_type($normalizedPath) ?: null;
            $metadata['size'] = filesize($normalizedPath);
        }

        return $metadata;
    }

    private function defaultContentTypeForMediaType(string $mediaType): string
    {
        return match ($mediaType) {
            'audio' => 'audio/mpeg',
            'image' => 'image/jpeg',
            'video' => 'video/mp4',
            'sticker' => 'image/webp',
            default => 'application/octet-stream',
        };
    }

    private function normalizeMediaSize($size): int
    {
        if (is_numeric($size) && (int) $size >= 0) {
            return (int) $size;
        }

        return 0;
    }

    private function buildFailureResponseObjectFromThrowable(\Throwable $exception): \stdClass
    {
        $responseObject = new \stdClass;
        $responseObject->success = false;
        $responseObject->data = new \stdClass;
        $responseObject->data->error = new \stdClass;
        $responseObject->data->error->message = $exception->getMessage();

        $response = method_exists($exception, 'getResponse') ? $exception->getResponse() : null;
        if (! $response) {
            return $responseObject;
        }

        $decoded = json_decode($response->getBody()->getContents());
        if ($decoded instanceof \stdClass) {
            $responseObject->data = $decoded;
        } elseif (is_array($decoded)) {
            $responseObject->data = json_decode(json_encode($decoded));
        }

        if (isset($responseObject->data->error->error_user_msg)) {
            $responseObject->message = $responseObject->data->error->error_user_msg;
        } elseif (isset($responseObject->data->error->message)) {
            $responseObject->message = $responseObject->data->error->message;
        }

        return $responseObject;
    }
}
