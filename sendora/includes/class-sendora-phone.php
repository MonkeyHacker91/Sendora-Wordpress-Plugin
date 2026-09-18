<?php
/**
 * Phone number normalization helpers.
 *
 * @package Sendora
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Sendora_Phone
{
    public static function normalize(string $raw, string $default_cc = '55'): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        $country_code = preg_replace('/\D+/', '', $default_cc) ?? '';

        if ($digits === '' || $country_code === '') {
            return $digits;
        }

        $explicit_international = str_starts_with(trim($raw), '+')
            || str_starts_with($digits, '00');

        if (str_starts_with($digits, '00')) {
            return substr($digits, 2);
        }

        if ($explicit_international || str_starts_with($digits, $country_code)) {
            return $digits;
        }

        return $country_code . $digits;
    }
}
