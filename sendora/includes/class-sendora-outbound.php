<?php
/**
 * Shared outbound message helpers (templates → WhatsApp send).
 *
 * @package Sendora
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Sendora_Outbound
{
    public const PROVIDER_EVOLUTION = 'evolution';
    public const PROVIDER_META = 'meta';

    /**
     * @param array<string, string> $vars
     * @param array<string, string> $contact Fields for upsert (phone required).
     * @param array{
     *   provider?: string,
     *   instance_id?: string,
     *   template_language?: string,
     *   body_vars?: array<int, string>|string,
     *   skip_upsert?: bool
     * } $options
     * @return array{ok: bool, error?: string, status?: int, template_name?: string, provider?: string}
     */
    public static function upsert_and_send_template(
        array $contact,
        string $template_id,
        array $vars = [],
        array $options = []
    ): array {
        $phone = trim((string) ($contact['phone'] ?? ''));
        if ($phone === '') {
            return ['ok' => false, 'error' => __('Telefone inválido.', 'sendora'), 'status' => 0];
        }

        $template_id = sanitize_text_field($template_id);
        if ($template_id === '') {
            return ['ok' => false, 'error' => __('Mensagem não configurada.', 'sendora'), 'status' => 0];
        }

        $provider = self::normalize_provider((string) ($options['provider'] ?? self::PROVIDER_EVOLUTION));
        $instance_id = sanitize_text_field((string) ($options['instance_id'] ?? ''));
        $language = sanitize_text_field((string) ($options['template_language'] ?? 'pt_BR'));
        if ($language === '' || !preg_match('/^[A-Za-z]{2}(_[A-Za-z]{2})?$/', $language)) {
            $language = 'pt_BR';
        }
        $body_vars = self::normalize_body_vars($options['body_vars'] ?? []);

        $client = Sendora_Api_Client::from_options();
        if (empty($options['skip_upsert'])) {
            $contact_result = $client->upsert_contact($contact);
            if (empty($contact_result['ok'])) {
                return [
                    'ok' => false,
                    'error' => (string) ($contact_result['error'] ?? __('Falha ao sincronizar contato.', 'sendora')),
                    'status' => (int) ($contact_result['status'] ?? 0),
                ];
            }
        }

        $merged = array_merge(
            [
                'name' => (string) ($contact['name'] ?? ''),
                'phone' => $phone,
                'store_name' => function_exists('get_bloginfo') ? (string) get_bloginfo('name') : '',
                'company' => function_exists('get_bloginfo') ? (string) get_bloginfo('name') : '',
            ],
            $vars
        );

        if ($provider === self::PROVIDER_META) {
            return self::send_meta_template(
                $client,
                $phone,
                $template_id,
                $language,
                $instance_id,
                $body_vars,
                $merged
            );
        }

        return self::send_evolution_template(
            $client,
            $phone,
            $template_id,
            $instance_id,
            $merged
        );
    }

    /**
     * @param array<string, string> $merged
     * @param array<int, string> $body_vars
     * @return array{ok: bool, error?: string, status?: int, template_name?: string, provider?: string}
     */
    private static function send_meta_template(
        Sendora_Api_Client $client,
        string $phone,
        string $template_name,
        string $language,
        string $api_settings_id,
        array $body_vars,
        array $merged
    ): array {
        $payload = [
            'phone' => $phone,
            'template_name' => $template_name,
            'language' => $language,
        ];
        if ($api_settings_id !== '') {
            $payload['api_settings_id'] = $api_settings_id;
        }

        $components = self::build_meta_body_components($body_vars, $merged);
        if ($components !== []) {
            $payload['components'] = $components;
        }

        $send = $client->send_template($payload);
        if (empty($send['ok'])) {
            return [
                'ok' => false,
                'error' => (string) ($send['error'] ?? __('Falha ao enviar template Meta.', 'sendora')),
                'status' => (int) ($send['status'] ?? 0),
                'template_name' => $template_name,
                'provider' => self::PROVIDER_META,
            ];
        }

        return [
            'ok' => true,
            'template_name' => $template_name,
            'status' => (int) ($send['status'] ?? 200),
            'provider' => self::PROVIDER_META,
        ];
    }

    /**
     * @param array<string, string> $merged
     * @return array{ok: bool, error?: string, status?: int, template_name?: string, provider?: string}
     */
    private static function send_evolution_template(
        Sendora_Api_Client $client,
        string $phone,
        string $template_id,
        string $instance_name,
        array $merged
    ): array {
        $template = self::find_template($template_id);
        if ($template === null || trim((string) ($template['content'] ?? '')) === '') {
            return [
                'ok' => false,
                'error' => __('Mensagem/template não encontrado.', 'sendora'),
                'status' => 0,
                'provider' => self::PROVIDER_EVOLUTION,
            ];
        }

        $text = Sendora_Template_Vars::resolve((string) $template['content'], $merged);
        if (trim($text) === '') {
            return [
                'ok' => false,
                'error' => __('Mensagem vazia após variáveis.', 'sendora'),
                'status' => 0,
                'provider' => self::PROVIDER_EVOLUTION,
            ];
        }

        $payload = [
            'phone' => $phone,
            'text' => $text,
            'message' => $text,
        ];
        if ($instance_name !== '') {
            $payload['instance_name'] = $instance_name;
        }

        $send = $client->send_message($payload);
        if (empty($send['ok'])) {
            return [
                'ok' => false,
                'error' => (string) ($send['error'] ?? __('Falha ao enviar mensagem.', 'sendora')),
                'status' => (int) ($send['status'] ?? 0),
                'template_name' => (string) ($template['name'] ?? ''),
                'provider' => self::PROVIDER_EVOLUTION,
            ];
        }

        return [
            'ok' => true,
            'template_name' => (string) ($template['name'] ?? $template_id),
            'status' => (int) ($send['status'] ?? 200),
            'provider' => self::PROVIDER_EVOLUTION,
        ];
    }

    /**
     * @param array<int, string> $body_vars
     * @param array<string, string> $merged
     * @return list<array{type: string, parameters: list<array{type: string, text: string}>}>
     */
    public static function build_meta_body_components(array $body_vars, array $merged): array
    {
        if ($body_vars === []) {
            return [];
        }

        $parameters = [];
        foreach ($body_vars as $key) {
            $value = (string) ($merged[$key] ?? '');
            $parameters[] = [
                'type' => 'text',
                'text' => $value !== '' ? $value : '-',
            ];
        }

        return [
            [
                'type' => 'body',
                'parameters' => $parameters,
            ],
        ];
    }

    public static function normalize_provider(string $raw): string
    {
        $raw = strtolower(sanitize_key($raw));
        if ($raw === 'whatsapp' || $raw === '' || $raw === 'qr') {
            return self::PROVIDER_EVOLUTION;
        }
        if ($raw === self::PROVIDER_META || $raw === 'oficial' || $raw === 'official') {
            return self::PROVIDER_META;
        }

        return $raw === self::PROVIDER_EVOLUTION ? self::PROVIDER_EVOLUTION : self::PROVIDER_EVOLUTION;
    }

    /**
     * @param mixed $raw
     * @return array<int, string>
     */
    public static function normalize_body_vars(mixed $raw): array
    {
        if (is_string($raw)) {
            $parts = preg_split('/[,;]+/', $raw) ?: [];
        } elseif (is_array($raw)) {
            $parts = $raw;
        } else {
            return [];
        }

        $vars = [];
        foreach ($parts as $part) {
            $key = sanitize_key((string) $part);
            if ($key === '') {
                continue;
            }
            $vars[] = $key;
        }

        return array_values(array_unique($vars));
    }

    /**
     * @return array{id: string, name: string, content: string, type: string}|null
     */
    public static function find_template(string $template_id): ?array
    {
        $template_id = sanitize_text_field($template_id);
        if ($template_id === '') {
            return null;
        }

        $settings = Sendora_Settings::get_settings();
        foreach (Sendora_Settings::load_templates_public($settings) as $template) {
            if (($template['id'] ?? '') === $template_id) {
                return $template;
            }
        }

        $result = Sendora_Api_Client::from_options()->list_templates();
        if (empty($result['ok']) || !is_array($result['data'])) {
            return null;
        }

        $items = isset($result['data']['data']) && is_array($result['data']['data'])
            ? $result['data']['data']
            : $result['data'];

        foreach ($items as $row) {
            if (!is_array($row) || (string) ($row['id'] ?? '') !== $template_id) {
                continue;
            }

            return [
                'id' => sanitize_text_field((string) $row['id']),
                'name' => sanitize_text_field((string) ($row['name'] ?? $row['id'])),
                'content' => (string) ($row['content'] ?? ''),
                'type' => sanitize_text_field((string) ($row['type'] ?? 'text')),
            ];
        }

        return null;
    }

    /**
     * Sample context for admin “test send”.
     *
     * @return array<string, string>
     */
    public static function sample_order_vars(): array
    {
        $store = function_exists('get_bloginfo') ? (string) get_bloginfo('name') : 'Loja';

        return [
            'name' => 'Cliente Teste',
            'first_name' => 'Cliente',
            'phone' => '',
            'company' => $store,
            'store_name' => $store,
            'order_id' => '9999',
            'order_number' => '9999',
            'order_total' => 'R$ 199,90',
            'order_status' => 'processing',
            'order_date' => wp_date('d/m/Y'),
            'payment_method' => 'Pix',
            'shipping_address' => 'Rua Exemplo, 100 — São Paulo/SP',
            'product' => 'Produto Exemplo',
            'tracking' => 'BR123456789BR',
            'order_link' => home_url('/'),
            'coupon' => 'SENDORA10',
        ];
    }
}
