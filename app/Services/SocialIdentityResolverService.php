<?php

namespace App\Services;

use App\Helpers\Email;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class SocialIdentityResolverService
{
    public function resolveOrCreateUser(string $provider, object $socialUser): User
    {
        $provider = strtolower(trim($provider));
        $providerField = match ($provider) {
            'facebook' => 'facebook_id',
            'google' => 'google_id',
            default => throw ValidationException::withMessages([
                'provider' => __('The selected social login provider is not supported.'),
            ]),
        };

        $providerId = trim((string) ($socialUser->id ?? ''));
        $email = strtolower(trim((string) ($socialUser->email ?? '')));

        if ($providerId === '' || $email === '') {
            throw ValidationException::withMessages([
                'email' => __('We could not read a verified email address from the social login provider.'),
            ]);
        }

        $existingUser = User::withTrashed()
            ->where($providerField, $providerId)
            ->orWhere('email', $email)
            ->first();

        if ($existingUser) {
            if ($existingUser->trashed() || (string) ($existingUser->status ?? '1') !== '1') {
                throw ValidationException::withMessages([
                    'email' => __('This account is not available for social login.'),
                ]);
            }

            if (! $existingUser->{$providerField}) {
                $existingUser->{$providerField} = $providerId;
            }

            if (! $existingUser->email_verified_at) {
                $existingUser->email_verified_at = now();
            }

            $existingUser->save();

            return $existingUser;
        }

        [$firstName, $lastName] = $this->splitDisplayName($socialUser);

        $user = new User();
        $user->first_name = $firstName;
        $user->last_name = $lastName;
        $user->email = $email;
        $user->{$providerField} = $providerId;
        $user->password = null;
        $user->email_verified_at = now();
        $user->role = 'user';
        $user->save();

        Email::send('Registration', $user, [
            'link' => Email::defaultAppLink(),
        ]);

        return $user;
    }

    private function splitDisplayName(object $socialUser): array
    {
        $fullName = trim((string) (
            $socialUser->name
            ?? data_get((array) ($socialUser->user ?? []), 'name')
            ?? ''
        ));

        if ($fullName === '') {
            $firstName = trim((string) (
                $socialUser->first_name
                ?? data_get((array) ($socialUser->user ?? []), 'given_name')
                ?? __('User')
            ));
            $lastName = trim((string) (
                $socialUser->last_name
                ?? data_get((array) ($socialUser->user ?? []), 'family_name')
                ?? ''
            ));

            return [$firstName !== '' ? $firstName : __('User'), $lastName !== '' ? $lastName : null];
        }

        $nameParts = preg_split('/\s+/', $fullName) ?: [];
        $firstName = $nameParts[0] ?? __('User');
        $lastName = count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : null;

        return [$firstName, $lastName ?: null];
    }
}
