<?php
/**
 * Plugin Name: Sendora
 * Description: Connect WordPress forms, messaging, and automations to Sendora.
 * Version: 0.1.0
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * Author: Sendora
 * Text Domain: sendora
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('SENDORA_VERSION', '0.1.0');
define('SENDORA_PLUGIN_FILE', __FILE__);
define('SENDORA_PLUGIN_DIR', plugin_dir_path(__FILE__));

require_once SENDORA_PLUGIN_DIR . 'includes/class-sendora-phone.php';
require_once SENDORA_PLUGIN_DIR . 'includes/class-sendora-api-client.php';
require_once SENDORA_PLUGIN_DIR . 'includes/class-sendora-plugin.php';

add_action('plugins_loaded', static function (): void {
    Sendora_Plugin::instance()->run();
});
