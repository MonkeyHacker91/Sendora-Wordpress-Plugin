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
            foreach (['default_flow_id'] as $field) {
                $value = sanitize_text_field((string) ($input[$field] ?? ''));
                $saved[$field] = preg_match('/^[A-Za-z0-9_-]*$/', $value) ? $value : '';
            }
            $saved['default_cc'] = preg_replace(
                '/\D+/',
                '',
                sanitize_text_field((string) ($input['default_cc'] ?? '55'))
            ) ?: '55';
            $saved['cf7_mappings'] = array_key_exists('cf7_mappings', $input)
                ? self::sanitize_cf7_mappings($input['cf7_mappings'])
                : (is_array($saved['cf7_mappings'] ?? null) ? $saved['cf7_mappings'] : []);
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
            foreach (['woo_paid_flow_id'] as $field) {
                $value = sanitize_text_field((string) ($input[$field] ?? ''));
                $saved[$field] = preg_match('/^[A-Za-z0-9_-]*$/', $value) ? $value : '';
            }
            foreach (['woo_on_paid', 'woo_on_cancelled'] as $field) {
                $saved[$field] = isset($input[$field]) && (string) $input[$field] === '1';
            }
            $paid_mode = sanitize_text_field((string) ($input['woo_paid_mode'] ?? 'off'));
            $saved['woo_paid_mode'] = in_array($paid_mode, ['flow', 'message', 'off'], true) ? $paid_mode : 'off';
            $created_mode = sanitize_text_field((string) ($input['woo_created_mode'] ?? 'off'));
            $saved['woo_created_mode'] = in_array(
                $created_mode,
                ['contact_only', 'contact_and_flow', 'off'],
                true
            ) ? $created_mode : 'off';
            $saved['api_base'] = self::DEFAULT_API_BASE;

            return $saved;
        }

        if (($input['_partial'] ?? '') === 'general') {
            $saved['default_cc'] = preg_replace(
                '/\D+/',
                '',
                sanitize_text_field((string) ($input['default_cc'] ?? '55'))
            ) ?: '55';
            $saved['api_base'] = self::DEFAULT_API_BASE;

            return $saved;
        }

        $settings['widget_id'] = class_exists('Sendora_Widget')
            ? Sendora_Widget::sanitize_widget_id((string) ($input['widget_id'] ?? ''))
            : sanitize_text_field((string) ($input['widget_id'] ?? ''));

        $display = sanitize_text_field((string) ($input['widget_display'] ?? 'all'));
        $settings['widget_display'] = in_array($display, ['all', 'specific'], true) ? $display : 'all';
        $settings['widget_page_ids'] = self::sanitize_page_ids($input['widget_page_ids'] ?? []);

        foreach (['default_flow_id', 'woo_paid_flow_id'] as $field) {
            $value = sanitize_text_field((string) ($input[$field] ?? ''));
            $settings[$field] = preg_match('/^[A-Za-z0-9_-]*$/', $value) ? $value : '';
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

        $created_mode = sanitize_text_field((string) ($input['woo_created_mode'] ?? 'off'));
        $settings['woo_created_mode'] = in_array(
            $created_mode,
            ['contact_only', 'contact_and_flow', 'off'],
            true
        ) ? $created_mode : 'off';

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
     * @return array<string, array{form_id: string, name: string, phone: string, email: string}>
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

            $mappings[$form_id] = [
                'form_id' => $form_id,
                'name' => sanitize_key((string) ($row['name'] ?? '')),
                'phone' => $phone,
                'email' => sanitize_key((string) ($row['email'] ?? '')),
            ];
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
}
