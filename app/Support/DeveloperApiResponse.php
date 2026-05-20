<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class DeveloperApiResponse
{
    public static function validation($errors, ?string $message = null): JsonResponse
    {
        return self::error(
            400,
            $message ?? __('The given data was invalid.'),
            'validation_error',
            $errors
        );
    }

    public static function unauthorized(string $message, string $code = 'unauthorized'): JsonResponse
    {
        return self::error(401, $message, $code);
    }

    public static function forbidden(string $message, string $code = 'forbidden'): JsonResponse
    {
        return self::error(403, $message, $code);
    }

    public static function notFound(string $message, string $code = 'not_found'): JsonResponse
    {
        return self::error(404, $message, $code);
    }

    public static function unprocessable(string $message, string $code = 'unprocessable'): JsonResponse
    {
        return self::error(422, $message, $code);
    }

    public static function serverError(?string $message = null): JsonResponse
    {
        return self::error(500, $message ?? __('Request unable to be processed'), 'server_error');
    }

    public static function error(int $statusCode, string $message, string $code, $errors = null): JsonResponse
    {
        $requestId = self::requestId();
        $payload = [
            'statusCode' => $statusCode,
            'code' => $code,
            'message' => $message,
            'request_id' => $requestId,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()
            ->json($payload, $statusCode)
            ->header('X-Request-Id', $requestId);
    }

    private static function requestId(): string
    {
        $request = request();
        $existing = $request->attributes->get('developer_api_request_id');

        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $requestId = $request->headers->get('X-Request-Id') ?: (string) Str::uuid();
        $request->attributes->set('developer_api_request_id', $requestId);

        return $requestId;
    }
}
