<?php
/**
 * Phone number normalization and country masks.
 *
 * @package Sendora
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Sendora_Phone
{
    /**
     * @return array<string, array{cc: string, label: string, mask: string, placeholder: string}>
     */
    public static function countries(): array
    {
        return [
            'BR' => [
                'cc' => '55',
                'label' => 'Brasil (+55)',
                'mask' => '(##) #####-####',
                'placeholder' => '(11) 99999-9999',
            ],
            'PT' => [
                'cc' => '351',
                'label' => 'Portugal (+351)',
                'mask' => '### ### ###',
                'placeholder' => '912 345 678',
            ],
            'US' => [
                'cc' => '1',
                'label' => 'Estados Unidos (+1)',
                'mask' => '(###) ###-####',
                'placeholder' => '(415) 555-2671',
            ],
            'CA' => [
                'cc' => '1',
                'label' => 'Canadá (+1)',
                'mask' => '(###) ###-####',
                'placeholder' => '(416) 555-0123',
            ],
            'MX' => [
                'cc' => '52',
                'label' => 'México (+52)',
                'mask' => '## #### ####',
                'placeholder' => '55 1234 5678',
            ],
            'AR' => [
                'cc' => '54',
                'label' => 'Argentina (+54)',
                'mask' => '## ####-####',
                'placeholder' => '11 2345-6789',
            ],
            'CL' => [
                'cc' => '56',
                'label' => 'Chile (+56)',
                'mask' => '# #### ####',
                'placeholder' => '9 1234 5678',
            ],
            'CO' => [
                'cc' => '57',
                'label' => 'Colômbia (+57)',
                'mask' => '### ### ####',
                'placeholder' => '300 123 4567',
            ],
            'PE' => [
                'cc' => '51',
                'label' => 'Peru (+51)',
                'mask' => '### ### ###',
                'placeholder' => '912 345 678',
            ],
            'UY' => [
                'cc' => '598',
                'label' => 'Uruguai (+598)',
                'mask' => '#### ####',
                'placeholder' => '9123 4567',
            ],
            'PY' => [
                'cc' => '595',
                'label' => 'Paraguai (+595)',
                'mask' => '### ######',
                'placeholder' => '981 123456',
            ],
            'BO' => [
                'cc' => '591',
                'label' => 'Bolívia (+591)',
                'mask' => '# ### ####',
                'placeholder' => '7 123 4567',
            ],
            'EC' => [
                'cc' => '593',
                'label' => 'Equador (+593)',
                'mask' => '## ### ####',
                'placeholder' => '99 123 4567',
            ],
            'ES' => [
                'cc' => '34',
                'label' => 'Espanha (+34)',
                'mask' => '### ## ## ##',
                'placeholder' => '612 34 56 78',
            ],
            'IT' => [
                'cc' => '39',
                'label' => 'Itália (+39)',
                'mask' => '### ### ####',
                'placeholder' => '312 345 6789',
            ],
            'FR' => [
                'cc' => '33',
                'label' => 'França (+33)',
                'mask' => '# ## ## ## ##',
                'placeholder' => '6 12 34 56 78',
            ],
            'DE' => [
                'cc' => '49',
                'label' => 'Alemanha (+49)',
                'mask' => '### #######',
                'placeholder' => '151 1234567',
            ],
            'GB' => [
                'cc' => '44',
                'label' => 'Reino Unido (+44)',
                'mask' => '#### ######',
                'placeholder' => '7911 123456',
            ],
            'AO' => [
                'cc' => '244',
                'label' => 'Angola (+244)',
                'mask' => '### ### ###',
                'placeholder' => '923 456 789',
            ],
            'MZ' => [
                'cc' => '258',
                'label' => 'Moçambique (+258)',
                'mask' => '## ### ####',
                'placeholder' => '82 123 4567',
            ],
        ];
    }

    /**
     * @return array{cc: string, label: string, mask: string, placeholder: string}
     */
    public static function country(string $code): array
    {
        $code = strtoupper(trim($code));
        $countries = self::countries();

        return $countries[$code] ?? $countries['BR'];
    }

    public static function country_from_cc(string $cc): string
    {
        $cc = preg_replace('/\D+/', '', $cc) ?? '';
        foreach (self::countries() as $iso => $meta) {
            if ($meta['cc'] === $cc) {
                return $iso;
            }
        }

        return 'BR';
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $saved
     * @return array{phone_country: string, default_cc: string}
     */
    public static function sanitize_country_settings(array $input, array $saved = []): array
    {
        $countries = self::countries();
        $iso = strtoupper(sanitize_text_field((string) ($input['phone_country'] ?? '')));

        if ($iso !== '' && isset($countries[$iso])) {
            return [
                'phone_country' => $iso,
                'default_cc' => $countries[$iso]['cc'],
            ];
        }

        $cc = preg_replace(
            '/\D+/',
            '',
            sanitize_text_field((string) ($input['default_cc'] ?? ($saved['default_cc'] ?? '55')))
        ) ?: '55';

        $resolved = self::country_from_cc($cc);
        // Prefer saved country when CC is ambiguous (US/CA both use 1).
        $saved_iso = strtoupper((string) ($saved['phone_country'] ?? ''));
        if ($cc === '1' && isset($countries[$saved_iso]) && $countries[$saved_iso]['cc'] === '1') {
            $resolved = $saved_iso;
        }

        return [
            'phone_country' => $resolved,
            'default_cc' => $countries[$resolved]['cc'],
        ];
    }

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

    /**
     * HTML <option> list for admin country select.
     */
    public static function country_options_html(string $selected): string
    {
        $selected = strtoupper($selected);
        if ($selected === '' || !isset(self::countries()[$selected])) {
            $selected = 'BR';
        }

        $html = '';
        foreach (self::countries() as $iso => $meta) {
            $html .= sprintf(
                '<option value="%s"%s>%s</option>',
                esc_attr($iso),
                selected($selected, $iso, false),
                esc_html($meta['label'])
            );
        }

        return $html;
    }
}
