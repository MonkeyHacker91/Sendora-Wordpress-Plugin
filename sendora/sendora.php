<?php
/**
 * Plugin Name:       Sendora
 * Plugin URI:        https://app.sendora.com.br
 * Description:       Connect WordPress forms, WooCommerce, and messaging to the Sendora platform.
 * Version:           0.8.7
 * Requires at least: 6.2
 * Requires PHP:      8.0
 * Author:            Sendora
 * Author URI:        https://sendora.com.br
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sendora
 * Domain Path:       /languages
 *
 * @package           Sendora
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('SENDORA_VERSION', '0.8.7');
define('SENDORA_PLUGIN_FILE', __FILE__);
define('SENDORA_PLUGIN_DIR', plugin_dir_path(__FILE__));

require_once SENDORA_PLUGIN_DIR . 'includes/class-sendora-phone.php';
require_once SENDORA_PLUGIN_DIR . 'includes/class-sendora-logger.php';
require_once SENDORA_PLUGIN_DIR . 'includes/class-sendora-api-client.php';
require_once SENDORA_PLUGIN_DIR . 'includes/class-sendora-template-vars.php';
require_once SENDORA_PLUGIN_DIR . 'includes/class-sendora-outbound.php';
require_once SENDORA_PLUGIN_DIR . 'includes/class-sendora-settings.php';
require_once SENDORA_PLUGIN_DIR . 'includes/class-sendora-automations.php';
require_once SENDORA_PLUGIN_DIR . 'includes/class-sendora-onboarding.php';
require_once SENDORA_PLUGIN_DIR . 'includes/class-sendora-connection.php';
require_once SENDORA_PLUGIN_DIR . 'includes/Events/class-sendora-events.php';
require_once SENDORA_PLUGIN_DIR . 'includes/Admin/class-sendora-admin.php';
require_once SENDORA_PLUGIN_DIR . 'includes/class-sendora-forms.php';
require_once SENDORA_PLUGIN_DIR . 'includes/class-sendora-widget.php';
require_once SENDORA_PLUGIN_DIR . 'includes/class-sendora-plugin.php';

register_activation_hook(SENDORA_PLUGIN_FILE, static function (): void {
    Sendora_Logger::install_table();
});

add_action(
    'before_woocommerce_init',
    static function (): void {
        if (!class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            return;
        }

        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            SENDORA_PLUGIN_FILE,
            true
        );
    }
);

add_action('plugins_loaded', static function (): void {
    if (defined('WPCF7_VERSION') || class_exists('WPCF7_ContactForm')) {
        require_once SENDORA_PLUGIN_DIR . 'includes/class-sendora-cf7.php';
    }

    if (defined('WC_VERSION') || class_exists('WooCommerce')) {
        require_once SENDORA_PLUGIN_DIR . 'includes/class-sendora-woocommerce.php';
        require_once SENDORA_PLUGIN_DIR . 'includes/class-sendora-abandoned-cart.php';
    }

    Sendora_Plugin::instance()->run();
}, 20);
