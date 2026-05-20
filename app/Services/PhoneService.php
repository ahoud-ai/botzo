<?php

namespace App\Services;

use Propaganistas\LaravelPhone\PhoneNumber;
use Throwable;

class PhoneService
{
    private const COUNTRY = 'SA';

    /**
     * @return array{is_valid: bool, formatted: string|null, error: string|null, type: string}
     */
    public static function validateAndFormat($phoneNumber): array
    {
        foreach ([[$phoneNumber], [$phoneNumber, self::COUNTRY]] as $arguments) {
            try {
                $phone = new PhoneNumber(...$arguments);

                if ($phone->isValid() && strtoupper((string) $phone->getCountry()) === self::COUNTRY) {
                    return [
                        'is_valid' => true,
                        'formatted' => $phone->formatE164(),
                        'error' => null,
                        'type' => 'saudi',
                    ];
                }
            } catch (Throwable) {
                //
            }
        }

        return [
            'is_valid' => false,
            'formatted' => null,
            'error' => __('Enter a valid Saudi phone number.'),
            'type' => 'invalid',
        ];
    }

    public static function formatForDisplay($phoneNumber): string
    {
        try {
            $phone = new PhoneNumber($phoneNumber, self::COUNTRY);

            if (strtoupper((string) $phone->getCountry()) === self::COUNTRY) {
                return $phone->formatInternational();
            }
        } catch (Throwable) {
            //
        }

        return (string) $phoneNumber;
    }

    public static function getE164Format($phoneNumber): ?string
    {
        $validation = self::validateAndFormat($phoneNumber);

        return $validation['is_valid'] ? $validation['formatted'] : null;
    }

    public static function isValid($phoneNumber): bool
    {
        return self::validateAndFormat($phoneNumber)['is_valid'];
    }

    public static function getType($phoneNumber): string
    {
        return self::validateAndFormat($phoneNumber)['type'];
    }

    public static function normalize($phoneNumber): string
    {
        $phoneNumber = trim((string) $phoneNumber);

        if ($phoneNumber !== '' && !str_starts_with($phoneNumber, '+')) {
            $phoneNumber = '+'.ltrim($phoneNumber, '0+');
        }

        return $phoneNumber;
    }
}
