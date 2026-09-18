<?php
/**
 * Plugin bootstrap / service wiring.
 *
 * @package Sendora
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Sendora_Plugin
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function run(): void
    {
        (new Sendora_Settings())->run();
        (new Sendora_Admin())->run();
        Sendora_Events::register_hooks();
        (new Sendora_Forms())->run();
        (new Sendora_Widget())->run();
        if ((defined('WPCF7_VERSION') || class_exists('WPCF7_ContactForm')) && class_exists('Sendora_CF7')) {
            (new Sendora_CF7())->run();
        }
        if (
            (defined('WC_VERSION') || class_exists('WooCommerce'))
            && class_exists('Sendora_WooCommerce')
        ) {
            (new Sendora_WooCommerce())->run();
        }
    }

    private function __construct()
    {
    }
}
