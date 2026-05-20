<?php

namespace App\Services\Whatsapp;

class WhatsappTemplateReadinessService
{
    public function buildForMetadata(?array $metadata): array
    {
        $statuses = [
            'account_review_status' => $this->normalizeStatus(data_get($metadata, 'whatsapp.account_review_status')),
            'number_status' => $this->normalizeStatus(data_get($metadata, 'whatsapp.number_status')),
            'verification_status' => $this->normalizeStatus(data_get($metadata, 'whatsapp.code_verification_status')),
            'name_status' => $this->normalizeStatus(data_get($metadata, 'whatsapp.name_status')),
            'quality_rating' => $this->normalizeStatus(data_get($metadata, 'whatsapp.quality_rating')),
        ];

        return [
            'hint' => $this->buildHint($statuses),
            'status_summary' => $this->buildStatusSummary($statuses),
            'statuses' => $statuses,
        ];
    }

    public function appendHintToMessage(string $message, ?array $metadata): string
    {
        $context = $this->buildForMetadata($metadata);
        $parts = [trim($message)];

        if (!empty($context['hint'])) {
            $parts[] = $context['hint'];
        }

        if (!empty($context['status_summary'])) {
            $parts[] = $context['status_summary'];
        }

        return trim(implode("\n", array_filter($parts)));
    }

    private function buildHint(array $statuses): ?string
    {
        $verificationStatus = $statuses['verification_status'];
        $numberStatus = $statuses['number_status'];
        $accountReviewStatus = $statuses['account_review_status'];
        $nameStatus = $statuses['name_status'];
        $qualityRating = $statuses['quality_rating'];

        if ($verificationStatus !== null && $verificationStatus !== 'VERIFIED') {
            return __('Your WhatsApp phone number is not fully verified yet. Complete verification before creating templates.');
        }

        if ($numberStatus === 'PENDING' || $accountReviewStatus === 'PENDING') {
            return __('Your WhatsApp number or account is still pending activation/review in Meta. Template creation may fail until both statuses are completed.');
        }

        if ($nameStatus === 'AVAILABLE_WITHOUT_REVIEW' || $qualityRating === 'UNKNOWN') {
            return __('This WhatsApp number still appears to be in a limited test/review state. If Meta rejects template creation, try again after account review is completed or use a fully activated business number.');
        }

        return null;
    }

    private function buildStatusSummary(array $statuses): ?string
    {
        if (
            $statuses['account_review_status'] === null &&
            $statuses['number_status'] === null &&
            $statuses['verification_status'] === null
        ) {
            return null;
        }

        return __('Current WhatsApp status: account review = :account_review_status, number status = :number_status, phone verification = :verification_status.', [
            'account_review_status' => $statuses['account_review_status'] ?? 'UNKNOWN',
            'number_status' => $statuses['number_status'] ?? 'UNKNOWN',
            'verification_status' => $statuses['verification_status'] ?? 'UNKNOWN',
        ]);
    }

    private function normalizeStatus(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : strtoupper($value);
    }
}
