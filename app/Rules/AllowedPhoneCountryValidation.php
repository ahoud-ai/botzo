<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Propaganistas\LaravelPhone\PhoneNumber;
use Throwable;

class AllowedPhoneCountryValidation implements Rule
{
    /**
     * @var array<int, string>
     */
    private array $allowedCountries;

    /**
     * @param array<int, string>|null $allowedCountries
     */
    public function __construct(?array $allowedCountries = null)
    {
        $this->allowedCountries = collect($allowedCountries ?? config('formats.allowed_phone_country_codes', []))
            ->filter(fn ($country) => is_string($country) && $country !== '')
            ->map(fn ($country) => strtoupper($country))
            ->values()
            ->all();
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     */
    public function passes($attribute, $value): bool
    {
        if (!is_string($value) || trim($value) === '') {
            return false;
        }

        try {
            $phoneNumber = new PhoneNumber($value);

            if (!$phoneNumber->isValid()) {
                return false;
            }

            $country = $phoneNumber->getCountry();
            if (!$country) {
                return false;
            }

            return in_array(strtoupper($country), $this->allowedCountries, true);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return __('The :attribute field must be a valid phone number from the allowed countries.');
    }
}
