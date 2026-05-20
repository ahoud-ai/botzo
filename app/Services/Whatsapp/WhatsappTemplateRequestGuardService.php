<?php

namespace App\Services\Whatsapp;

use Illuminate\Http\Request;

class WhatsappTemplateRequestGuardService
{
    public function normalizeTemplateRequestPayload(Request $request): Request
    {
        $buttons = $request->input('buttons', []);
        if (! is_array($buttons)) {
            return $request;
        }

        foreach ($buttons as $index => $button) {
            if (! is_array($button)) {
                continue;
            }

            $buttonType = strtoupper(trim((string) ($button['type'] ?? '')));
            if ($buttonType !== '') {
                $buttons[$index]['type'] = $buttonType;
            }
        }

        $request->merge(['buttons' => $buttons]);

        return $request;
    }

    public function validateTemplateRequestPayload(Request $request): ?\stdClass
    {
        $category = strtoupper((string) $request->input('category', ''));

        if ($category === 'AUTHENTICATION') {
            return null;
        }

        $header = $request->input('header', []);
        if (is_array($header)) {
            $headerFormat = strtoupper((string) ($header['format'] ?? 'TEXT'));
            $headerText = trim((string) ($header['text'] ?? ''));
            if ($headerFormat === 'TEXT' && $headerText !== '' && mb_strlen($headerText) > 60) {
                return $this->templateValidationResponse(
                    __('Template header text cannot exceed :max characters.', ['max' => 60]),
                    'header.text'
                );
            }
        }

        $body = $request->input('body', []);
        $bodyText = is_array($body) ? trim((string) ($body['text'] ?? '')) : '';
        if ($bodyText === '') {
            return $this->templateValidationResponse(
                __('Template body text is required.'),
                'body.text'
            );
        }

        if (mb_strlen($bodyText) > 1024) {
            return $this->templateValidationResponse(
                __('Template body cannot exceed :max characters.', ['max' => 1024]),
                'body.text'
            );
        }

        $footer = $request->input('footer', []);
        if (is_array($footer)) {
            $footerText = trim((string) ($footer['text'] ?? ''));
            if ($footerText !== '' && mb_strlen($footerText) > 60) {
                return $this->templateValidationResponse(
                    __('Template footer text cannot exceed :max characters.', ['max' => 60]),
                    'footer.text'
                );
            }
        }

        $buttons = $request->input('buttons', []);
        if (! is_array($buttons)) {
            return $this->templateValidationResponse(
                __('Template buttons payload is invalid.'),
                'buttons'
            );
        }

        $normalizedButtons = [];
        foreach ($buttons as $button) {
            if (! is_array($button)) {
                continue;
            }

            $type = strtoupper(trim((string) ($button['type'] ?? '')));
            if ($type === '') {
                continue;
            }

            if (! in_array($type, ['QUICK_REPLY', 'URL', 'PHONE_NUMBER', 'COPY_CODE'], true)) {
                return $this->templateValidationResponse(
                    __('Template contains invalid button configuration.'),
                    'buttons'
                );
            }

            if ($type === 'COPY_CODE') {
                $example = trim((string) ($button['example'] ?? ''));
                if ($example === '') {
                    return $this->templateValidationResponse(
                        __('Copy code button requires a sample code.'),
                        'buttons'
                    );
                }

                $normalizedButtons[] = [
                    'type' => 'COPY_CODE',
                    'example' => $example,
                ];
                continue;
            }

            $text = trim((string) ($button['text'] ?? ''));
            if ($text === '') {
                return $this->templateValidationResponse(
                    __('Button text is required.'),
                    'buttons'
                );
            }

            if (mb_strlen($text) > 25) {
                return $this->templateValidationResponse(
                    __('Button text cannot exceed :max characters.', ['max' => 25]),
                    'buttons'
                );
            }

            if ($type === 'QUICK_REPLY') {
                $normalizedButtons[] = [
                    'type' => 'QUICK_REPLY',
                    'text' => $text,
                ];
                continue;
            }

            if ($type === 'URL') {
                $url = trim((string) ($button['url'] ?? ''));
                if (! $this->isValidTemplateUrl($url)) {
                    return $this->templateValidationResponse(
                        __('Website URL must start with http:// or https:// and be valid.'),
                        'buttons'
                    );
                }

                $normalizedButtons[] = [
                    'type' => 'URL',
                    'text' => $text,
                    'url' => $url,
                ];
                continue;
            }

            $country = trim((string) ($button['country'] ?? ''));
            $phoneNumber = trim((string) ($button['phone_number'] ?? ''));
            $fullPhone = preg_replace('/\D+/', '', $country.$phoneNumber);
            if ($country === '' || $phoneNumber === '' || $fullPhone === '' || strlen($fullPhone) < 7 || strlen($fullPhone) > 15) {
                return $this->templateValidationResponse(
                    __('Phone button requires a valid country code and phone number.'),
                    'buttons'
                );
            }

            $normalizedButtons[] = [
                'type' => 'PHONE_NUMBER',
                'text' => $text,
                'country' => $country,
                'phone_number' => $phoneNumber,
            ];
        }

        $request->merge(['buttons' => $normalizedButtons]);

        return null;
    }

    public function buildTemplateApiErrorMessage(?object $apiData): string
    {
        $error = (is_object($apiData) && isset($apiData->error) && is_object($apiData->error))
            ? $apiData->error
            : null;

        $details = '';
        if ($error && isset($error->error_data) && is_object($error->error_data) && isset($error->error_data->details)) {
            $details = trim((string) $error->error_data->details);
        }

        if ($details !== '') {
            return __('Template validation failed: :details', ['details' => $details]);
        }

        if ($error && isset($error->error_user_msg) && trim((string) $error->error_user_msg) !== '') {
            return trim((string) $error->error_user_msg);
        }

        if ($error && isset($error->message) && trim((string) $error->message) !== '') {
            return __('Template request was rejected by WhatsApp. Please review header, body, footer, and buttons.').' '.trim((string) $error->message);
        }

        return __('Template request was rejected by WhatsApp. Please review header, body, footer, and buttons.');
    }

    private function isValidTemplateUrl(string $url): bool
    {
        $sanitizedUrl = trim($url);
        if ($sanitizedUrl === '' || ! preg_match('/^https?:\/\//i', $sanitizedUrl)) {
            return false;
        }

        $normalizedUrl = preg_replace('/\{\{\d+\}\}/', 'sample', $sanitizedUrl);

        return filter_var($normalizedUrl, FILTER_VALIDATE_URL) !== false;
    }

    private function templateValidationResponse(string $message, string $field): \stdClass
    {
        $responseObject = new \stdClass;
        $responseObject->success = false;
        $responseObject->message = $message;
        $responseObject->data = new \stdClass;
        $responseObject->data->error = new \stdClass;
        $responseObject->data->error->message = $message;
        $responseObject->data->error->field = $field;

        return $responseObject;
    }
}
