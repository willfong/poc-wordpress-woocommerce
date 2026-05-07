<?php
/**
 * Plugin Name: Woo Dev Template
 * Plugin URI: https://example.test/
 * Description: A modern WooCommerce plugin scaffold for local development templates.
 * Version: 0.1.0
 * Author: Template Author
 * Requires at least: 6.8
 * Requires PHP: 8.3
 * WC requires at least: 9.0
 * Text Domain: woo-dev-template
 *
 * @package WooDevTemplate
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('WOO_DEV_TEMPLATE_FILE', __FILE__);
define('WOO_DEV_TEMPLATE_PATH', plugin_dir_path(__FILE__));
define('WOO_DEV_TEMPLATE_URL', plugin_dir_url(__FILE__));
define('WOO_DEV_TEMPLATE_VERSION', '0.1.0');

$autoload = WOO_DEV_TEMPLATE_PATH . 'vendor/autoload.php';

if (is_readable($autoload)) {
    require_once $autoload;
}

if (! class_exists(\WooDevTemplate\Plugin::class)) {
    add_action(
        'admin_notices',
        static function (): void {
            if (! current_user_can('activate_plugins')) {
                return;
            }

            echo '<div class="notice notice-error"><p>';
            echo esc_html__('Woo Dev Template requires Composer dependencies. Run `make composer ARGS="install"`.', 'woo-dev-template');
            echo '</p></div>';
        }
    );

    return;
}

register_activation_hook(__FILE__, [\WooDevTemplate\Plugin::class, 'activate']);
register_deactivation_hook(__FILE__, [\WooDevTemplate\Plugin::class, 'deactivate']);

add_action(
    'plugins_loaded',
    static function (): void {
        \WooDevTemplate\Plugin::instance()->boot();
    }
);

