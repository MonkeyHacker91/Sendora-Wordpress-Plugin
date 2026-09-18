<?php
/**
 * Connection status helpers (API key ↔ Sendora account).
 *
 * @package Sendora
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Sendora_Connection
{
    public const STATUS_OPTION = 'sendora_connection_status';

    /**
     * @return array{
     *   connected: bool,
     *   checked_at: string,
     *   company_name: string,
     *   full_name: string,
     *   billing_email: string,
     *   user_id: string,
     *   message: string
     * }
     */
    public static function get_status(): array
    {
        $stored = get_option(self::STATUS_OPTION, []);
        $stored = is_array($stored) ? $stored : [];
        $settings = Sendora_Settings::get_settings();
        $has_key = trim((string) ($settings['api_key'] ?? '')) !== '';

        return [
            'connected' => $has_key && !empty($stored['connected']),
            'checked_at' => (string) ($stored['checked_at'] ?? ''),
            'company_name' => (string) ($stored['company_name'] ?? ''),
            'full_name' => (string) ($stored['full_name'] ?? ''),
            'billing_email' => (string) ($stored['billing_email'] ?? ''),
            'user_id' => (string) ($stored['user_id'] ?? ''),
            'message' => (string) ($stored['message'] ?? ''),
        ];
    }

    /**
     * @return array{ok: bool, message: string, status: array<string, mixed>}
     */
    public static function probe(?string $api_key = null): array
    {
        $settings = Sendora_Settings::get_settings();
        $key = $api_key !== null && $api_key !== ''
            ? $api_key
            : (string) ($settings['api_key'] ?? '');

        if ($key === '') {
            self::store_status([
                'connected' => false,
                'message' => __('Nenhuma chave de API configurada.', 'sendora'),
            ]);

            return [
                'ok' => false,
                'message' => __('Informe uma chave de API (sk_) e salve, ou cole no campo antes de testar.', 'sendora'),
                'status' => self::get_status(),
            ];
        }

        if (!str_starts_with($key, 'sk_')) {
            return [
                'ok' => false,
                'message' => __('A chave de API da Sendora deve começar com sk_.', 'sendora'),
                'status' => self::get_status(),
            ];
        }

        $client = new Sendora_Api_Client(Sendora_Settings::DEFAULT_API_BASE, $key);
        $me = $client->get_me();

        if (!empty($me['ok']) && is_array($me['data'])) {
            $data = isset($me['data']['data']) && is_array($me['data']['data'])
                ? $me['data']['data']
                : $me['data'];

            self::store_status([
                'connected' => true,
                'company_name' => sanitize_text_field((string) ($data['company_name'] ?? '')),
                'full_name' => sanitize_text_field((string) ($data['full_name'] ?? '')),
                'billing_email' => sanitize_email((string) ($data['billing_email'] ?? '')),
                'user_id' => sanitize_text_field((string) ($data['user_id'] ?? '')),
                'message' => __('Conectado à Sendora.', 'sendora'),
            ]);

            return [
                'ok' => true,
                'message' => __('Conectado à Sendora.', 'sendora'),
                'status' => self::get_status(),
            ];
        }

        $fallback = $client->test_connection();
        self::store_status([
            'connected' => !empty($fallback['ok']),
            'message' => (string) ($fallback['message'] ?? ''),
        ]);

        return [
            'ok' => !empty($fallback['ok']),
            'message' => (string) ($fallback['message'] ?? __('Não foi possível conectar à Sendora.', 'sendora')),
            'status' => self::get_status(),
        ];
    }

    public static function disconnect(): void
    {
        $settings = Sendora_Settings::get_settings();
        $settings['api_key'] = '';
        update_option(Sendora_Settings::OPTION_KEY, $settings, false);
        Sendora_Settings::force_option_no_autoload();
        delete_option(self::STATUS_OPTION);
    }

    /**
     * Workspace label for dashboard cards.
     */
    public static function workspace_label(): string
    {
        $status = self::get_status();
        if ($status['company_name'] !== '') {
            return $status['company_name'];
        }
        if ($status['full_name'] !== '') {
            return $status['full_name'];
        }
        if ($status['billing_email'] !== '') {
            return $status['billing_email'];
        }

        return $status['connected']
            ? __('Conta conectada', 'sendora')
            : __('Não conectado', 'sendora');
    }

    /**
     * @param array<string, mixed> $fields
     */
    private static function store_status(array $fields): void
    {
        $current = self::get_status();
        $merged = array_merge($current, $fields, [
            'checked_at' => gmdate('c'),
        ]);
        update_option(self::STATUS_OPTION, [
            'connected' => !empty($merged['connected']),
            'checked_at' => (string) $merged['checked_at'],
            'company_name' => (string) ($merged['company_name'] ?? ''),
            'full_name' => (string) ($merged['full_name'] ?? ''),
            'billing_email' => (string) ($merged['billing_email'] ?? ''),
            'user_id' => (string) ($merged['user_id'] ?? ''),
            'message' => (string) ($merged['message'] ?? ''),
        ], false);
    }
}
