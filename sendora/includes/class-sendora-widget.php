<?php
/**
 * Official Sendora chat widget embed (opt-in).
 *
 * @package Sendora
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Sendora_Widget
{
    private const UUID_PATTERN =
        '/[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}/i';

    public function run(): void
    {
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_embed'], 20);
    }

    public static function enqueue_embed(): void
    {
        if (is_admin() || is_feed() || is_preview()) {
            return;
        }

        $settings = Sendora_Settings::get_settings();

        if (empty($settings['widget_enabled'])) {
            return;
        }

        if (!self::should_display($settings)) {
            return;
        }

        $widget_id = self::sanitize_widget_id((string) ($settings['widget_id'] ?? ''));
        if ($widget_id === '') {
            return;
        }

        $api_base = rtrim((string) ($settings['api_base'] ?? ''), '/');
        if ($api_base === '' || !self::is_https_url($api_base)) {
            return;
        }

        // esc_url_raw keeps a real "&" for the query string. esc_url() would emit
        // &#038; which breaks when the URL is used as a script src / in JS.
        $script_url = esc_url_raw(
            add_query_arg(
                [
                    'id' => $widget_id,
                    'v' => '6',
                ],
                $api_base . '/public/widget/embed'
            )
        );
        if ($script_url === '') {
            return;
        }

        // Enqueue the official embed directly. Do not set window.__sendora_widget
        // here — the embed script uses that flag as its own boot guard.
        wp_enqueue_script(
            'sendora-widget',
            $script_url,
            [],
            SENDORA_VERSION,
            true
        );
    }

    /**
     * @param array<string, mixed> $settings
     */
    public static function should_display(array $settings): bool
    {
        $mode = (string) ($settings['widget_display'] ?? 'all');
        if ($mode !== 'specific') {
            return true;
        }

        $page_ids = $settings['widget_page_ids'] ?? [];
        if (!is_array($page_ids) || $page_ids === []) {
            return false;
        }

        $page_ids = array_map('intval', $page_ids);

        if (!is_page()) {
            return false;
        }

        $current_id = (int) get_queried_object_id();

        return $current_id > 0 && in_array($current_id, $page_ids, true);
    }

    /**
     * @deprecated Kept for tests and explicit calls; prefer enqueue_embed.
     */
    public static function render_embed(): void
    {
        self::enqueue_embed();
    }

    /**
     * Accept a bare UUID, or a pasted embed URL / query like
     * `8ab2…d4c3&v=6` or `…/embed?id=UUID&v=6`.
     */
    public static function sanitize_widget_id(string $widget_id): string
    {
        $widget_id = trim(wp_unslash($widget_id));
        $widget_id = sanitize_text_field($widget_id);
        if ($widget_id === '') {
            return '';
        }

        if (preg_match(self::UUID_PATTERN, $widget_id, $matches)) {
            return strtolower($matches[0]);
        }

        return '';
    }

    private static function is_https_url(string $url): bool
    {
        $parts = wp_parse_url($url);

        return is_array($parts)
            && ($parts['scheme'] ?? '') === 'https'
            && !empty($parts['host']);
    }
}
