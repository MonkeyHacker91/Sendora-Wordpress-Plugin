<?php

declare(strict_types=1);

$GLOBALS['sendora_test_options'] = [];
$GLOBALS['sendora_test_http_handler'] = null;

if (!function_exists('get_option')) {
    function get_option(string $option, mixed $default = false): mixed
    {
        return $GLOBALS['sendora_test_options'][$option] ?? $default;
    }
}

if (!function_exists('wp_remote_request')) {
    function wp_remote_request(string $url, array $arguments = []): mixed
    {
        $handler = $GLOBALS['sendora_test_http_handler'];

        if (!is_callable($handler)) {
            throw new RuntimeException('No test HTTP handler configured.');
        }

        return $handler($url, $arguments);
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code(array $response): int
    {
        return (int) ($response['response']['code'] ?? 0);
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body(array $response): string
    {
        return (string) ($response['body'] ?? '');
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error(mixed $value): bool
    {
        return $value instanceof WP_Error;
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode(mixed $value, int $flags = 0, int $depth = 512): string|false
    {
        return json_encode($value, $flags, $depth);
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        private string $message;

        public function __construct(string $message)
        {
            $this->message = $message;
        }

        public function get_error_message(): string
        {
            return $this->message;
        }
    }
}

foreach ([
    dirname(__DIR__) . '/includes/class-sendora-phone.php',
    dirname(__DIR__) . '/includes/class-sendora-api-client.php',
] as $file) {
    if (is_file($file)) {
        require_once $file;
    }
}
