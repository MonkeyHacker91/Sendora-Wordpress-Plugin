<?php

declare(strict_types=1);

$GLOBALS['sendora_test_options'] = [];
$GLOBALS['sendora_test_http_handler'] = null;
$GLOBALS['sendora_test_actions'] = [];
$GLOBALS['sendora_test_registered_settings'] = [];
$GLOBALS['sendora_test_settings_errors'] = [];
$GLOBALS['sendora_test_json_response'] = null;

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

if (!function_exists('__')) {
    function __(string $text, string $domain = 'default'): string
    {
        return $text;
    }
}

if (!function_exists('add_action')) {
    function add_action(string $hook, callable $callback): void
    {
        $GLOBALS['sendora_test_actions'][$hook][] = $callback;
    }
}

if (!function_exists('register_setting')) {
    function register_setting(string $group, string $option, array $arguments = []): void
    {
        $GLOBALS['sendora_test_registered_settings'][$option] = [
            'group' => $group,
            'arguments' => $arguments,
        ];
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $value): string
    {
        return trim(strip_tags($value));
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw(string $value): string
    {
        return filter_var(trim($value), FILTER_SANITIZE_URL);
    }
}

if (!function_exists('add_settings_error')) {
    function add_settings_error(
        string $setting,
        string $code,
        string $message,
        string $type = 'error'
    ): void {
        $GLOBALS['sendora_test_settings_errors'][] = compact('setting', 'code', 'message', 'type');
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can(string $capability): bool
    {
        return $capability === 'manage_options';
    }
}

if (!function_exists('check_ajax_referer')) {
    function check_ajax_referer(string $action, string|false $query_arg = false): int
    {
        return 1;
    }
}

if (!function_exists('wp_send_json')) {
    function wp_send_json(mixed $response): never
    {
        $GLOBALS['sendora_test_json_response'] = $response;
        throw new RuntimeException('sendora_test_json_complete');
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
    dirname(__DIR__) . '/includes/class-sendora-settings.php',
] as $file) {
    if (is_file($file)) {
        require_once $file;
    }
}
