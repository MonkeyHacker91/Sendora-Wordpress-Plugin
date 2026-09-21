<?php
/**
 * Thin HTTPS client for the Sendora Public API.
 *
 * @package Sendora
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Sendora_Api_Client
{
    private const DEFAULT_BASE_URL = 'https://api.sendora.com.br';

    private string $base_url;

    private string $api_key;

    public function __construct(string $base_url, string $api_key)
    {
        $this->base_url = $base_url;
        $this->api_key = $api_key;
    }

    public static function from_options(): self
    {
        $settings = get_option('sendora_settings', []);
        $settings = is_array($settings) ? $settings : [];
        $base_url = (string) ($settings['api_base'] ?? self::DEFAULT_BASE_URL);
        $api_key = (string) ($settings['api_key'] ?? '');

        return new self(rtrim($base_url, '/'), trim($api_key));
    }

    /**
     * @return array{ok: bool, status: int, data: mixed, error: ?string}
     */
    public function request(string $method, string $path, ?array $body = null): array
    {
        if (!$this->is_https_base()) {
            return [
                'ok' => false,
                'status' => 0,
                'data' => null,
                'error' => __('A URL da API da Sendora deve usar HTTPS.', 'sendora'),
            ];
        }

        if ($this->api_key === '') {
            return [
                'ok' => false,
                'status' => 0,
                'data' => null,
                'error' => __('A chave de API da Sendora não está configurada.', 'sendora'),
            ];
        }

        $path = '/' . ltrim($path, '/');
        if (!self::is_safe_api_path($path)) {
            return [
                'ok' => false,
                'status' => 0,
                'data' => null,
                'error' => __('Caminho da API da Sendora inválido.', 'sendora'),
            ];
        }

        $arguments = [
            'method' => strtoupper($method),
            'timeout' => 15,
            'redirection' => 0,
            'sslverify' => true,
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key,
            ],
        ];

        if ($body !== null) {
            $arguments['headers']['Content-Type'] = 'application/json';
            $arguments['body'] = wp_json_encode($body);
        }

        $response = wp_remote_request($this->base_url . $path, $arguments);

        if (is_wp_error($response)) {
            $error = $response->get_error_message();
            $this->log_request_failure($method, $path, 0, $error, null);

            return [
                'ok' => false,
                'status' => 0,
                'data' => null,
                'error' => $this->redact_secrets($error),
            ];
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $raw_body = wp_remote_retrieve_body($response);
        $data = $this->decode_body($raw_body);
        $ok = $status >= 200 && $status < 300;

        if (!$ok) {
            $error = $this->extract_error($data, $status);
            $this->log_request_failure($method, $path, $status, $error, $data);

            return [
                'ok' => false,
                'status' => $status,
                'data' => $data,
                'error' => $this->redact_secrets($error),
            ];
        }

        return [
            'ok' => true,
            'status' => $status,
            'data' => $data,
            'error' => null,
        ];
    }

    public function upsert_contact(array $fields): array
    {
        return $this->request('POST', '/api/contacts', $fields);
    }

    public function list_flows(): array
    {
        return $this->request('GET', '/api/flows');
    }

    /**
     * Lightweight auth check (does not require flows:write).
     *
     * @return array{ok: bool, status: int, data: mixed, error: ?string}
     */
    public function get_me(): array
    {
        return $this->request('GET', '/api/me');
    }

    public function trigger_flow(
        string $flow_id,
        string $phone,
        ?string $message = null
    ): array {
        $flow_id = sanitize_text_field($flow_id);
        if ($flow_id === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $flow_id)) {
            return [
                'ok' => false,
                'status' => 0,
                'data' => null,
                'error' => __('ID de fluxo inválido.', 'sendora'),
            ];
        }

        $body = ['phone' => $phone];

        if ($message !== null) {
            $body['message'] = $message;
        }

        return $this->request(
            'POST',
            '/api/flows/' . rawurlencode($flow_id) . '/trigger',
            $body
        );
    }

    public function send_message(array $payload): array
    {
        return $this->request('POST', '/api/messages/send', $payload);
    }

    /**
     * Send an approved Meta WABA template (POST /api/messages/send-template).
     *
     * @param array<string, mixed> $payload
     * @return array{ok: bool, status: int, data: mixed, error: ?string}
     */
    public function send_template(array $payload): array
    {
        return $this->request('POST', '/api/messages/send-template', $payload);
    }

    /**
     * List message templates from the Sendora account (GET /api/templates).
     *
     * @return array{ok: bool, status: int, data: mixed, error: ?string}
     */
    public function list_templates(): array
    {
        return $this->request('GET', '/api/templates');
    }

    /**
     * WhatsApp QR Code instances (GET /api/connections).
     *
     * @return array{ok: bool, status: int, data: mixed, error: ?string}
     */
    public function list_connections(): array
    {
        return $this->request('GET', '/api/connections');
    }

    /**
     * Official Meta WABA accounts (GET /api/meta/waba).
     *
     * @return array{ok: bool, status: int, data: mixed, error: ?string}
     */
    public function list_waba(): array
    {
        return $this->request('GET', '/api/meta/waba');
    }

    /**
     * Official Meta message templates (GET /api/meta/templates).
     *
     * @return array{ok: bool, status: int, data: mixed, error: ?string}
     */
    public function list_meta_templates(?string $api_settings_id = null): array
    {
        $api_settings_id = $api_settings_id !== null ? sanitize_text_field($api_settings_id) : '';
        if ($api_settings_id !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $api_settings_id)) {
            return $this->request(
                'GET',
                '/api/meta/templates?api_settings_id=' . rawurlencode($api_settings_id)
            );
        }

        return $this->request('GET', '/api/meta/templates');
    }

    /**
     * @return array{ok: bool, status: int, data: mixed, error: ?string}
     */
    public function list_funnels(): array
    {
        return $this->request('GET', '/api/funnels');
    }

    /**
     * @return array{ok: bool, status: int, data: mixed, error: ?string}
     */
    public function list_stages(?string $funnel_id = null): array
    {
        $funnel_id = $funnel_id !== null ? sanitize_text_field($funnel_id) : '';
        if ($funnel_id !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $funnel_id)) {
            return $this->request('GET', '/api/funnels/' . rawurlencode($funnel_id) . '/stages');
        }

        return $this->request('GET', '/api/stages');
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function test_connection(): array
    {
        $result = $this->get_me();

        if ($result['ok']) {
            return ['ok' => true, 'message' => __('Conectado à Sendora.', 'sendora')];
        }

        // Fallback for older keys/scopes that can list flows but not /api/me.
        if (in_array($result['status'], [401, 403, 404], true)) {
            $flows = $this->list_flows();
            if ($flows['ok']) {
                return ['ok' => true, 'message' => __('Conectado à Sendora.', 'sendora')];
            }
            if (in_array($flows['status'], [401, 403], true)) {
                return ['ok' => false, 'message' => __('Chave de API inválida.', 'sendora')];
            }

            return [
                'ok' => false,
                'message' => $flows['error'] ?? __('Não foi possível conectar à Sendora.', 'sendora'),
            ];
        }

        if (in_array($result['status'], [401, 403], true)) {
            return ['ok' => false, 'message' => __('Chave de API inválida.', 'sendora')];
        }

        return [
            'ok' => false,
            'message' => $result['error'] ?? __('Não foi possível conectar à Sendora.', 'sendora'),
        ];
    }

    private function is_https_base(): bool
    {
        $parts = wp_parse_url($this->base_url);

        return is_array($parts)
            && ($parts['scheme'] ?? '') === 'https'
            && !empty($parts['host']);
    }

    private static function is_safe_api_path(string $path): bool
    {
        if ($path === '' || str_contains($path, '..') || str_contains($path, '\\')) {
            return false;
        }

        return (bool) preg_match('#^/api/[A-Za-z0-9/_-]+(?:\?[A-Za-z0-9_=&%-]+)?$#', $path);
    }

    private function decode_body(string $body): mixed
    {
        if ($body === '') {
            return null;
        }

        $decoded = json_decode($body, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $body;
    }

    private function extract_error(mixed $data, int $status): string
    {
        if (is_array($data)) {
            foreach (['message', 'error'] as $key) {
                if (isset($data[$key]) && is_string($data[$key])) {
                    return wp_strip_all_tags($data[$key]);
                }
            }
        }

        if (is_string($data) && $data !== '') {
            return wp_strip_all_tags($data);
        }

        return sprintf(
            /* translators: %d: HTTP status code from the Sendora API. */
            __('A requisição à API da Sendora falhou com HTTP %d.', 'sendora'),
            $status
        );
    }

    private function log_request_failure(
        string $method,
        string $path,
        int $status,
        string $error,
        mixed $data
    ): void {
        if (!class_exists('Sendora_Logger')) {
            return;
        }

        $message = $this->redact_secrets($error);
        $context = [
            'method' => strtoupper($method),
            'path' => $path,
            'status' => $status,
        ];

        if ($data !== null) {
            $context['response'] = $this->truncate_for_log(
                is_string($data) ? $data : (string) wp_json_encode($data)
            );
            if (is_string($context['response'])) {
                $context['response'] = $this->redact_secrets($context['response']);
            }
        }

        Sendora_Logger::log('api', 'error', $message, $context);
    }

    private function redact_secrets(string $text): string
    {
        $text = preg_replace('/\bsk_[A-Za-z0-9_-]+\b/', '[redacted]', $text) ?? $text;

        if ($this->api_key !== '') {
            $text = str_replace($this->api_key, '[redacted]', $text);
        }

        return $text;
    }

    private function truncate_for_log(?string $value, int $max = 512): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (strlen($value) <= $max) {
            return $value;
        }

        return substr($value, 0, $max) . '…';
    }
}
