<?php

namespace App\Services;

use App\Helpers\Email;
use App\Mail\PasswordResetCodeMail;
use App\Models\PasswordResetToken;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PasswordResetService
{
    const CODE_EXPIRY_MINUTES = 60;

    public function generateResetCode(string $email): void
    {
        $code = (string) random_int(100000, 999999);

        $this->storeCodeInDatabase($email, $code);
        $this->sendCodeEmail($email, $code);
    }

    public function sendCodeEmail(string $email, string $code): void
    {
        if ((string) Setting::where('key', 'smtp_email_active')->value('value') !== '1') {
            return;
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            return;
        }

        try {
            Mail::to($email)->queue(new PasswordResetCodeMail($user, $code));
        } catch (\Exception $e) {
            Log::error('Password reset code mail failed: ' . $e->getMessage());
        }
    }

    public function verifyCode(string $email, string $code): bool
    {
        $record = PasswordResetToken::where('email', $email)->first();

        if (! $record) {
            return false;
        }

        if ($record->created_at->addMinutes(self::CODE_EXPIRY_MINUTES)->isPast()) {
            return false;
        }

        return Hash::check($code, $record->token);
    }

    public function resetPassword(string $email, string $password): bool
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            return false;
        }

        $user->forceFill([
            'password' => Hash::make($password),
        ])->save();

        $this->clearCode($email);

        Email::send('Password Reset Notification', $user->refresh());

        return true;
    }

    private function storeCodeInDatabase(string $email, string $code): void
    {
        PasswordResetToken::updateOrInsert(
            ['email' => $email],
            ['token' => Hash::make($code), 'created_at' => now()]
        );
    }

    private function clearCode(string $email): void
    {
        PasswordResetToken::where('email', $email)->delete();
    }
}
