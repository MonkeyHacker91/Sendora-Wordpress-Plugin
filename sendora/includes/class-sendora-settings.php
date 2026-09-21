<?php
/**
 * Admin settings, sanitization, and connection test.
 *
 * @package Sendora
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Sendora_Settings
{
    public const OPTION_KEY = 'sendora_settings';

    public const DEFAULT_API_BASE = 'https://api.sendora.com.br';

    private const PAGE_SLUG = 'sendora';
    private const LOGS_PAGE_SLUG = 'sendora-logs';
    private const SETTINGS_GROUP = 'sendora_settings_group';

    public function run(): void
    {
        add_action('admin_init', [$this, 'register_settings']);
        add_action('update_option_' . self::OPTION_KEY, [self::class, 'force_option_no_autoload'], 10, 0);
        add_action('add_option_' . self::OPTION_KEY, [self::class, 'force_option_no_autoload'], 10, 0);
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
                'show_in_rest' => false,
            ]
        );
    }

    /**
     * @param mixed $input Untrusted Settings API input.
     * @return array<string, mixed>
     */
    public static function sanitize(mixed $input): array
    {
        $input = is_array($input) ? $input : [];
        $saved = self::get_settings();
        $settings = self::defaults();

        // API base is fixed to the official Sendora endpoint (not user-configurable).
        $settings['api_base'] = self::DEFAULT_API_BASE;

        $api_key = trim(sanitize_text_field((string) ($input['api_key'] ?? '')));
        if ($api_key === '') {
            $settings['api_key'] = $saved['api_key'];
        } elseif (!str_starts_with($api_key, 'sk_')) {
            add_settings_error(
                self::OPTION_KEY,
                'sendora_api_key_prefix',
                __('A chave de API da Sendora deve começar com sk_.', 'sendora')
            );
            $settings['api_key'] = $saved['api_key'];
        } else {
            $settings['api_key'] = $api_key;
        }

        // Partial save from Conexão page — only touch the API key.
        if (($input['_partial'] ?? '') === 'connection') {
            $saved['api_key'] = $settings['api_key'];
            $saved['api_base'] = self::DEFAULT_API_BASE;

            return $saved;
        }

        if (($input['_partial'] ?? '') === 'forms') {
            $country = Sendora_Phone::sanitize_country_settings($input, $saved);
            $saved['phone_country'] = $country['phone_country'];
            $saved['default_cc'] = $country['default_cc'];
            $saved['native_form'] = self::sanitize_native_form($input['native_form'] ?? []);
            $saved['cf7_mappings'] = array_key_exists('cf7_mappings', $input)
                ? self::sanitize_cf7_mappings($input['cf7_mappings'])
                : (is_array($saved['cf7_mappings'] ?? null) ? $saved['cf7_mappings'] : []);
            // Keep legacy flow id if posted (optional advanced).
            if (array_key_exists('default_flow_id', $input)) {
                $value = sanitize_text_field((string) $input['default_flow_id']);
                $saved['default_flow_id'] = preg_match('/^[A-Za-z0-9_-]*$/', $value) ? $value : '';
            }
            $saved['api_base'] = self::DEFAULT_API_BASE;

            return $saved;
        }

        if (($input['_partial'] ?? '') === 'widget') {
            $saved['widget_id'] = class_exists('Sendora_Widget')
                ? Sendora_Widget::sanitize_widget_id((string) ($input['widget_id'] ?? ''))
                : sanitize_text_field((string) ($input['widget_id'] ?? ''));
            $display = sanitize_text_field((string) ($input['widget_display'] ?? 'all'));
            $saved['widget_display'] = in_array($display, ['all', 'specific'], true) ? $display : 'all';
            $saved['widget_page_ids'] = self::sanitize_page_ids($input['widget_page_ids'] ?? []);
            $saved['widget_enabled'] = isset($input['widget_enabled']) && (string) $input['widget_enabled'] === '1';
            $saved['api_base'] = self::DEFAULT_API_BASE;

            return $saved;
        }

        if (($input['_partial'] ?? '') === 'woo') {
            $saved['woo_events'] = self::sanitize_woo_events($input['woo_events'] ?? []);
            $saved['api_base'] = self::DEFAULT_API_BASE;

            return $saved;
        }

        if (($input['_partial'] ?? '') === 'general') {
            $country = Sendora_Phone::sanitize_country_settings($input, $saved);
            $saved['phone_country'] = $country['phone_country'];
            $saved['default_cc'] = $country['default_cc'];
            $test_phone = preg_replace(
                '/\D+/',
                '',
                sanitize_text_field((string) ($input['test_phone'] ?? ''))
            ) ?? '';
            $saved['test_phone'] = $test_phone;
            $saved['api_base'] = self::DEFAULT_API_BASE;

            return $saved;
        }

        if (($input['_partial'] ?? '') === 'automations') {
            $saved['automations'] = class_exists('Sendora_Automations')
                ? Sendora_Automations::sanitize_rules($input['automations'] ?? [])
                : [];
            $saved['api_base'] = self::DEFAULT_API_BASE;

            return $saved;
        }

        if (($input['_partial'] ?? '') === 'onboarding') {
            $saved['onboarding'] = class_exists('Sendora_Onboarding')
                ? Sendora_Onboarding::sanitize($input['onboarding'] ?? [])
                : ($saved['onboarding'] ?? []);
            $saved['api_base'] = self::DEFAULT_API_BASE;

            return $saved;
        }

        $settings['widget_id'] = class_exists('Sendora_Widget')
            ? Sendora_Widget::sanitize_widget_id((string) ($input['widget_id'] ?? ''))
            : sanitize_text_field((string) ($input['widget_id'] ?? ''));

        $display = sanitize_text_field((string) ($input['widget_display'] ?? 'all'));
        $settings['widget_display'] = in_array($display, ['all', 'specific'], true) ? $display : 'all';
        $settings['widget_page_ids'] = self::sanitize_page_ids($input['widget_page_ids'] ?? []);

        foreach (['default_flow_id'] as $field) {
            $value = sanitize_text_field((string) ($input[$field] ?? ''));
            $settings[$field] = preg_match('/^[A-Za-z0-9_-]*$/', $value) ? $value : '';
        }

        $country = Sendora_Phone::sanitize_country_settings($input, $saved);
        $settings['phone_country'] = $country['phone_country'];
        $settings['default_cc'] = $country['default_cc'];

        foreach (['widget_enabled'] as $field) {
            $settings[$field] = isset($input[$field]) && (string) $input[$field] === '1';
        }

        if (array_key_exists('woo_events', $input)) {
            $settings['woo_events'] = self::sanitize_woo_events($input['woo_events']);
        } else {
            $settings['woo_events'] = self::sanitize_woo_events($saved['woo_events'] ?? self::default_woo_events());
        }

        // Legacy keys kept for older installs / tests (no longer drive runtime).
        foreach (['woo_on_created', 'woo_on_paid', 'woo_on_cancelled'] as $field) {
            $settings[$field] = isset($input[$field]) && (string) $input[$field] === '1';
        }
        $paid_mode = sanitize_text_field((string) ($input['woo_paid_mode'] ?? 'off'));
        $settings['woo_paid_mode'] = in_array($paid_mode, ['flow', 'message', 'off'], true)
            ? $paid_mode
            : 'off';
        $created_mode = sanitize_text_field((string) ($input['woo_created_mode'] ?? 'off'));
        $settings['woo_created_mode'] = in_array(
            $created_mode,
            ['contact_only', 'contact_and_flow', 'off'],
            true
        ) ? $created_mode : 'off';
        $settings['woo_paid_flow_id'] = preg_match(
            '/^[A-Za-z0-9_-]*$/',
            sanitize_text_field((string) ($input['woo_paid_flow_id'] ?? ''))
        ) ? sanitize_text_field((string) ($input['woo_paid_flow_id'] ?? '')) : '';

        $settings['cf7_mappings'] = array_key_exists('cf7_mappings', $input)
            ? self::sanitize_cf7_mappings($input['cf7_mappings'])
            : (is_array($saved['cf7_mappings'] ?? null) ? $saved['cf7_mappings'] : []);

        return $settings;
    }

    /**
     * @return array<string, mixed>
     */
    public static function get_settings(): array
    {
        $saved = get_option(self::OPTION_KEY, []);
        $saved = is_array($saved) ? $saved : [];
        if (!array_key_exists('woo_created_mode', $saved) && !empty($saved['woo_on_created'])) {
            $saved['woo_created_mode'] = 'contact_only';
        }

        $merged = array_merge(self::defaults(), $saved);
        $merged['api_base'] = self::DEFAULT_API_BASE;

        if ((string) ($merged['phone_country'] ?? '') === '') {
            $merged['phone_country'] = Sendora_Phone::country_from_cc(
                (string) ($merged['default_cc'] ?? '55')
            );
        }
        $iso = strtoupper((string) $merged['phone_country']);
        if (!array_key_exists($iso, Sendora_Phone::countries())) {
            $iso = 'BR';
        }
        $merged['phone_country'] = $iso;
        $merged['default_cc'] = Sendora_Phone::country($iso)['cc'];

        if (!isset($saved['woo_events']) || !is_array($saved['woo_events'])) {
            $merged['woo_events'] = self::migrate_legacy_woo_events($merged);
        } else {
            $merged['woo_events'] = self::sanitize_woo_events($saved['woo_events']);
        }

        if (class_exists('Sendora_Automations')) {
            $merged['automations'] = Sendora_Automations::sanitize_rules($saved['automations'] ?? []);
        } else {
            $merged['automations'] = [];
        }

        if (class_exists('Sendora_Onboarding')) {
            $merged['onboarding'] = Sendora_Onboarding::sanitize($saved['onboarding'] ?? []);
        }

        return $merged;
    }

    public static function mask_api_key(string $api_key): string
    {
        if ($api_key === '') {
            return '';
        }

        return '••••••••' . substr($api_key, -4);
    }

    /**
     * Keep the API key out of the autoloaded options cache.
     */
    public static function force_option_no_autoload(): void
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- intentional autoload flip for secret storage.
        $wpdb->update(
            $wpdb->options,
            ['autoload' => 'no'],
            ['option_name' => self::OPTION_KEY],
            ['%s'],
            ['%s']
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaults(): array
    {
        return [
            'api_base' => self::DEFAULT_API_BASE,
            'api_key' => '',
            'widget_enabled' => false,
            'widget_id' => '',
            'widget_display' => 'all',
            'widget_page_ids' => [],
            'default_flow_id' => '',
            'default_cc' => '55',
            'phone_country' => 'BR',
            'test_phone' => '',
            'native_form' => [
                'enabled' => false,
                'channel' => Sendora_Outbound::PROVIDER_EVOLUTION,
                'instance_id' => '',
                'template_id' => '',
                'template_language' => 'pt_BR',
                'body_vars' => [],
            ],
            'woo_events' => self::default_woo_events(),
            'automations' => [],
            'onboarding' => class_exists('Sendora_Onboarding')
                ? Sendora_Onboarding::defaults()
                : [
                    'completed' => false,
                    'dismissed' => false,
                    'channels_seen' => false,
                    'test_sent' => false,
                    'wizard_step' => 1,
                ],
            'woo_on_created' => false,
            'woo_created_mode' => 'off',
            'woo_on_paid' => false,
            'woo_on_cancelled' => false,
            'woo_paid_flow_id' => '',
            'woo_paid_mode' => 'off',
            'cf7_mappings' => [],
        ];
    }

    /**
     * @return array<string, array{enabled: bool, channel: string, template_id: string}>
     */
    public static function default_woo_events(): array
    {
        $events = [];
        $keys = class_exists('Sendora_WooCommerce')
            ? array_keys(Sendora_WooCommerce::event_definitions())
            : [
                'order_created',
                'cart_abandoned',
                'payment_approved',
                'order_processing',
                'order_completed',
                'order_cancelled',
                'order_refunded',
                'order_shipped',
                'order_delivered',
            ];

        foreach ($keys as $key) {
            $events[$key] = [
                'enabled' => false,
                'channel' => Sendora_Outbound::PROVIDER_EVOLUTION,
                'instance_id' => '',
                'template_id' => '',
                'template_language' => 'pt_BR',
                'body_vars' => [],
                'delay_minutes' => 60,
                'funnel_id' => '',
                'stage' => '',
                'tags' => [],
            ];
        }

        return $events;
    }

    /**
     * @param mixed $input
     * @return array<string, array{enabled: bool, channel: string, instance_id: string, template_id: string, template_language: string, body_vars: array<int, string>, delay_minutes: int, funnel_id: string, stage: string, tags: array<int, string>}>
     */
    public static function sanitize_woo_events(mixed $input): array
    {
        $defaults = self::default_woo_events();
        if (!is_array($input)) {
            return $defaults;
        }

        foreach ($defaults as $key => $default_row) {
            $row = is_array($input[$key] ?? null) ? $input[$key] : [];
            $enabled = !empty($row['enabled']) && (
                $row['enabled'] === true
                || $row['enabled'] === 1
                || $row['enabled'] === '1'
                || $row['enabled'] === 'on'
            );

            $defaults[$key] = array_merge(
                $default_row,
                self::sanitize_message_channel_fields($row),
                [
                    'enabled' => $enabled,
                    'delay_minutes' => max(
                        5,
                        min(10080, (int) ($row['delay_minutes'] ?? 60))
                    ),
                    'funnel_id' => self::sanitize_id_field((string) ($row['funnel_id'] ?? '')),
                    'stage' => self::sanitize_id_field((string) ($row['stage'] ?? '')),
                    'tags' => self::sanitize_tag_list($row['tags'] ?? []),
                ]
            );
        }

        return $defaults;
    }

    /**
     * Shared channel / instance / template fields for Woo + forms.
     *
     * @param array<string, mixed> $row
     * @return array{channel: string, instance_id: string, template_id: string, template_language: string, body_vars: array<int, string>}
     */
    public static function sanitize_message_channel_fields(array $row): array
    {
        $channel = Sendora_Outbound::normalize_provider((string) ($row['channel'] ?? Sendora_Outbound::PROVIDER_EVOLUTION));
        $instance_id = self::sanitize_id_field((string) ($row['instance_id'] ?? ''));
        $template_id = self::sanitize_id_field((string) ($row['template_id'] ?? ''));
        $language = sanitize_text_field((string) ($row['template_language'] ?? 'pt_BR'));
        if ($language === '' || !preg_match('/^[A-Za-z]{2}(_[A-Za-z]{2})?$/', $language)) {
            $language = 'pt_BR';
        }

        return [
            'channel' => $channel,
            'instance_id' => $instance_id,
            'template_id' => $template_id,
            'template_language' => $language,
            'body_vars' => Sendora_Outbound::normalize_body_vars($row['body_vars'] ?? []),
        ];
    }

    public static function sanitize_id_field(string $value): string
    {
        $value = sanitize_text_field($value);
        if ($value !== '' && !preg_match('/^[A-Za-z0-9_.-]+$/', $value)) {
            return '';
        }

        return $value;
    }

    /**
     * @return array<int, string>
     */
    public static function sanitize_tag_list(mixed $raw): array
    {
        if (is_string($raw)) {
            $parts = preg_split('/[,;]+/', $raw) ?: [];
        } elseif (is_array($raw)) {
            $parts = $raw;
        } else {
            return [];
        }

        $tags = [];
        foreach ($parts as $part) {
            $tag = sanitize_text_field(trim((string) $part));
            if ($tag === '' || strlen($tag) > 64) {
                continue;
            }
            $tags[] = $tag;
        }

        return array_values(array_unique($tags));
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, array{enabled: bool, channel: string, template_id: string}>
     */
    private static function migrate_legacy_woo_events(array $settings): array
    {
        $events = self::default_woo_events();

        $created = (string) ($settings['woo_created_mode'] ?? 'off');
        if ($created === 'contact_and_flow' || !empty($settings['woo_on_created'])) {
            // Message-centric: enable only when they previously intended an action beyond silent sync.
            if ($created === 'contact_and_flow') {
                $events['order_created']['enabled'] = true;
            }
        }

        if (!empty($settings['woo_on_paid']) && ($settings['woo_paid_mode'] ?? 'off') !== 'off') {
            $events['payment_approved']['enabled'] = true;
        }

        if (!empty($settings['woo_on_cancelled'])) {
            $events['order_cancelled']['enabled'] = true;
        }

        return $events;
    }

    /**
     * @return array<int, int>
     */
    private static function sanitize_page_ids(mixed $input): array
    {
        if (!is_array($input)) {
            return [];
        }

        $ids = [];
        foreach ($input as $value) {
            $id = absint($value);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        $ids = array_values(array_unique($ids));
        sort($ids);

        return $ids;
    }

    /**
     * @return array<int, array{id: int, title: string}>
     */
    public static function list_pages_for_widget(): array
    {
        $pages = get_pages(
            [
                'post_status' => 'publish',
                'sort_column' => 'post_title',
                'sort_order' => 'ASC',
                'number' => 200,
            ]
        );

        if (!is_array($pages)) {
            return [];
        }

        $items = [];
        foreach ($pages as $page) {
            if (!is_object($page) || empty($page->ID)) {
                continue;
            }
            $items[] = [
                'id' => (int) $page->ID,
                'title' => self::page_label_for_widget($page),
            ];
        }

        return $items;
    }

    /**
     * Human-readable page label for selects (never bare "#123" when a name exists).
     */
    public static function page_label_for_widget(object $page): string
    {
        $id = (int) ($page->ID ?? 0);
        $title = trim(sanitize_text_field((string) ($page->post_title ?? '')));

        if ($title === '' && function_exists('get_the_title') && $id > 0) {
            $title = trim(sanitize_text_field((string) get_the_title($page)));
        }

        if ($title === '') {
            $slug = trim((string) ($page->post_name ?? ''));
            $slug = sanitize_title($slug);
            // Ignore auto slugs like "3487" / "3487-2" — they are not real names.
            if ($slug !== '' && !preg_match('/^\d+(-\d+)?$/', $slug)) {
                $title = str_replace('-', ' ', $slug);
            }
        }

        if ($title === '') {
            $title = __('(sem título)', 'sendora');
        }

        if ($id > 0) {
            return sprintf('%s (#%d)', $title, $id);
        }

        return $title;
    }

    /**
     * @param mixed $input
     * @return array{enabled: bool, channel: string, instance_id: string, template_id: string, template_language: string, body_vars: array<int, string>}
     */
    public static function sanitize_native_form(mixed $input): array
    {
        $row = is_array($input) ? $input : [];
        $enabled = !empty($row['enabled']) && (
            $row['enabled'] === true
            || $row['enabled'] === 1
            || $row['enabled'] === '1'
            || $row['enabled'] === 'on'
        );

        return array_merge(
            self::sanitize_message_channel_fields($row),
            ['enabled' => $enabled]
        );
    }

    /**
     * @return array<string, array{form_id: string, name: string, phone: string, email: string, enabled: bool, channel: string, instance_id: string, template_id: string, template_language: string, body_vars: array<int, string>}>
     */
    private static function sanitize_cf7_mappings(mixed $input): array
    {
        if (!is_array($input)) {
            return [];
        }

        $mappings = [];

        foreach ($input as $row) {
            if (!is_array($row)) {
                continue;
            }

            $form_id = trim((string) ($row['form_id'] ?? ''));
            $phone = sanitize_key((string) ($row['phone'] ?? ''));
            if (!ctype_digit($form_id) || (int) $form_id < 1 || $phone === '') {
                continue;
            }

            $enabled = !empty($row['enabled']) && (
                $row['enabled'] === true
                || $row['enabled'] === 1
                || $row['enabled'] === '1'
                || $row['enabled'] === 'on'
            );

            $mappings[$form_id] = array_merge(
                [
                    'form_id' => $form_id,
                    'name' => sanitize_key((string) ($row['name'] ?? '')),
                    'phone' => $phone,
                    'email' => sanitize_key((string) ($row['email'] ?? '')),
                    'enabled' => $enabled,
                ],
                self::sanitize_message_channel_fields($row)
            );
        }

        return $mappings;
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<int, array{id: string, name: string}>
     */
    public static function load_flows_public(array $settings): array
    {
        if (($settings['api_key'] ?? '') === '') {
            return [];
        }

        $cached = get_transient('sendora_flows_cache');
        if (is_array($cached)) {
            return $cached;
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

            $id = sanitize_text_field((string) $flow['id']);
            if ($id === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $id)) {
                continue;
            }

            $flows[] = [
                'id' => $id,
                'name' => sanitize_text_field((string) ($flow['name'] ?? $id)),
            ];
        }

        set_transient('sendora_flows_cache', $flows, 5 * MINUTE_IN_SECONDS);

        return $flows;
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<int, array{id: string, name: string, content: string, type: string}>
     */
    public static function load_templates_public(array $settings): array
    {
        if (($settings['api_key'] ?? '') === '') {
            return [];
        }

        $cached = get_transient('sendora_templates_cache');
        if (is_array($cached)) {
            return $cached;
        }

        $result = Sendora_Api_Client::from_options()->list_templates();
        if (empty($result['ok']) || !is_array($result['data'])) {
            return [];
        }

        $items = isset($result['data']['data']) && is_array($result['data']['data'])
            ? $result['data']['data']
            : $result['data'];
        $templates = [];

        foreach ($items as $row) {
            if (!is_array($row) || empty($row['id'])) {
                continue;
            }

            // UUIDs use hyphens; keep letters/digits/_/- only.
            $id = sanitize_text_field((string) $row['id']);
            if ($id === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $id)) {
                continue;
            }

            $templates[] = [
                'id' => $id,
                'name' => sanitize_text_field((string) ($row['name'] ?? $id)),
                'content' => (string) ($row['content'] ?? ''),
                'type' => sanitize_text_field((string) ($row['type'] ?? 'text')),
            ];
        }

        // Never cache an empty list — a previous failure/zero state would hide new templates.
        if ($templates !== []) {
            set_transient('sendora_templates_cache', $templates, 5 * MINUTE_IN_SECONDS);
        } else {
            delete_transient('sendora_templates_cache');
        }

        return $templates;
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<int, array{id: string, name: string, phone: string, status: string}>
     */
    public static function load_connections_public(array $settings): array
    {
        if (($settings['api_key'] ?? '') === '') {
            return [];
        }

        $cached = get_transient('sendora_connections_cache');
        if (is_array($cached)) {
            return $cached;
        }

        $result = Sendora_Api_Client::from_options()->list_connections();
        if (empty($result['ok']) || !is_array($result['data'])) {
            return [];
        }

        $items = isset($result['data']['data']) && is_array($result['data']['data'])
            ? $result['data']['data']
            : $result['data'];
        $rows = [];

        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = sanitize_text_field((string) ($row['instance_name'] ?? ''));
            if ($name === '' || !preg_match('/^[A-Za-z0-9_.-]+$/', $name)) {
                continue;
            }
            $phone = sanitize_text_field((string) ($row['phone_connected'] ?? ''));
            $label = $name;
            if ($phone !== '') {
                $label .= ' (' . $phone . ')';
            }
            $rows[] = [
                'id' => $name,
                'name' => $label,
                'phone' => $phone,
                'status' => sanitize_text_field((string) ($row['status'] ?? '')),
            ];
        }

        set_transient('sendora_connections_cache', $rows, 5 * MINUTE_IN_SECONDS);

        return $rows;
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<int, array{id: string, name: string, phone: string, active: bool}>
     */
    public static function load_waba_public(array $settings): array
    {
        if (($settings['api_key'] ?? '') === '') {
            return [];
        }

        $cached = get_transient('sendora_waba_cache');
        if (is_array($cached)) {
            return $cached;
        }

        $result = Sendora_Api_Client::from_options()->list_waba();
        if (empty($result['ok']) || !is_array($result['data'])) {
            return [];
        }

        $items = isset($result['data']['data']) && is_array($result['data']['data'])
            ? $result['data']['data']
            : $result['data'];
        $rows = [];

        foreach ($items as $row) {
            if (!is_array($row) || empty($row['id'])) {
                continue;
            }
            $id = sanitize_text_field((string) $row['id']);
            if ($id === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $id)) {
                continue;
            }
            $waba_name = sanitize_text_field((string) ($row['waba_name'] ?? ''));
            $phone = sanitize_text_field((string) ($row['display_phone_number'] ?? ''));
            $label = $waba_name !== '' ? $waba_name : $id;
            if ($phone !== '') {
                $label .= ' (' . $phone . ')';
            }
            $rows[] = [
                'id' => $id,
                'name' => $label,
                'phone' => $phone,
                'active' => !empty($row['is_active']),
            ];
        }

        set_transient('sendora_waba_cache', $rows, 5 * MINUTE_IN_SECONDS);

        return $rows;
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<int, array{id: string, name: string, language: string, status: string}>
     */
    public static function load_meta_templates_public(array $settings, ?string $api_settings_id = null): array
    {
        if (($settings['api_key'] ?? '') === '') {
            return [];
        }

        $api_settings_id = $api_settings_id !== null ? sanitize_text_field($api_settings_id) : '';
        $cache_key = 'sendora_meta_tpl_' . ($api_settings_id !== '' ? md5($api_settings_id) : 'default');
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            return $cached;
        }

        $result = Sendora_Api_Client::from_options()->list_meta_templates(
            $api_settings_id !== '' ? $api_settings_id : null
        );
        if (empty($result['ok']) || !is_array($result['data'])) {
            return [];
        }

        $items = isset($result['data']['data']) && is_array($result['data']['data'])
            ? $result['data']['data']
            : $result['data'];
        if (isset($items['templates']) && is_array($items['templates'])) {
            $items = $items['templates'];
        }

        $templates = [];
        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = sanitize_text_field((string) ($row['name'] ?? ''));
            if ($name === '' || !preg_match('/^[A-Za-z0-9_.-]+$/', $name)) {
                continue;
            }
            $status = strtoupper(sanitize_text_field((string) ($row['status'] ?? '')));
            if ($status !== '' && $status !== 'APPROVED') {
                continue;
            }
            $language = sanitize_text_field((string) ($row['language'] ?? 'pt_BR'));
            $label = $name;
            if ($language !== '') {
                $label .= ' (' . $language . ')';
            }
            $templates[] = [
                'id' => $name,
                'name' => $label,
                'language' => $language !== '' ? $language : 'pt_BR',
                'status' => $status !== '' ? $status : 'APPROVED',
            ];
        }

        if ($templates !== []) {
            set_transient($cache_key, $templates, 5 * MINUTE_IN_SECONDS);
        } else {
            delete_transient($cache_key);
        }

        return $templates;
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<int, array{id: string, name: string}>
     */
    public static function load_funnels_public(array $settings): array
    {
        if (($settings['api_key'] ?? '') === '') {
            return [];
        }

        $cached = get_transient('sendora_funnels_cache');
        if (is_array($cached)) {
            return $cached;
        }

        $result = Sendora_Api_Client::from_options()->list_funnels();
        if (empty($result['ok']) || !is_array($result['data'])) {
            return [];
        }

        $items = isset($result['data']['data']) && is_array($result['data']['data'])
            ? $result['data']['data']
            : $result['data'];
        $funnels = [];

        foreach ($items as $row) {
            if (!is_array($row) || empty($row['id'])) {
                continue;
            }
            $id = sanitize_text_field((string) $row['id']);
            if ($id === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $id)) {
                continue;
            }
            $funnels[] = [
                'id' => $id,
                'name' => sanitize_text_field((string) ($row['name'] ?? $row['title'] ?? $id)),
            ];
        }

        set_transient('sendora_funnels_cache', $funnels, 5 * MINUTE_IN_SECONDS);

        return $funnels;
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<int, array{id: string, label: string, funnel_id: string}>
     */
    public static function load_stages_public(array $settings): array
    {
        if (($settings['api_key'] ?? '') === '') {
            return [];
        }

        $cached = get_transient('sendora_stages_cache');
        if (is_array($cached)) {
            return $cached;
        }

        $result = Sendora_Api_Client::from_options()->list_stages();
        if (empty($result['ok']) || !is_array($result['data'])) {
            return [];
        }

        $items = isset($result['data']['data']) && is_array($result['data']['data'])
            ? $result['data']['data']
            : $result['data'];
        $stages = [];

        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }
            // Public API uses stage_id as the slug stored on contacts.stage
            $stage_id = sanitize_text_field((string) ($row['stage_id'] ?? $row['id'] ?? ''));
            if ($stage_id === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $stage_id)) {
                continue;
            }
            $funnel_id = sanitize_text_field((string) ($row['funnel_id'] ?? ''));
            $stages[] = [
                'id' => $stage_id,
                'label' => sanitize_text_field((string) ($row['label'] ?? $row['name'] ?? $stage_id)),
                'funnel_id' => $funnel_id,
            ];
        }

        set_transient('sendora_stages_cache', $stages, 5 * MINUTE_IN_SECONDS);

        return $stages;
    }
}
