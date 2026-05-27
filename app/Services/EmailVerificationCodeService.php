<?php

namespace App\Services;

use App\Models\EmailVerificationCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EmailVerificationCodeService
{
    public function generate(User $user): string
    {
        $code = (string) random_int(100000, 999999);

        DB::transaction(function () use ($user, $code) {
            EmailVerificationCode::query()
                ->where('user_id', $user->id)
                ->whereNull('used_at')
                ->update(['used_at' => now()]);

            EmailVerificationCode::create([
                'user_id' => $user->id,
                'email' => $user->getEmailForVerification(),
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(15),
            ]);
        });

        return $code;
    }

    public function verify(User $user, string $code): bool
    {
        $code = preg_replace('/\D+/', '', $code) ?? '';

        if ($code === '') {
            return false;
        }

        $record = EmailVerificationCode::query()
            ->where('user_id', $user->id)
            ->where('email', $user->getEmailForVerification())
            ->whereNull('used_at')
            ->latest()
            ->first();

        if (! $record || $record->expires_at->isPast() || ! Hash::check($code, $record->code_hash)) {
            return false;
        }

        DB::transaction(function () use ($user, $record) {
            $record->update(['used_at' => now()]);

            if (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }
        });

        return true;
    }
}
