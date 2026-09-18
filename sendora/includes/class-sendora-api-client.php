<?php

declare(strict_types=1);

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
        $arguments = [
            'method' => strtoupper($method),
            'timeout' => 15,
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key,
            ],
        ];

        if ($body !== null) {
            $arguments['headers']['Content-Type'] = 'application/json';
            $arguments['body'] = wp_json_encode($body);
        }

        $response = wp_remote_request(
            $this->base_url . '/' . ltrim($path, '/'),
            $arguments
        );

        if (is_wp_error($response)) {
            $error = $response->get_error_message();
            $this->log_request_failure($method, $path, 0, $error, null);

            return [
                'ok' => false,
                'status' => 0,
                'data' => null,
                'error' => $error,
            ];
        }

        $status = wp_remote_retrieve_response_code($response);
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
                'error' => $error,
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

    public function trigger_flow(
        string $flow_id,
        string $phone,
        ?string $message = null
    ): array {
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
     * @return array{ok: bool, message: string}
     */
    public function test_connection(): array
    {
        $result = $this->list_flows();

        if ($result['ok']) {
            return ['ok' => true, 'message' => 'Connected to Sendora.'];
        }

        if (in_array($result['status'], [401, 403], true)) {
            return ['ok' => false, 'message' => 'Invalid API key.'];
        }

        return [
            'ok' => false,
            'message' => $result['error'] ?? 'Unable to connect to Sendora.',
        ];
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
                    return $data[$key];
                }
            }
        }

        if (is_string($data) && $data !== '') {
            return $data;
        }

        return 'Sendora API request failed with HTTP ' . $status . '.';
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
                is_string($data) ? $data : wp_json_encode($data)
            );
        }

        Sendora_Logger::log('api', 'error', $message, $context);
    }

    private function redact_secrets(string $text): string
    {
        if ($this->api_key === '') {
            return $text;
        }

        return str_replace($this->api_key, '[redacted]', $text);
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
