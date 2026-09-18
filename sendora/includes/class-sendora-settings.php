<?php

declare(strict_types=1);

final class Sendora_Settings
{
    public const OPTION_KEY = 'sendora_settings';

    private const PAGE_SLUG = 'sendora';
    private const LOGS_PAGE_SLUG = 'sendora-logs';
    private const SETTINGS_GROUP = 'sendora_settings_group';
    private const DEFAULT_API_BASE = 'https://api.sendora.com.br';

    public function run(): void
    {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_sendora_test_connection', [self::class, 'ajax_test_connection']);
    }

    public function register_menu(): void
    {
        add_menu_page(
            __('Sendora', 'sendora'),
            __('Sendora', 'sendora'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_page'],
            'dashicons-format-chat',
            58
        );

        add_submenu_page(
            self::PAGE_SLUG,
            __('Sendora logs', 'sendora'),
            __('Logs', 'sendora'),
            'manage_options',
            self::LOGS_PAGE_SLUG,
            [$this, 'render_logs_page']
        );
    }

    public function register_settings(): void
    {
        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_KEY,
            [
                'type' => 'array',
                'sanitize_callback' => [self::class, 'sanitize'],
                'default' => self::defaults(),
            ]
        );
    }

    public function enqueue_assets(string $hook_suffix): void
    {
        $allowed = [
            'toplevel_page_' . self::PAGE_SLUG,
            'sendora_page_' . self::LOGS_PAGE_SLUG,
        ];
        if (!in_array($hook_suffix, $allowed, true)) {
            return;
        }

        wp_enqueue_style(
            'sendora-admin',
            plugins_url('admin/css/admin.css', SENDORA_PLUGIN_FILE),
            [],
            SENDORA_VERSION
        );
        wp_enqueue_script(
            'sendora-admin',
            plugins_url('admin/js/admin.js', SENDORA_PLUGIN_FILE),
            [],
            SENDORA_VERSION,
            true
        );
        wp_localize_script(
            'sendora-admin',
            'SendoraAdmin',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sendora_test_connection'),
            ]
        );
    }

    public function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to manage Sendora settings.', 'sendora'));
        }

        $settings = self::get_settings();
        $flows = $this->load_flows($settings);
        $masked_api_key = self::mask_api_key($settings['api_key']);

        require SENDORA_PLUGIN_DIR . 'admin/views/settings.php';
    }

    public function render_logs_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to view Sendora logs.', 'sendora'));
        }

        $cleared = false;

        if (
            isset($_POST['sendora_clear_logs'])
            && check_admin_referer('sendora_clear_logs')
        ) {
            Sendora_Logger::clear();
            $cleared = true;
        }

        $logs = Sendora_Logger::list(100);

        require SENDORA_PLUGIN_DIR . 'admin/views/logs.php';
    }

    /**
     * @param mixed $input Untrusted Settings API input.
     * @return array<string, bool|string>
     */
    public static function sanitize(mixed $input): array
    {
        $input = is_array($input) ? $input : [];
        $saved = self::get_settings();
        $settings = self::defaults();

        $api_base = rtrim(esc_url_raw((string) ($input['api_base'] ?? '')), '/');
        if (!self::is_https_url($api_base)) {
            add_settings_error(
                self::OPTION_KEY,
                'sendora_api_base_https',
                __('The Sendora API base URL must use HTTPS.', 'sendora')
            );
            $api_base = $saved['api_base'];
        }
        $settings['api_base'] = $api_base;

        $api_key = trim(sanitize_text_field((string) ($input['api_key'] ?? '')));
        if ($api_key === '') {
            $settings['api_key'] = $saved['api_key'];
        } elseif (!str_starts_with($api_key, 'sk_')) {
            add_settings_error(
                self::OPTION_KEY,
                'sendora_api_key_prefix',
                __('The Sendora API key must start with sk_.', 'sendora')
            );
            $settings['api_key'] = $saved['api_key'];
        } else {
            $settings['api_key'] = $api_key;
        }

        foreach (['widget_id', 'default_flow_id', 'woo_paid_flow_id'] as $field) {
            $settings[$field] = sanitize_text_field((string) ($input[$field] ?? ''));
        }

        $settings['default_cc'] = preg_replace(
            '/\D+/',
            '',
            sanitize_text_field((string) ($input['default_cc'] ?? '55'))
        ) ?: '55';

        foreach (['widget_enabled', 'woo_on_created', 'woo_on_paid', 'woo_on_cancelled'] as $field) {
            $settings[$field] = isset($input[$field]) && (string) $input[$field] === '1';
        }

        $paid_mode = sanitize_text_field((string) ($input['woo_paid_mode'] ?? 'off'));
        $settings['woo_paid_mode'] = in_array($paid_mode, ['flow', 'message', 'off'], true)
            ? $paid_mode
            : 'off';

        return $settings;
    }

    /**
     * @return array<string, bool|string>
     */
    public static function get_settings(): array
    {
        $saved = get_option(self::OPTION_KEY, []);

        return array_merge(self::defaults(), is_array($saved) ? $saved : []);
    }

    public static function mask_api_key(string $api_key): string
    {
        if ($api_key === '') {
            return '';
        }

        return '••••••••' . substr($api_key, -4);
    }

    public static function ajax_test_connection(): void
    {
        check_ajax_referer('sendora_test_connection', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json([
                'ok' => false,
                'message' => __('You are not allowed to test this connection.', 'sendora'),
            ], 403);
        }

        $result = Sendora_Api_Client::from_options()->test_connection();
        $api_key = self::get_settings()['api_key'];
        $message = (string) ($result['message'] ?? __('Unable to connect to Sendora.', 'sendora'));

        if ($api_key !== '') {
            $message = str_replace($api_key, '[redacted]', $message);
        }

        wp_send_json([
            'ok' => !empty($result['ok']),
            'message' => $message,
        ]);
    }

    /**
     * @return array<string, bool|string>
     */
    private static function defaults(): array
    {
        return [
            'api_base' => self::DEFAULT_API_BASE,
            'api_key' => '',
            'widget_enabled' => false,
            'widget_id' => '',
            'default_flow_id' => '',
            'default_cc' => '55',
            'woo_on_created' => false,
            'woo_on_paid' => false,
            'woo_on_cancelled' => false,
            'woo_paid_flow_id' => '',
            'woo_paid_mode' => 'off',
        ];
    }

    private static function is_https_url(string $url): bool
    {
        $parts = parse_url($url);

        return is_array($parts)
            && ($parts['scheme'] ?? '') === 'https'
            && !empty($parts['host']);
    }

    /**
     * @param array<string, bool|string> $settings
     * @return array<int, array{id: string, name: string}>
     */
    private function load_flows(array $settings): array
    {
        if ($settings['api_key'] === '') {
            return [];
        }

        $result = Sendora_Api_Client::from_options()->list_flows();
        if (empty($result['ok']) || !is_array($result['data'])) {
            return [];
        }

        $items = isset($result['data']['data']) && is_array($result['data']['data'])
            ? $result['data']['data']
            : $result['data'];
        $flows = [];

        foreach ($items as $flow) {
            if (!is_array($flow) || empty($flow['id'])) {
                continue;
            }

            $flows[] = [
                'id' => sanitize_text_field((string) $flow['id']),
                'name' => sanitize_text_field((string) ($flow['name'] ?? $flow['id'])),
            ];
        }

        return $flows;
    }
}
