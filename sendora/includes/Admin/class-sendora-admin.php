<?php
/**
 * Fluent-style admin shell and page router.
 *
 * @package Sendora
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Sendora_Admin
{
    public const MENU_SLUG = 'sendora';

    public function run(): void
    {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_sendora_test_connection', [self::class, 'ajax_test_connection']);
        add_action('wp_ajax_sendora_disconnect', [self::class, 'ajax_disconnect']);
        add_action('wp_ajax_sendora_test_event', [self::class, 'ajax_test_event']);
    }

    public function register_menu(): void
    {
        add_menu_page(
            __('Sendora', 'sendora'),
            __('Sendora', 'sendora'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render_dashboard'],
            'dashicons-format-chat',
            58
        );

        $pages = [
            'sendora' => [__('Visão geral', 'sendora'), [$this, 'render_dashboard']],
            'sendora-connection' => [__('Conexão', 'sendora'), [$this, 'render_connection']],
            'sendora-forms' => [__('Formulários', 'sendora'), [$this, 'render_forms']],
            'sendora-widget' => [__('Widget', 'sendora'), [$this, 'render_widget']],
            'sendora-woocommerce' => [__('WooCommerce', 'sendora'), [$this, 'render_woo']],
            'sendora-automations' => [__('Automações', 'sendora'), [$this, 'render_automations']],
            'sendora-settings' => [__('Configurações', 'sendora'), [$this, 'render_settings']],
            'sendora-logs' => [__('Logs', 'sendora'), [$this, 'render_logs']],
        ];

        foreach ($pages as $slug => [$title, $callback]) {
            add_submenu_page(
                self::MENU_SLUG,
                $title,
                $title,
                'manage_options',
                $slug,
                $callback
            );
        }
    }

    public function enqueue_assets(string $hook_suffix): void
    {
        if (!str_contains($hook_suffix, 'sendora')) {
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
                'nonce' => wp_create_nonce('sendora_admin'),
                'i18n' => [
                    'testing' => __('Testando…', 'sendora'),
                    'failed' => __('Falha no teste de conexão.', 'sendora'),
                    'done' => __('Teste de conexão concluído.', 'sendora'),
                    'disconnectConfirm' => __('Desconectar este site da Sendora? A chave de API será removida.', 'sendora'),
                    'disconnecting' => __('Desconectando…', 'sendora'),
                    'sendingEvent' => __('Enviando evento…', 'sendora'),
                ],
            ]
        );
    }

    public function render_dashboard(): void
    {
        $this->render_page('sendora', 'pages/dashboard.php', [
            'status' => Sendora_Connection::get_status(),
            'settings' => Sendora_Settings::get_settings(),
            'logs' => Sendora_Logger::list(8),
            'integrations' => $this->active_integrations(Sendora_Settings::get_settings()),
        ]);
    }

    public function render_connection(): void
    {
        $this->render_page('sendora-connection', 'pages/connection.php', [
            'settings' => Sendora_Settings::get_settings(),
            'status' => Sendora_Connection::get_status(),
            'masked_api_key' => Sendora_Settings::mask_api_key(
                (string) (Sendora_Settings::get_settings()['api_key'] ?? '')
            ),
        ]);
    }

    public function render_forms(): void
    {
        $settings = Sendora_Settings::get_settings();
        $this->render_page('sendora-forms', 'pages/forms.php', [
            'settings' => $settings,
            'flows' => Sendora_Settings::load_flows_public($settings),
            'cf7_forms' => class_exists('Sendora_CF7') ? Sendora_CF7::list_forms() : [],
        ]);
    }

    public function render_widget(): void
    {
        $settings = Sendora_Settings::get_settings();
        $this->render_page('sendora-widget', 'pages/widget.php', [
            'settings' => $settings,
            'pages' => Sendora_Settings::list_pages_for_widget(),
        ]);
    }

    public function render_woo(): void
    {
        $settings = Sendora_Settings::get_settings();
        $this->render_page('sendora-woocommerce', 'pages/woocommerce.php', [
            'settings' => $settings,
            'flows' => Sendora_Settings::load_flows_public($settings),
            'woo_active' => defined('WC_VERSION') || class_exists('WooCommerce'),
        ]);
    }

    public function render_automations(): void
    {
        $this->render_page('sendora-automations', 'pages/automations.php', []);
    }

    public function render_settings(): void
    {
        $this->render_page('sendora-settings', 'pages/general.php', [
            'settings' => Sendora_Settings::get_settings(),
        ]);
    }

    public function render_logs(): void
    {
        $this->guard();
        $cleared = false;
        if (
            isset($_POST['sendora_clear_logs'])
            && check_admin_referer('sendora_clear_logs')
        ) {
            Sendora_Logger::clear();
            $cleared = true;
        }
        $this->render_page('sendora-logs', 'pages/logs.php', [
            'logs' => Sendora_Logger::list(100),
            'cleared' => $cleared,
        ]);
    }

    public static function ajax_test_connection(): void
    {
        check_ajax_referer('sendora_admin', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json(['ok' => false, 'message' => __('Sem permissão.', 'sendora')], 403);
        }

        $posted_key = isset($_POST['api_key'])
            ? trim(sanitize_text_field(wp_unslash((string) $_POST['api_key'])))
            : '';

        $result = Sendora_Connection::probe($posted_key !== '' ? $posted_key : null);
        $message = preg_replace('/\bsk_[A-Za-z0-9_-]+\b/', '[redacted]', $result['message']) ?? $result['message'];

        wp_send_json([
            'ok' => !empty($result['ok']),
            'message' => wp_strip_all_tags($message),
            'workspace' => Sendora_Connection::workspace_label(),
            'connected' => !empty($result['status']['connected']),
        ]);
    }

    public static function ajax_disconnect(): void
    {
        check_ajax_referer('sendora_admin', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json(['ok' => false, 'message' => __('Sem permissão.', 'sendora')], 403);
        }

        Sendora_Connection::disconnect();
        wp_send_json([
            'ok' => true,
            'message' => __('Desconectado da Sendora.', 'sendora'),
        ]);
    }

    public static function ajax_test_event(): void
    {
        check_ajax_referer('sendora_admin', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json(['ok' => false, 'message' => __('Sem permissão.', 'sendora')], 403);
        }

        $status = Sendora_Connection::get_status();
        if (empty($status['connected'])) {
            wp_send_json([
                'ok' => false,
                'message' => __('Conecte a API antes de enviar um evento de teste.', 'sendora'),
            ]);
        }

        // Dry diagnostic only — does not create contacts (no phone). Logs locally.
        Sendora_Logger::log('event', 'info', 'Test event from admin dashboard.', [
            'event' => 'admin_test',
            'source' => 'wordpress',
        ]);

        wp_send_json([
            'ok' => true,
            'message' => __('Evento de teste registrado nos logs locais.', 'sendora'),
        ]);
    }

    private function guard(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Você não tem permissão para gerenciar a Sendora.', 'sendora'));
        }
    }

    /**
     * @param array<string, mixed> $vars
     */
    private function render_page(string $current, string $relative, array $vars): void
    {
        $this->guard();
        extract($vars, EXTR_SKIP);
        $page_file = SENDORA_PLUGIN_DIR . 'admin/views/' . $relative;
        require SENDORA_PLUGIN_DIR . 'admin/views/layout.php';
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<int, array{label: string, active: bool}>
     */
    private function active_integrations(array $settings): array
    {
        return [
            [
                'label' => __('API Sendora', 'sendora'),
                'active' => Sendora_Connection::get_status()['connected'],
            ],
            [
                'label' => __('Widget de chat', 'sendora'),
                'active' => !empty($settings['widget_enabled']) && (string) ($settings['widget_id'] ?? '') !== '',
            ],
            [
                'label' => __('Contact Form 7', 'sendora'),
                'active' => defined('WPCF7_VERSION') && class_exists('Sendora_CF7'),
            ],
            [
                'label' => __('WooCommerce', 'sendora'),
                'active' => (defined('WC_VERSION') || class_exists('WooCommerce'))
                    && class_exists('Sendora_WooCommerce'),
            ],
            [
                'label' => __('Formulário nativo', 'sendora'),
                'active' => true,
            ],
        ];
    }
}
