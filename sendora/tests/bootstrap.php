<?php

declare(strict_types=1);

$GLOBALS['sendora_test_options'] = [];
$GLOBALS['sendora_test_http_handler'] = null;
$GLOBALS['sendora_test_actions'] = [];
$GLOBALS['sendora_test_registered_settings'] = [];
$GLOBALS['sendora_test_settings_errors'] = [];
$GLOBALS['sendora_test_json_response'] = null;
$GLOBALS['sendora_test_logs'] = [];
$GLOBALS['sendora_test_log_id'] = 0;
$GLOBALS['sendora_test_shortcodes'] = [];
$GLOBALS['sendora_test_enqueued_styles'] = [];
$GLOBALS['sendora_test_enqueued_scripts'] = [];
$GLOBALS['sendora_test_transients'] = [];
$GLOBALS['sendora_test_nonce_valid'] = 1;

if (!defined('SENDORA_VERSION')) {
    define('SENDORA_VERSION', '0.1.0-test');
}

if (!defined('SENDORA_PLUGIN_FILE')) {
    define('SENDORA_PLUGIN_FILE', dirname(__DIR__) . '/sendora.php');
}

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

if (!function_exists('add_shortcode')) {
    function add_shortcode(string $tag, callable $callback): void
    {
        $GLOBALS['sendora_test_shortcodes'][$tag] = $callback;
    }
}

if (!function_exists('wp_enqueue_style')) {
    function wp_enqueue_style(
        string $handle,
        string $src = '',
        array $deps = [],
        string|bool|null $version = false
    ): void {
        $GLOBALS['sendora_test_enqueued_styles'][$handle] = compact('src', 'deps', 'version');
    }
}

if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script(
        string $handle,
        string $src = '',
        array $deps = [],
        string|bool|null $version = false,
        bool|array $args = false
    ): void {
        $GLOBALS['sendora_test_enqueued_scripts'][$handle] = compact('src', 'deps', 'version', 'args');
    }
}

if (!function_exists('plugins_url')) {
    function plugins_url(string $path = '', string $plugin = ''): string
    {
        return 'https://example.test/wp-content/plugins/sendora/' . ltrim($path, '/');
    }
}

if (!function_exists('admin_url')) {
    function admin_url(string $path = ''): string
    {
        return 'https://example.test/wp-admin/' . ltrim($path, '/');
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce(string $action): string
    {
        return 'nonce-' . $action;
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

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field(string $value): string
    {
        return trim(strip_tags($value));
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email(string $value): string
    {
        return filter_var(trim($value), FILTER_SANITIZE_EMAIL);
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash(mixed $value): mixed
    {
        return $value;
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html')) {
    function esc_html(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__(string $text, string $domain = 'default'): string
    {
        return esc_html($text);
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
    function check_ajax_referer(
        string $action,
        string|false $query_arg = false,
        bool $stop = true
    ): int|false {
        return $GLOBALS['sendora_test_nonce_valid'];
    }
}

if (!function_exists('wp_send_json')) {
    function wp_send_json(mixed $response, ?int $status_code = null, int $flags = 0): never
    {
        $GLOBALS['sendora_test_json_response'] = $response;
        throw new RuntimeException('sendora_test_json_complete');
    }
}

if (!function_exists('get_transient')) {
    function get_transient(string $transient): mixed
    {
        return $GLOBALS['sendora_test_transients'][$transient] ?? false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient(string $transient, mixed $value, int $expiration = 0): bool
    {
        $GLOBALS['sendora_test_transients'][$transient] = $value;

        return true;
    }
}

if (!isset($GLOBALS['wpdb'])) {
    $GLOBALS['wpdb'] = new class {
        public string $prefix = 'wp_';

        public function insert(string $table, array $data, array $format = []): int
        {
            $GLOBALS['sendora_test_log_id']++;
            $GLOBALS['sendora_test_logs'][$GLOBALS['sendora_test_log_id']] = [
                'id' => $GLOBALS['sendora_test_log_id'],
                'created_at' => (string) ($data['created_at'] ?? ''),
                'source' => (string) ($data['source'] ?? ''),
                'level' => (string) ($data['level'] ?? ''),
                'message' => (string) ($data['message'] ?? ''),
                'context' => (string) ($data['context'] ?? ''),
            ];

            return 1;
        }

        public function get_results(string $query, string $output = OBJECT): array
        {
            $logs = array_values($GLOBALS['sendora_test_logs']);
            usort($logs, static fn (array $a, array $b): int => $b['id'] <=> $a['id']);

            if (preg_match('/LIMIT\s+(\d+)/i', $query, $matches)) {
                $logs = array_slice($logs, 0, (int) $matches[1]);
            }

            if ($output === 'ARRAY_A') {
                return $logs;
            }

            return $logs;
        }

        public function get_var(string $query): int
        {
            return count($GLOBALS['sendora_test_logs']);
        }

        public function query(string $query): int
        {
            if (stripos($query, 'TRUNCATE') !== false) {
                $GLOBALS['sendora_test_logs'] = [];
                $GLOBALS['sendora_test_log_id'] = 0;

                return 0;
            }

            if (preg_match('/DELETE FROM .+ LIMIT\s+(\d+)/i', $query, $matches)) {
                $limit = (int) $matches[1];
                $ids = array_keys($GLOBALS['sendora_test_logs']);
                sort($ids);
                foreach (array_slice($ids, 0, $limit) as $id) {
                    unset($GLOBALS['sendora_test_logs'][$id]);
                }

                return $limit;
            }

            return 0;
        }

        public function prepare(string $query, mixed ...$args): string
        {
            if ($args !== []) {
                $query = preg_replace('/%d/', (string) $args[0], $query, 1) ?? $query;
            }

            return $query;
        }
    };
}

if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

if (!function_exists('current_time')) {
    function current_time(string $type, bool $gmt = false): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key(string $key): string
    {
        return strtolower(preg_replace('/[^a-z0-9_\-]/', '', $key) ?? '');
    }
}

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags(string $text): string
    {
        return strip_tags($text);
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
    dirname(__DIR__) . '/includes/class-sendora-logger.php',
    dirname(__DIR__) . '/includes/class-sendora-api-client.php',
    dirname(__DIR__) . '/includes/class-sendora-settings.php',
    dirname(__DIR__) . '/includes/class-sendora-forms.php',
    dirname(__DIR__) . '/includes/class-sendora-plugin.php',
] as $file) {
    if (is_file($file)) {
        require_once $file;
    }
}
