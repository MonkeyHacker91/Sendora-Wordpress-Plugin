<?php
/**
 * Plugin-only onboarding: wizard + dashboard checklist.
 *
 * @package Sendora
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Sendora_Onboarding
{
    public const TEMPLATES_APP_URL = 'https://app.sendora.com.br/templates';
    public const META_TEMPLATES_APP_URL = 'https://app.sendora.com.br/campaign-templates';
    public const FINISHED_OPTION = 'sendora_onboarding_finished';

    /**
     * @return array{completed: bool, dismissed: bool, channels_seen: bool, test_sent: bool, wizard_step: int}
     */
    public static function defaults(): array
    {
        return [
            'completed' => false,
            'dismissed' => false,
            'channels_seen' => false,
            'test_sent' => false,
            'wizard_step' => 1,
        ];
    }

    public static function is_finished(): bool
    {
        return get_option(self::FINISHED_OPTION, '') === '1';
    }

    /**
     * Admin GET ?sendora_wizard=1 with a valid nonce forces the wizard UI.
     */
    public static function request_forces_wizard(): bool
    {
        if (!isset($_GET['sendora_wizard'])) {
            return false;
        }

        $flag = sanitize_text_field(wp_unslash((string) $_GET['sendora_wizard']));
        if ($flag !== '1') {
            return false;
        }

        $nonce = isset($_GET['_wpnonce'])
            ? sanitize_text_field(wp_unslash((string) $_GET['_wpnonce']))
            : '';

        return $nonce !== '' && (bool) wp_verify_nonce($nonce, 'sendora_wizard');
    }

    /**
     * Optional step override from a nonced wizard deep-link.
     */
    public static function request_wizard_step(): ?int
    {
        if (!self::request_forces_wizard() || !isset($_GET['step'])) {
            return null;
        }

        return max(1, min(4, (int) sanitize_text_field(wp_unslash((string) $_GET['step']))));
    }

    public static function wizard_deep_link(int $step = 1): string
    {
        $step = max(1, min(4, $step));
        $url = add_query_arg(
            [
                'page' => 'sendora',
                'sendora_wizard' => '1',
                'step' => (string) $step,
            ],
            admin_url('admin.php')
        );

        return wp_nonce_url($url, 'sendora_wizard');
    }

    /**
     * @param mixed $input
     * @return array{completed: bool, dismissed: bool, channels_seen: bool, test_sent: bool, wizard_step: int}
     */
    public static function sanitize(mixed $input): array
    {
        $base = self::defaults();
        if (!is_array($input)) {
            return $base;
        }

        $base['completed'] = self::truthy($input['completed'] ?? false);
        $base['dismissed'] = self::truthy($input['dismissed'] ?? false);
        $base['channels_seen'] = self::truthy($input['channels_seen'] ?? false);
        $base['test_sent'] = self::truthy($input['test_sent'] ?? false);
        $base['wizard_step'] = max(1, min(4, (int) ($input['wizard_step'] ?? 1)));

        return $base;
    }

    private static function truthy(mixed $value): bool
    {
        return $value === true
            || $value === 1
            || $value === '1'
            || $value === 'on'
            || $value === 'true';
    }

    /**
     * @return array{completed: bool, dismissed: bool, channels_seen: bool, test_sent: bool, wizard_step: int}
     */
    public static function state(?array $settings = null): array
    {
        $settings ??= Sendora_Settings::get_settings();
        $state = self::sanitize($settings['onboarding'] ?? []);
        if (self::is_finished()) {
            $state['completed'] = true;
            $state['dismissed'] = true;
        }

        return $state;
    }

    /**
     * Patch onboarding in the raw option (avoids race with set_step wiping finish).
     *
     * @param array{completed?: bool, dismissed?: bool, channels_seen?: bool, test_sent?: bool, wizard_step?: int} $patch
     * @return array{completed: bool, dismissed: bool, channels_seen: bool, test_sent: bool, wizard_step: int}
     */
    public static function update(array $patch): array
    {
        $raw = get_option(Sendora_Settings::OPTION_KEY, []);
        $raw = is_array($raw) ? $raw : [];
        $current = self::sanitize($raw['onboarding'] ?? []);

        // After finish, never allow set_step / channels_seen to reopen the wizard.
        if (self::is_finished() || $current['completed'] || $current['dismissed']) {
            $patch['completed'] = true;
            $patch['dismissed'] = true;
        }

        $merged = self::sanitize(array_merge($current, $patch));
        $raw['onboarding'] = $merged;
        update_option(Sendora_Settings::OPTION_KEY, $raw, false);

        return self::state($raw);
    }

    public static function is_wizard_due(?array $settings = null): bool
    {
        if (self::is_finished()) {
            return false;
        }
        $state = self::state($settings);

        return !$state['completed'] && !$state['dismissed'];
    }

    public static function show_checklist(?array $settings = null): bool
    {
        if (self::is_finished()) {
            return false;
        }
        $state = self::state($settings);
        if ($state['dismissed'] || $state['completed']) {
            return false;
        }

        foreach (self::steps($settings) as $step) {
            if (empty($step['done'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array{id: string, label: string, done: bool, url: string, cta: string}>
     */
    public static function steps(?array $settings = null): array
    {
        $settings ??= Sendora_Settings::get_settings();
        $state = self::state($settings);
        $connected = class_exists('Sendora_Connection')
            && !empty(Sendora_Connection::get_status()['connected']);

        $has_message = self::has_any_message($settings);
        $has_integration = self::has_active_integration($settings);

        return [
            [
                'id' => 'api',
                'label' => __('Conectar a API Sendora', 'sendora'),
                'done' => $connected,
                'url' => admin_url('admin.php?page=sendora-connection'),
                'cta' => __('Abrir Conexão', 'sendora'),
            ],
            [
                'id' => 'channels',
                'label' => __('Entender QR (modelos) vs Meta (templates oficiais)', 'sendora'),
                'done' => $state['channels_seen'],
                'url' => self::wizard_deep_link(2),
                'cta' => __('Ver explicação', 'sendora'),
            ],
            [
                'id' => 'message',
                'label' => __('Ter um modelo QR ou template Meta', 'sendora'),
                'done' => $has_message,
                'url' => self::TEMPLATES_APP_URL,
                'cta' => __('Abrir modelos no app', 'sendora'),
            ],
            [
                'id' => 'integration',
                'label' => __('Ativar WooCommerce ou Formulários', 'sendora'),
                'done' => $has_integration,
                'url' => admin_url('admin.php?page=sendora-woocommerce'),
                'cta' => __('Configurar', 'sendora'),
            ],
            [
                'id' => 'test',
                'label' => __('Enviar uma mensagem de teste', 'sendora'),
                'done' => $state['test_sent'],
                'url' => admin_url('admin.php?page=sendora-woocommerce'),
                'cta' => __('Ir para teste', 'sendora'),
            ],
        ];
    }

    public static function has_any_message(?array $settings = null): bool
    {
        $settings ??= Sendora_Settings::get_settings();
        if (($settings['api_key'] ?? '') === '') {
            return false;
        }

        $qr = Sendora_Settings::load_templates_public($settings);
        if ($qr !== []) {
            return true;
        }

        $meta = Sendora_Settings::load_meta_templates_public($settings);

        return $meta !== [];
    }

    public static function has_active_integration(?array $settings = null): bool
    {
        $settings ??= Sendora_Settings::get_settings();

        $native = is_array($settings['native_form'] ?? null) ? $settings['native_form'] : [];
        if (!empty($native['enabled']) && (string) ($native['template_id'] ?? '') !== '') {
            return true;
        }

        $cf7 = is_array($settings['cf7_mappings'] ?? null) ? $settings['cf7_mappings'] : [];
        foreach ($cf7 as $map) {
            if (!is_array($map) || empty($map['enabled'])) {
                continue;
            }
            if ((string) ($map['template_id'] ?? '') !== '' || (string) ($map['flow_id'] ?? '') !== '') {
                return true;
            }
        }

        if (!class_exists('Sendora_WooCommerce')) {
            return false;
        }

        $events = is_array($settings['woo_events'] ?? null) ? $settings['woo_events'] : [];
        foreach ($events as $cfg) {
            if (!is_array($cfg) || empty($cfg['enabled'])) {
                continue;
            }
            if (Sendora_WooCommerce::config_has_action($cfg)) {
                return true;
            }
        }

        return false;
    }

    public static function mark_test_sent(): void
    {
        self::update(['test_sent' => true]);
    }

    public static function mark_channels_seen(): void
    {
        self::update(['channels_seen' => true]);
    }

    public static function complete(): void
    {
        self::finish_forever(true);
    }

    public static function dismiss(): void
    {
        self::finish_forever(false);
    }

    /**
     * Close onboarding permanently (wizard + checklist never auto-show again).
     */
    public static function finish_forever(bool $mark_channels_seen = true): void
    {
        update_option(self::FINISHED_OPTION, '1', false);

        $patch = [
            'completed' => true,
            'dismissed' => true,
        ];
        if ($mark_channels_seen) {
            $patch['channels_seen'] = true;
        }
        self::update($patch);
    }

    public static function reopen(): void
    {
        delete_option(self::FINISHED_OPTION);
        self::update([
            'dismissed' => false,
            'completed' => false,
            'wizard_step' => 1,
        ]);
    }
}
