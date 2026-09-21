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
        add_action('wp_ajax_sendora_test_send', [self::class, 'ajax_test_send']);
        add_action('wp_ajax_sendora_list_meta_templates', [self::class, 'ajax_list_meta_templates']);
        add_action('wp_ajax_sendora_onboarding', [self::class, 'ajax_onboarding']);
    }

    public function register_menu(): void
    {
        add_menu_page(
            __('Sendora', 'sendora'),
            __('Sendora', 'sendora'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render_dashboard'],
            plugins_url('admin/assets/icon-sendora.svg', SENDORA_PLUGIN_FILE),
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
        // Menu icon must load on every admin screen (sidebar is global).
        wp_enqueue_style(
            'sendora-admin-menu',
            plugins_url('admin/css/admin-menu.css', SENDORA_PLUGIN_FILE),
            [],
            SENDORA_VERSION
        );

        if (!str_contains($hook_suffix, 'sendora')) {
            return;
        }

        wp_enqueue_style(
            'sendora-admin',
            plugins_url('admin/css/admin.css', SENDORA_PLUGIN_FILE),
            ['sendora-admin-menu'],
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
                'stages' => Sendora_Settings::load_stages_public(Sendora_Settings::get_settings()),
                'connections' => Sendora_Settings::load_connections_public(Sendora_Settings::get_settings()),
                'wabas' => Sendora_Settings::load_waba_public(Sendora_Settings::get_settings()),
                'templates' => Sendora_Settings::load_templates_public(Sendora_Settings::get_settings()),
                'metaTemplates' => Sendora_Settings::load_meta_templates_public(Sendora_Settings::get_settings()),
                'i18n' => [
                    'testing' => __('Testando…', 'sendora'),
                    'failed' => __('Falha no teste de conexão.', 'sendora'),
                    'done' => __('Teste de conexão concluído.', 'sendora'),
                    'disconnectConfirm' => __('Desconectar este site da Sendora? A chave de API será removida.', 'sendora'),
                    'disconnecting' => __('Desconectando…', 'sendora'),
                    'sendingEvent' => __('Enviando evento…', 'sendora'),
                    'sendingTest' => __('Enviando teste…', 'sendora'),
                    'testNeedPhone' => __('Informe o telefone de teste (pode ser o seu) ao lado do botão ou em Configurações.', 'sendora'),
                    'testNeedTemplate' => __('Selecione e salve uma mensagem antes de testar.', 'sendora'),
                    'selectStage' => __('Selecione um estágio', 'sendora'),
                    'defaultInstance' => __('Padrão da conta', 'sendora'),
                    'noneMessage' => __('Nenhuma (só CRM)', 'sendora'),
                    'selectMessage' => __('Selecione uma mensagem', 'sendora'),
                'instanceHelpEvolution' => __('Número conectado via QR Code. Deixe em branco para o padrão.', 'sendora'),
                'instanceHelpMeta' => __('Conta WABA da API Oficial. Deixe em branco para usar a mais recente.', 'sendora'),
                'channelHelpEvolution' => __('Modelos com variáveis dinâmicas ({{nome}}, {{pedido}}…) criados em app.sendora.com.br/templates.', 'sendora'),
                'channelHelpMeta' => __('Templates oficiais aprovados na Meta (WABA). Diferente dos modelos com {{variáveis}} do app.', 'sendora'),
                'loadingTemplates' => __('Carregando templates…', 'sendora'),
            ],
            'testPhone' => (string) (Sendora_Settings::get_settings()['test_phone'] ?? ''),
            'onboarding' => class_exists('Sendora_Onboarding')
                ? Sendora_Onboarding::state()
                : null,
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
            'templates' => Sendora_Settings::load_templates_public($settings),
            'meta_templates' => Sendora_Settings::load_meta_templates_public($settings),
            'connections' => Sendora_Settings::load_connections_public($settings),
            'wabas' => Sendora_Settings::load_waba_public($settings),
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
            'templates' => Sendora_Settings::load_templates_public($settings),
            'meta_templates' => Sendora_Settings::load_meta_templates_public($settings),
            'connections' => Sendora_Settings::load_connections_public($settings),
            'wabas' => Sendora_Settings::load_waba_public($settings),
            'funnels' => Sendora_Settings::load_funnels_public($settings),
            'stages' => Sendora_Settings::load_stages_public($settings),
            'woo_active' => defined('WC_VERSION') || class_exists('WooCommerce'),
        ]);
    }

    public function render_automations(): void
    {
        $settings = Sendora_Settings::get_settings();
        $this->render_page('sendora-automations', 'pages/automations.php', [
            'settings' => $settings,
            'templates' => Sendora_Settings::load_templates_public($settings),
            'meta_templates' => Sendora_Settings::load_meta_templates_public($settings),
            'connections' => Sendora_Settings::load_connections_public($settings),
            'wabas' => Sendora_Settings::load_waba_public($settings),
            'flows' => Sendora_Settings::load_flows_public($settings),
            'funnels' => Sendora_Settings::load_funnels_public($settings),
            'stages' => Sendora_Settings::load_stages_public($settings),
        ]);
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

    public static function ajax_test_send(): void
    {
        check_ajax_referer('sendora_admin', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json(['ok' => false, 'message' => __('Sem permissão.', 'sendora')], 403);
        }

        $settings = Sendora_Settings::get_settings();
        $default_cc = (string) ($settings['default_cc'] ?? '55');

        // Prefer phone typed next to the button; fall back to Configurações.
        $posted_phone = sanitize_text_field(wp_unslash((string) ($_POST['test_phone'] ?? '')));
        $test_phone = Sendora_Phone::normalize(
            $posted_phone !== '' ? $posted_phone : (string) ($settings['test_phone'] ?? ''),
            $default_cc
        );
        if ($test_phone === '') {
            wp_send_json([
                'ok' => false,
                'message' => __('Informe o telefone de teste (pode ser o seu) ao lado do botão ou em Configurações.', 'sendora'),
            ]);
        }

        // Remember last used test phone for convenience.
        if ($posted_phone !== '' && $test_phone !== (string) ($settings['test_phone'] ?? '')) {
            $settings['test_phone'] = $test_phone;
            update_option(Sendora_Settings::OPTION_KEY, $settings, false);
        }

        $source = sanitize_key(wp_unslash((string) ($_POST['source'] ?? '')));
        $template_id = '';
        $provider = Sendora_Outbound::PROVIDER_EVOLUTION;
        $instance_id = '';
        $template_language = 'pt_BR';
        $body_vars = [];

        if ($source === 'woo') {
            $event = sanitize_key(wp_unslash((string) ($_POST['event'] ?? '')));
            $cfg = class_exists('Sendora_WooCommerce')
                ? Sendora_WooCommerce::event_config($settings, $event)
                : [];
            $template_id = (string) ($cfg['template_id'] ?? '');
            $provider = (string) ($cfg['channel'] ?? $provider);
            $instance_id = (string) ($cfg['instance_id'] ?? '');
            $template_language = (string) ($cfg['template_language'] ?? 'pt_BR');
            $body_vars = is_array($cfg['body_vars'] ?? null) ? $cfg['body_vars'] : [];
        } elseif ($source === 'native_form') {
            $native = is_array($settings['native_form'] ?? null) ? $settings['native_form'] : [];
            $template_id = (string) ($native['template_id'] ?? '');
            $provider = (string) ($native['channel'] ?? $provider);
            $instance_id = (string) ($native['instance_id'] ?? '');
            $template_language = (string) ($native['template_language'] ?? 'pt_BR');
            $body_vars = is_array($native['body_vars'] ?? null) ? $native['body_vars'] : [];
        } elseif ($source === 'cf7') {
            $form_id = (string) preg_replace('/\D+/', '', wp_unslash((string) ($_POST['form_id'] ?? '')));
            $map = is_array($settings['cf7_mappings'][$form_id] ?? null)
                ? $settings['cf7_mappings'][$form_id]
                : [];
            $template_id = (string) ($map['template_id'] ?? '');
            $provider = (string) ($map['channel'] ?? $provider);
            $instance_id = (string) ($map['instance_id'] ?? '');
            $template_language = (string) ($map['template_language'] ?? 'pt_BR');
            $body_vars = is_array($map['body_vars'] ?? null) ? $map['body_vars'] : [];
        }

        // Allow unsaved selects from the current form UI.
        $posted_template = sanitize_text_field(wp_unslash((string) ($_POST['template_id'] ?? '')));
        if ($posted_template !== '' && preg_match('/^[A-Za-z0-9_.-]+$/', $posted_template)) {
            $template_id = $posted_template;
        }
        $posted_provider = sanitize_key(wp_unslash((string) ($_POST['provider'] ?? '')));
        if ($posted_provider !== '') {
            $provider = Sendora_Outbound::normalize_provider($posted_provider);
        }
        $posted_instance = sanitize_text_field(wp_unslash((string) ($_POST['instance_id'] ?? '')));
        if ($posted_instance !== '' && preg_match('/^[A-Za-z0-9_.-]+$/', $posted_instance)) {
            $instance_id = $posted_instance;
        }
        $posted_language = sanitize_text_field(wp_unslash((string) ($_POST['template_language'] ?? '')));
        if ($posted_language !== '' && preg_match('/^[A-Za-z]{2}(_[A-Za-z]{2})?$/', $posted_language)) {
            $template_language = $posted_language;
        }
        if (isset($_POST['body_vars'])) {
            $body_vars = Sendora_Outbound::normalize_body_vars(
                sanitize_text_field(wp_unslash((string) $_POST['body_vars']))
            );
        }

        if ($template_id === '') {
            wp_send_json([
                'ok' => false,
                'message' => __('Selecione e salve uma mensagem antes de testar.', 'sendora'),
            ]);
        }

        $vars = Sendora_Outbound::sample_order_vars();
        $vars['phone'] = $test_phone;
        $result = Sendora_Outbound::upsert_and_send_template(
            [
                'name' => (string) ($vars['name'] ?? 'Cliente Teste'),
                'phone' => $test_phone,
            ],
            $template_id,
            $vars,
            [
                'provider' => $provider,
                'instance_id' => $instance_id,
                'template_language' => $template_language,
                'body_vars' => $body_vars,
            ]
        );

        if (empty($result['ok'])) {
            wp_send_json([
                'ok' => false,
                'message' => wp_strip_all_tags((string) ($result['error'] ?? __('Falha no envio de teste.', 'sendora'))),
            ]);
        }

        Sendora_Logger::log('admin', 'info', sprintf(
            'Teste → Mensagem: "%s" → WhatsApp: %s → Sendora: enviado',
            (string) ($result['template_name'] ?? ''),
            '+' . substr($test_phone, 0, 2) . '…' . substr($test_phone, -4)
        ), [
            'source' => $source,
            'template_id' => $template_id,
            'provider' => $provider,
            'instance_id' => $instance_id,
        ]);

        if (class_exists('Sendora_Onboarding')) {
            Sendora_Onboarding::mark_test_sent();
        }

        wp_send_json([
            'ok' => true,
            'message' => sprintf(
                /* translators: %s: template name */
                __('Teste enviado: %s', 'sendora'),
                (string) ($result['template_name'] ?? $template_id)
            ),
        ]);
    }

    public static function ajax_onboarding(): void
    {
        check_ajax_referer('sendora_admin', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json(['ok' => false, 'message' => __('Sem permissão.', 'sendora')], 403);
        }
        if (!class_exists('Sendora_Onboarding')) {
            wp_send_json(['ok' => false, 'message' => __('Onboarding indisponível.', 'sendora')], 500);
        }

        $action = sanitize_key((string) wp_unslash($_POST['onboard_action'] ?? ''));
        $wizard_step = (int) sanitize_text_field((string) wp_unslash($_POST['wizard_step'] ?? '1'));
        $state = match ($action) {
            'channels_seen' => (static function () use ($wizard_step): array {
                Sendora_Onboarding::mark_channels_seen();
                $step = max(1, min(4, $wizard_step > 0 ? $wizard_step : 3));

                return Sendora_Onboarding::update(['wizard_step' => $step]);
            })(),
            'set_step' => Sendora_Onboarding::update([
                'wizard_step' => max(1, min(4, $wizard_step > 0 ? $wizard_step : 1)),
            ]),
            'complete' => (static function (): array {
                Sendora_Onboarding::complete();

                return Sendora_Onboarding::state();
            })(),
            'dismiss' => (static function (): array {
                Sendora_Onboarding::dismiss();

                return Sendora_Onboarding::state();
            })(),
            'reopen' => (static function (): array {
                Sendora_Onboarding::reopen();

                return Sendora_Onboarding::state();
            })(),
            default => null,
        };

        if ($state === null) {
            wp_send_json(['ok' => false, 'message' => __('Ação inválida.', 'sendora')]);
        }

        wp_send_json([
            'ok' => true,
            'onboarding' => $state,
            'reload' => in_array($action, ['complete', 'dismiss', 'reopen'], true),
        ]);
    }

    public static function ajax_list_meta_templates(): void
    {
        check_ajax_referer('sendora_admin', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json(['ok' => false, 'message' => __('Sem permissão.', 'sendora')], 403);
        }

        $api_settings_id = sanitize_text_field(wp_unslash((string) ($_POST['instance_id'] ?? '')));
        if ($api_settings_id !== '' && !preg_match('/^[A-Za-z0-9_-]+$/', $api_settings_id)) {
            $api_settings_id = '';
        }

        $settings = Sendora_Settings::get_settings();
        $templates = Sendora_Settings::load_meta_templates_public(
            $settings,
            $api_settings_id !== '' ? $api_settings_id : null
        );

        wp_send_json([
            'ok' => true,
            'templates' => $templates,
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
     */
    private static function woo_has_enabled_event(array $settings): bool
    {
        $events = is_array($settings['woo_events'] ?? null) ? $settings['woo_events'] : [];
        foreach ($events as $key => $row) {
            if (!is_array($row) || empty($row['enabled'])) {
                continue;
            }
            $cfg = class_exists('Sendora_WooCommerce')
                ? Sendora_WooCommerce::event_config($settings, (string) $key)
                : $row;
            if (class_exists('Sendora_WooCommerce') && Sendora_WooCommerce::config_has_action($cfg)) {
                return true;
            }
            if (!empty($row['template_id'])) {
                return true;
            }
        }

        return false;
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
                    && class_exists('Sendora_WooCommerce')
                    && self::woo_has_enabled_event($settings),
            ],
            [
                'label' => __('Formulário nativo', 'sendora'),
                'active' => true,
            ],
        ];
    }
}
