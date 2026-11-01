<?php

namespace App\Services\DemoRequests;

use App\Mail\DemoRequestAlertMail;
use App\Models\DemoRequest;
use App\Models\Setting;
use Illuminate\Support\Facades\Mail;

/**
 * Saves a demo request (from the website form or from Mochi) and emails the team, after the
 * response is sent so the visitor never waits for the mail server.
 */
class DemoRequestService
{
    public const FALLBACK_ALERT_EMAIL = 'support@botzo.net';

    /**
     * @param  array{full_name: string, phone: string, email?: ?string, business_activity?: ?string, notes?: ?string, preferred_time?: ?string}  $data
     */
    public function create(array $data, string $source = DemoRequest::SOURCE_WEBSITE, ?int $contactId = null): DemoRequest
    {
        $request = DemoRequest::create([
            'full_name' => trim((string) $data['full_name']),
            'phone' => self::normalizePhone((string) $data['phone']),
            'email' => filled($data['email'] ?? null) ? trim((string) $data['email']) : null,
            'business_activity' => filled($data['business_activity'] ?? null) ? trim((string) $data['business_activity']) : null,
            'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
            'preferred_time' => in_array($data['preferred_time'] ?? null, DemoRequest::PREFERRED_TIMES, true) ? $data['preferred_time'] : null,
            'source' => $source,
            'contact_id' => $contactId,
            'status' => 'new',
        ]);

        app()->terminating(fn () => $this->alertTeam($request));

        return $request;
    }

    public function alertTeam(DemoRequest $request): void
    {
        try {
            Mail::to($this->alertEmail())->send(new DemoRequestAlertMail(
                $request,
                self::whatsappUrl($request->phone),
                url('/admin/demo-requests')
            ));
        } catch (\Throwable $exception) {
            // The request is saved and listed in the admin; a mail problem must not lose it.
            report($exception);
        }
    }

    /**
     * The team address from Settings > General (the site's contact email).
     */
    public function alertEmail(): string
    {
        $email = trim((string) Setting::where('key', 'email')->value('value'));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : self::FALLBACK_ALERT_EMAIL;
    }

    /**
     * "+966 55 123 4567" -> "+966551234567"; a local Saudi "05..." number gets +966.
     */
    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '05') && strlen($digits) === 10) {
            $digits = '966'.substr($digits, 1);
        }

        return $digits === '' ? trim($phone) : '+'.$digits;
    }

    /**
     * A wa.me link that opens a chat with the number, or null when it is not a usable number.
     */
    public static function whatsappUrl(?string $phone, ?string $text = null): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (strlen($digits) < 8 || strlen($digits) > 15) {
            return null;
        }

        return 'https://wa.me/'.$digits.($text !== null && $text !== '' ? '?text='.rawurlencode($text) : '');
    }
}
