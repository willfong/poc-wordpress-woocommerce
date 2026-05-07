<?php
/**
 * Example WooCommerce hook service.
 *
 * @package WooDevTemplate\WooCommerce
 */

declare(strict_types=1);

namespace WooDevTemplate\WooCommerce;

/**
 * Adds a visible, low-risk sample behavior for plugin developers.
 */
final class OrderNotes
{
    /**
     * Register WooCommerce hooks.
     */
    public function register(): void
    {
        add_action('woocommerce_new_order', [$this, 'add_order_note'], 10, 1);
    }

    /**
     * Add a note to new orders when the example setting is enabled.
     *
     * @param int $order_id WooCommerce order ID.
     */
    public function add_order_note(int $order_id): void
    {
        $settings = get_option('woo_dev_template_settings', ['enabled' => true]);

        if (is_array($settings) && empty($settings['enabled'])) {
            return;
        }

        $order = wc_get_order($order_id);

        if (! $order) {
            return;
        }

        $order->add_order_note(__('Woo Dev Template sample order hook ran.', 'woo-dev-template'));
    }
}

