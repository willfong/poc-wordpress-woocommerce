<?php
/**
 * Deterministic WooCommerce fixture generation.
 *
 * @package WooDevTemplate\Seed
 */

declare(strict_types=1);

namespace WooDevTemplate\Seed;

/**
 * Creates repeatable WooCommerce data using public APIs.
 */
final class StoreSeeder
{
    private const META_KEY = '_woo_dev_template_seeded';

    /**
     * Seed store fixtures.
     *
     * @param array{profile: string, reset: bool, products: int, orders: int} $options Options.
     * @return array{categories: int, products: int, customers: int, coupons: int, orders: int}
     */
    public function seed(array $options): array
    {
        if ($options['reset']) {
            $this->reset();
        }

        $this->configure_store();

        $category_ids = $this->seed_categories($options['profile']);
        $product_ids  = $this->seed_products($options['profile'], $category_ids, $options['products']);
        $customer_ids = $this->seed_customers($options['profile']);
        $coupon_ids   = $this->seed_coupons();
        $order_ids    = $this->seed_orders($options['profile'], $product_ids, $customer_ids, $coupon_ids, $options['orders']);

        return [
            'categories' => count($category_ids),
            'products'   => count($product_ids),
            'customers'  => count($customer_ids),
            'coupons'    => count($coupon_ids),
            'orders'     => count($order_ids),
        ];
    }

    /**
     * Remove previously seeded records.
     */
    private function reset(): void
    {
        if (function_exists('wc_get_orders')) {
            $orders = wc_get_orders(
                [
                    'limit'      => -1,
                    'return'     => 'ids',
                    'status'     => array_keys(wc_get_order_statuses()),
                    'meta_query' => [
                        [
                            'key'   => self::META_KEY,
                            'value' => '1',
                        ],
                    ],
                ]
            );

            foreach ($orders as $order_id) {
                $order = wc_get_order((int) $order_id);

                if ($order) {
                    $order->delete(true);
                }
            }
        }

        foreach (['shop_coupon', 'product', 'product_variation'] as $post_type) {
            $posts = get_posts(
                [
                    'post_type'      => $post_type,
                    'post_status'    => 'any',
                    'posts_per_page' => -1,
                    'fields'         => 'ids',
                    'meta_key'       => self::META_KEY,
                    'meta_value'     => '1',
                ]
            );

            foreach ($posts as $post_id) {
                wp_delete_post((int) $post_id, true);
            }
        }

        $users = get_users(
            [
                'role__in' => ['customer'],
                'meta_key' => self::META_KEY,
                'fields'   => 'ID',
            ]
        );

        if (! function_exists('wp_delete_user')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }

        foreach ($users as $user_id) {
            wp_delete_user((int) $user_id);
        }

        $terms = get_terms(
            [
                'taxonomy'   => 'product_cat',
                'hide_empty' => false,
                'meta_key'   => self::META_KEY,
                'fields'     => 'ids',
            ]
        );

        if (! is_wp_error($terms)) {
            foreach ($terms as $term_id) {
                wp_delete_term((int) $term_id, 'product_cat');
            }
        }
    }

    /**
     * Apply practical local store settings.
     */
    private function configure_store(): void
    {
        update_option('woocommerce_manage_stock', 'yes');
        update_option('woocommerce_notify_low_stock', 'yes');
        update_option('woocommerce_notify_no_stock', 'yes');
        update_option('woocommerce_stock_email_recipient', get_option('admin_email'));
        update_option('woocommerce_calc_taxes', 'yes');
        update_option('woocommerce_enable_coupons', 'yes');
        update_option('woocommerce_registration_generate_username', 'yes');
        update_option('woocommerce_registration_generate_password', 'yes');

        $this->ensure_flat_rate_shipping();
        $this->ensure_tax_rate();
    }

    /**
     * Ensure a basic US shipping zone exists.
     */
    private function ensure_flat_rate_shipping(): void
    {
        if (! class_exists('\WC_Shipping_Zone')) {
            return;
        }

        $zone = new \WC_Shipping_Zone(0);
        $methods = $zone->get_shipping_methods();

        foreach ($methods as $method) {
            if ('flat_rate' === $method->id) {
                return;
            }
        }

        $instance_id = $zone->add_shipping_method('flat_rate');
        $method = \WC_Shipping_Zones::get_shipping_method($instance_id);

        if ($method) {
            $method->set_post_data(
                [
                    'woocommerce_flat_rate_title' => 'Flat rate',
                    'woocommerce_flat_rate_cost'  => '7.50',
                ]
            );
            $method->process_admin_options();
        }
    }

    /**
     * Ensure a basic tax rate exists.
     */
    private function ensure_tax_rate(): void
    {
        if (! class_exists('\WC_Tax')) {
            return;
        }

        $rates = \WC_Tax::find_rates(['country' => 'US', 'state' => 'OR']);

        if (! empty($rates)) {
            return;
        }

        \WC_Tax::_insert_tax_rate(
            [
                'tax_rate_country'  => 'US',
                'tax_rate_state'    => 'OR',
                'tax_rate'          => '0.0000',
                'tax_rate_name'     => 'Local Dev Tax',
                'tax_rate_priority' => 1,
                'tax_rate_compound' => 0,
                'tax_rate_shipping' => 1,
                'tax_rate_order'    => 0,
                'tax_rate_class'    => '',
            ]
        );
    }

    /**
     * Seed product categories.
     *
     * @return array<string, int>
     */
    private function seed_categories(string $profile): array
    {
        $names = ['Apparel', 'Accessories', 'Digital Goods'];

        if ('small' !== $profile) {
            $names = array_merge($names, ['Home Office', 'Seasonal']);
        }

        $ids = [];

        foreach ($names as $name) {
            $slug = sanitize_title('wdt-' . $name);
            $term = get_term_by('slug', $slug, 'product_cat');

            if (! $term) {
                $created = wp_insert_term($name, 'product_cat', ['slug' => $slug]);

                if (is_wp_error($created)) {
                    continue;
                }

                $term_id = (int) $created['term_id'];
                update_term_meta($term_id, self::META_KEY, '1');
            } else {
                $term_id = (int) $term->term_id;
            }

            $ids[$slug] = $term_id;
        }

        return $ids;
    }

    /**
     * Seed products for the selected profile.
     *
     * @param array<string, int> $category_ids Category map.
     * @return array<int, int>
     */
    private function seed_products(string $profile, array $category_ids, int $performance_count): array
    {
        $products = [
            ['sku' => 'WDT-TEE-BASIC', 'name' => 'Basic Logo Tee', 'price' => '24.00', 'stock' => 80, 'category' => 'wdt-apparel'],
            ['sku' => 'WDT-MUG', 'name' => 'Developer Mug', 'price' => '16.00', 'stock' => 120, 'category' => 'wdt-accessories'],
            ['sku' => 'WDT-EBOOK', 'name' => 'Plugin Patterns Ebook', 'price' => '9.00', 'stock' => null, 'category' => 'wdt-digital-goods', 'virtual' => true, 'downloadable' => true],
        ];

        if ('small' !== $profile) {
            $products[] = ['sku' => 'WDT-HOODIE', 'name' => 'Variable Hoodie', 'price' => '48.00', 'stock' => 40, 'category' => 'wdt-apparel', 'variable' => true];
            $products[] = ['sku' => 'WDT-STAND', 'name' => 'Laptop Stand', 'price' => '59.00', 'stock' => 12, 'category' => 'wdt-home-office'];
            $products[] = ['sku' => 'WDT-STICKERS', 'name' => 'Sticker Pack', 'price' => '6.00', 'stock' => 0, 'category' => 'wdt-seasonal'];
        }

        if ('performance' === $profile) {
            for ($i = 1; $i <= $performance_count; $i++) {
                $products[] = [
                    'sku'      => sprintf('WDT-PERF-%04d', $i),
                    'name'     => sprintf('Performance Product %04d', $i),
                    'price'    => (string) (10 + ($i % 90)),
                    'stock'    => 5 + ($i % 75),
                    'category' => array_keys($category_ids)[$i % count($category_ids)],
                ];
            }
        }

        $ids = [];

        foreach ($products as $data) {
            $ids[] = ! empty($data['variable'])
                ? $this->upsert_variable_product($data, $category_ids)
                : $this->upsert_simple_product($data, $category_ids);
        }

        return array_values(array_filter($ids));
    }

    /**
     * Create or update a simple product.
     *
     * @param array<string, mixed> $data Product data.
     * @param array<string, int>   $category_ids Category map.
     */
    private function upsert_simple_product(array $data, array $category_ids): int
    {
        $product_id = wc_get_product_id_by_sku((string) $data['sku']);
        $product = $product_id ? wc_get_product($product_id) : new \WC_Product_Simple();

        if (! $product instanceof \WC_Product_Simple) {
            return 0;
        }

        $product->set_name((string) $data['name']);
        $product->set_sku((string) $data['sku']);
        $product->set_regular_price((string) $data['price']);
        $product->set_description('Seeded fixture product for WooCommerce plugin development.');
        $product->set_short_description('Fixture product.');
        $product->set_status('publish');
        $product->set_catalog_visibility('visible');
        $product->set_virtual(! empty($data['virtual']));
        $product->set_downloadable(! empty($data['downloadable']));

        if (null === ($data['stock'] ?? null)) {
            $product->set_manage_stock(false);
            $product->set_stock_status('instock');
        } else {
            $product->set_manage_stock(true);
            $product->set_stock_quantity((int) $data['stock']);
            $product->set_stock_status(((int) $data['stock']) > 0 ? 'instock' : 'outofstock');
        }

        if (isset($category_ids[(string) $data['category']])) {
            $product->set_category_ids([$category_ids[(string) $data['category']]]);
        }

        $id = $product->save();
        update_post_meta($id, self::META_KEY, '1');

        return $id;
    }

    /**
     * Create or update a variable product with size variations.
     *
     * @param array<string, mixed> $data Product data.
     * @param array<string, int>   $category_ids Category map.
     */
    private function upsert_variable_product(array $data, array $category_ids): int
    {
        $product_id = wc_get_product_id_by_sku((string) $data['sku']);
        $product = $product_id ? wc_get_product($product_id) : new \WC_Product_Variable();

        if (! $product instanceof \WC_Product_Variable) {
            return 0;
        }

        $product->set_name((string) $data['name']);
        $product->set_sku((string) $data['sku']);
        $product->set_status('publish');
        $product->set_description('Seeded variable fixture product.');

        if (isset($category_ids[(string) $data['category']])) {
            $product->set_category_ids([$category_ids[(string) $data['category']]]);
        }

        $attribute = new \WC_Product_Attribute();
        $attribute->set_id(0);
        $attribute->set_name('Size');
        $attribute->set_options(['Small', 'Medium', 'Large']);
        $attribute->set_visible(true);
        $attribute->set_variation(true);
        $product->set_attributes([$attribute]);

        $id = $product->save();
        update_post_meta($id, self::META_KEY, '1');

        foreach (['Small' => '44.00', 'Medium' => '48.00', 'Large' => '52.00'] as $size => $price) {
            $sku = sprintf('%s-%s', (string) $data['sku'], strtoupper(substr($size, 0, 1)));
            $variation_id = wc_get_product_id_by_sku($sku);
            $variation = $variation_id ? wc_get_product($variation_id) : new \WC_Product_Variation();

            if (! $variation instanceof \WC_Product_Variation) {
                continue;
            }

            $variation->set_parent_id($id);
            $variation->set_sku($sku);
            $variation->set_regular_price($price);
            $variation->set_attributes(['size' => $size]);
            $variation->set_manage_stock(true);
            $variation->set_stock_quantity(15);
            $variation->set_status('publish');
            update_post_meta($variation->save(), self::META_KEY, '1');
        }

        \WC_Product_Variable::sync($id);

        return $id;
    }

    /**
     * Seed customers.
     *
     * @return array<int, int>
     */
    private function seed_customers(string $profile): array
    {
        $customers = [
            ['first' => 'Ada', 'last' => 'Lovelace', 'email' => 'ada@example.test'],
            ['first' => 'Grace', 'last' => 'Hopper', 'email' => 'grace@example.test'],
        ];

        if ('small' !== $profile) {
            $customers[] = ['first' => 'Katherine', 'last' => 'Johnson', 'email' => 'katherine@example.test'];
            $customers[] = ['first' => 'Dorothy', 'last' => 'Vaughan', 'email' => 'dorothy@example.test'];
        }

        $ids = [];

        foreach ($customers as $customer) {
            $user = get_user_by('email', $customer['email']);

            if ($user) {
                $user_id = (int) $user->ID;
            } else {
                $user_id = wp_insert_user(
                    [
                        'user_login' => sanitize_user(strtok($customer['email'], '@')),
                        'user_pass'  => wp_generate_password(24, true),
                        'user_email' => $customer['email'],
                        'first_name' => $customer['first'],
                        'last_name'  => $customer['last'],
                        'role'       => 'customer',
                    ]
                );

                if (is_wp_error($user_id)) {
                    continue;
                }
            }

            update_user_meta((int) $user_id, self::META_KEY, '1');
            update_user_meta((int) $user_id, 'billing_first_name', $customer['first']);
            update_user_meta((int) $user_id, 'billing_last_name', $customer['last']);
            update_user_meta((int) $user_id, 'billing_email', $customer['email']);
            update_user_meta((int) $user_id, 'billing_country', 'US');
            update_user_meta((int) $user_id, 'billing_state', 'OR');
            update_user_meta((int) $user_id, 'billing_city', 'Portland');
            update_user_meta((int) $user_id, 'billing_postcode', '97205');
            update_user_meta((int) $user_id, 'billing_address_1', '123 Template Street');
            $ids[] = (int) $user_id;
        }

        return $ids;
    }

    /**
     * Seed coupons.
     *
     * @return array<int, int>
     */
    private function seed_coupons(): array
    {
        $coupons = [
            ['code' => 'WELCOME10', 'type' => 'percent', 'amount' => '10'],
            ['code' => 'FREESHIP', 'type' => 'fixed_cart', 'amount' => '7.50'],
        ];

        $ids = [];

        foreach ($coupons as $data) {
            $coupon_id = wc_get_coupon_id_by_code($data['code']);
            $coupon = $coupon_id ? new \WC_Coupon($coupon_id) : new \WC_Coupon();
            $coupon->set_code($data['code']);
            $coupon->set_discount_type($data['type']);
            $coupon->set_amount($data['amount']);
            $coupon->set_description('Seeded coupon for WooCommerce plugin development.');
            $id = $coupon->save();
            update_post_meta($id, self::META_KEY, '1');
            $ids[] = $id;
        }

        return $ids;
    }

    /**
     * Seed orders.
     *
     * @param array<int, int> $product_ids Product IDs.
     * @param array<int, int> $customer_ids Customer IDs.
     * @param array<int, int> $coupon_ids Coupon IDs.
     * @return array<int, int>
     */
    private function seed_orders(string $profile, array $product_ids, array $customer_ids, array $coupon_ids, int $performance_count): array
    {
        if (empty($product_ids) || empty($customer_ids)) {
            return [];
        }

        $count = match ($profile) {
            'small' => 2,
            'performance' => $performance_count,
            default => 12,
        };

        $statuses = ['pending', 'processing', 'completed', 'on-hold', 'cancelled', 'refunded', 'failed'];
        $ids = [];

        for ($i = 1; $i <= $count; $i++) {
            $order_key = sprintf('wdt-order-%04d', $i);
            $existing = wc_get_orders(
                [
                    'limit'      => 1,
                    'return'     => 'ids',
                    'status'     => array_keys(wc_get_order_statuses()),
                    'meta_query' => [
                        [
                            'key'   => '_woo_dev_template_order_key',
                            'value' => $order_key,
                        ],
                    ],
                ]
            );

            $order = ! empty($existing) ? wc_get_order((int) $existing[0]) : wc_create_order();

            if (! $order instanceof \WC_Order) {
                continue;
            }

            if (empty($existing)) {
                $order->add_product(wc_get_product($product_ids[$i % count($product_ids)]), 1 + ($i % 3));

                if (0 === $i % 4 && ! empty($coupon_ids)) {
                    $coupon = new \WC_Coupon($coupon_ids[0]);
                    $order->apply_coupon($coupon);
                }
            }

            $customer_id = $customer_ids[$i % count($customer_ids)];
            $order->set_customer_id($customer_id);
            $order->set_status($statuses[$i % count($statuses)]);
            $order->set_billing_first_name((string) get_user_meta($customer_id, 'billing_first_name', true));
            $order->set_billing_last_name((string) get_user_meta($customer_id, 'billing_last_name', true));
            $order->set_billing_email((string) get_user_meta($customer_id, 'billing_email', true));
            $order->set_billing_address_1((string) get_user_meta($customer_id, 'billing_address_1', true));
            $order->set_billing_city((string) get_user_meta($customer_id, 'billing_city', true));
            $order->set_billing_state((string) get_user_meta($customer_id, 'billing_state', true));
            $order->set_billing_postcode((string) get_user_meta($customer_id, 'billing_postcode', true));
            $order->set_billing_country((string) get_user_meta($customer_id, 'billing_country', true));
            $order->set_payment_method('bacs');
            $order->set_payment_method_title('Direct bank transfer');
            $order->calculate_totals();
            $order->save();

            update_post_meta($order->get_id(), self::META_KEY, '1');
            update_post_meta($order->get_id(), '_woo_dev_template_order_key', $order_key);
            $ids[] = $order->get_id();
        }

        return $ids;
    }
}
