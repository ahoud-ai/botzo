<?php

namespace App\Services;

use App\Helpers\Email;
use App\Models\MetaVerificationDocumentRequest;
use App\Models\MetaVerificationRequest;
use Illuminate\Validation\ValidationException;

class MetaVerificationRequestService
{
    private const STATUS_EMAIL_TEMPLATE = 'Meta Verification Status Update';
    private const DOCUMENT_REQUEST_EMAIL_TEMPLATE = 'Meta Verification Document Request';

    public function requestAdditionalDocument(MetaVerificationRequest $request, string $label, ?string $note, ?int $requestedByAdminId): MetaVerificationDocumentRequest
    {
        $documentRequest = $request->documentRequests()->create([
            'label' => $label,
            'note' => $note,
            'status' => MetaVerificationDocumentRequest::STATUS_PENDING,
            'requested_by' => $requestedByAdminId,
        ]);

        Email::send(self::DOCUMENT_REQUEST_EMAIL_TEMPLATE, $request, [
            'full_name' => $request->full_name,
            'email' => $request->email,
            'label' => $label,
            'note' => $note ?: '',
            'link' => url('/meta-verification'),
        ]);

        return $documentRequest;
    }

    public function advance(MetaVerificationRequest $request, ?string $adminNote = null): void
    {
        $next = $request->isTerminal() ? null : $request->nextStatus();

        if ($next === null) {
            throw ValidationException::withMessages([
                'status' => __('This request cannot be advanced further.'),
            ]);
        }

        $request->update(['status' => $next, 'admin_note' => $adminNote]);

        $this->notifyStatusChange($request, $next, $adminNote);
        $this->ensureDocumentRequestForStatus($request, $next);
    }

    /**
     * The agreement and payment stages always need a document back from the
     * customer (a signed agreement, then proof of payment) — auto-open the
     * matching upload slot on their dashboard instead of making the admin
     * trigger it manually every time. No separate email: the status-change
     * email already sent by notifyStatusChange() covers this transition.
     */
    private function ensureDocumentRequestForStatus(MetaVerificationRequest $request, string $status): void
    {
        $labels = [
            MetaVerificationRequest::STATUS_AWAITING_AGREEMENT => 'توقيع اتفاقية الخدمة · Signed service agreement',
            MetaVerificationRequest::STATUS_AWAITING_PAYMENT => 'إثبات السداد · Proof of payment',
        ];

        if (! isset($labels[$status])) {
            return;
        }

        $label = $labels[$status];

        $alreadyRequested = $request->documentRequests()->where('label', $label)->exists();

        if ($alreadyRequested) {
            return;
        }

        $request->documentRequests()->create([
            'label' => $label,
            'note' => null,
            'status' => MetaVerificationDocumentRequest::STATUS_PENDING,
            'requested_by' => null,
        ]);
    }

    public function reject(MetaVerificationRequest $request, string $reason): void
    {
        if ($request->isTerminal()) {
            throw ValidationException::withMessages([
                'status' => __('This request cannot be advanced further.'),
            ]);
        }

        $request->update([
            'status' => MetaVerificationRequest::STATUS_REJECTED,
            'rejection_reason' => $reason,
        ]);

        $this->notifyStatusChange($request, MetaVerificationRequest::STATUS_REJECTED, $reason);
    }

    /**
     * Emails the requester (guest or logged-in — the form always collects a real
     * email address) using the same admin-editable EmailTemplate/CustomEmail
     * pipeline the rest of the app already uses for transactional email.
     */
    private function notifyStatusChange(MetaVerificationRequest $request, string $status, ?string $customNote = null): void
    {
        $note = $customNote !== null && $customNote !== ''
            ? $customNote
            : MetaVerificationRequest::nextStepNote($status);

        Email::send(self::STATUS_EMAIL_TEMPLATE, $request, [
            'full_name' => $request->full_name,
            'email' => $request->email,
            'status' => MetaVerificationRequest::statusLabel($status),
            'note' => $note,
        ]);
    }
}
