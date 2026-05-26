<?php
/**
 * Main plugin bootstrap.
 *
 * @package WooDevTemplate
 */

declare(strict_types=1);

namespace WooDevTemplate;

use WooDevTemplate\Admin\SettingsPage;
use WooDevTemplate\WooCommerce\OrderNotes;

/**
 * Wires plugin services into WordPress.
 */
final class Plugin
{
    private static ?self $instance = null;

    private bool $booted = false;

    /**
     * Get the singleton plugin instance.
     */
    public static function instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Activation callback.
     */
    public static function activate(): void
    {
        update_option('woo_dev_template_version', WOO_DEV_TEMPLATE_VERSION);
    }

    /**
     * Deactivation callback.
     */
    public static function deactivate(): void
    {
        wp_clear_scheduled_hook('woo_dev_template_hourly');
    }

    /**
     * Register hooks once WordPress has loaded plugins.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        if (is_admin()) {
            (new SettingsPage())->register();
        }

        if ($this->is_woocommerce_active()) {
            (new OrderNotes())->register();
        }

    }

    /**
     * Whether WooCommerce is available.
     */
    public function is_woocommerce_active(): bool
    {
        return class_exists('WooCommerce') && function_exists('wc_get_product');
    }
}
