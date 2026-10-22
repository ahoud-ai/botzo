<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * Turns a WhatsApp send result into the developer API answer: 200 with the message id when Meta
 * accepted it, otherwise an error with a stable code the app can act on and Meta's own details.
 * "data" keeps the raw result so integrations that read it keep working.
 */
class DeveloperApiSendResult
{
    // Meta error codes → stable codes for developers. https://developers.facebook.com/docs/whatsapp/cloud-api/support/error-codes
    private const META_CODES = [
        131047 => ['outside_24h_window', 422],
        131042 => ['meta_payment_issue', 422],
        131026 => ['recipient_unavailable', 422],
        131021 => ['recipient_is_sender', 422],
        131031 => ['whatsapp_account_locked', 403],
        131037 => ['display_name_not_approved', 422],
        131056 => ['recipient_rate_limited', 429],
        130429 => ['meta_rate_limited', 429],
        131048 => ['meta_spam_rate_limited', 429],
        131049 => ['meta_ecosystem_limited', 422],
        131051 => ['unsupported_message_type', 422],
        131052 => ['media_download_failed', 422],
        131053 => ['media_upload_failed', 422],
        132000 => ['template_parameters_mismatch', 422],
        132001 => ['template_not_found', 422],
        132005 => ['template_text_too_long', 422],
        132007 => ['template_policy_violation', 422],
        132012 => ['template_parameters_invalid', 422],
        132015 => ['template_paused', 422],
        132016 => ['template_disabled', 422],
        100 => ['invalid_request', 422],
        190 => ['whatsapp_token_expired', 401],
        368 => ['whatsapp_account_restricted', 403],
    ];

    public static function response(object $result): JsonResponse
    {
        if (($result->success ?? false) === true) {
            return response()->json([
                'statusCode' => 200,
                'message_id' => data_get($result, 'data.messages.0.id'),
                'status' => 'sent',
                'data' => $result,
            ], 200);
        }

        [$code, $status] = self::failureCode($result);
        $metaError = data_get($result, 'data.error');
        $message = (string) ($result->message
            ?? data_get($metaError, 'error_user_msg')
            ?? data_get($metaError, 'message')
            ?? __('The message could not be sent.'));

        $response = DeveloperApiResponse::error($status, $message, $code);
        $payload = $response->getData(true);
        $payload['meta_error'] = $metaError && data_get($metaError, 'code') !== null ? [
            'code' => data_get($metaError, 'code'),
            'subcode' => data_get($metaError, 'error_subcode'),
            'message' => data_get($metaError, 'message'),
            'details' => data_get($metaError, 'error_data.details'),
        ] : null;
        $payload['data'] = $result;

        return $response->setData($payload);
    }

    private static function failureCode(object $result): array
    {
        $errorType = data_get($result, 'data.error.type');
        if ($errorType === 'subscription_limit') {
            return ['message_limit_reached', 403];
        }
        if ($errorType === 'organization_scope') {
            return ['contact_not_found', 404];
        }

        $metaCode = data_get($result, 'data.error.code');
        if ($metaCode === null) {
            // No answer from Meta at all (timeout or network).
            return ['meta_unreachable', 502];
        }

        return self::META_CODES[(int) $metaCode] ?? ['meta_rejected', 422];
    }
}
