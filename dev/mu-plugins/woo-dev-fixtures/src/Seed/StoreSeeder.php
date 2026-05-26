<?php
/**
 * Deterministic WooCommerce fixture generation.
 *
 * @package WooDevFixtures\Seed
 */

declare(strict_types=1);

namespace WooDevFixtures\Seed;

/**
 * Creates repeatable WooCommerce data using WooCommerce CRUD APIs.
 */
final class StoreSeeder
{
    private const META_KEY = '_woo_dev_fixtures_seeded';
    private const ORDER_KEY_META = '_woo_dev_fixtures_order_key';

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
        $coupon_ids   = $this->seed_coupons($options['profile']);
        $order_ids    = $this->seed_orders($options['profile'], $product_ids, $customer_ids, $coupon_ids, $options['orders']);

        $this->apply_inventory_movements($options['profile']);

        return [
            'categories' => count($category_ids),
            'products'   => count($product_ids),
            'customers'  => count($customer_ids),
            'coupons'    => count($coupon_ids),
            'orders'     => count($order_ids),
        ];
    }

    /**
     * Metadata key used to mark seeded records.
     */
    public static function seeded_meta_key(): string
    {
        return self::META_KEY;
    }

    /**
     * Deterministic order key for idempotent order upserts.
     */
    public static function order_key_for_index(int $index): string
    {
        return sprintf('wdf-order-%04d', max(1, $index));
    }

    /**
     * Remove previously seeded records.
     */
    private function reset(): void
    {
        if (function_exists('wc_get_orders')) {
            $orders = array_merge(
                wc_get_orders(
                    [
                        'limit'      => -1,
                        'return'     => 'objects',
                        'status'     => array_keys(wc_get_order_statuses()),
                        'type'       => 'shop_order',
                        'meta_key'   => self::META_KEY,
                        'meta_value' => '1',
                    ]
                ),
                wc_get_orders(
                    [
                        'limit'      => -1,
                        'return'     => 'objects',
                        'status'     => 'any',
                        'type'       => 'shop_order_refund',
                        'meta_key'   => self::META_KEY,
                        'meta_value' => '1',
                    ]
                )
            );

            foreach ($orders as $order) {
                if ($order instanceof \WC_Order) {
                    $order->delete(true);
                }
            }
        }

        foreach (['product_variation', 'product', 'shop_coupon'] as $post_type) {
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
        update_option('woocommerce_store_address', '123 Template Street');
        update_option('woocommerce_store_city', 'Los Angeles');
        update_option('woocommerce_default_country', 'US:CA');
        update_option('woocommerce_store_postcode', '90013');
        update_option('woocommerce_currency', 'USD');
        update_option('woocommerce_manage_stock', 'yes');
        update_option('woocommerce_notify_low_stock', 'yes');
        update_option('woocommerce_notify_no_stock', 'yes');
        update_option('woocommerce_stock_email_recipient', get_option('admin_email'));
        update_option('woocommerce_calc_taxes', 'yes');
        update_option('woocommerce_enable_coupons', 'yes');
        update_option('woocommerce_registration_generate_username', 'yes');
        update_option('woocommerce_registration_generate_password', 'yes');

        $this->ensure_shipping_method('flat_rate', 'Flat rate', '7.50');
        $this->ensure_shipping_method('local_pickup', 'Local pickup', '0');
        $this->ensure_tax_rate();
    }

    /**
     * Ensure a shipping method exists in the fallback zone.
     */
    private function ensure_shipping_method(string $method_id, string $title, string $cost): void
    {
        if (! class_exists('\WC_Shipping_Zone')) {
            return;
        }

        $zone = new \WC_Shipping_Zone(0);

        foreach ($zone->get_shipping_methods() as $method) {
            if ($method_id === $method->id) {
                return;
            }
        }

        $instance_id = $zone->add_shipping_method($method_id);
        $method = \WC_Shipping_Zones::get_shipping_method($instance_id);

        if (! $method) {
            return;
        }

        $method->set_post_data(
            [
                sprintf('woocommerce_%s_title', $method_id) => $title,
                sprintf('woocommerce_%s_cost', $method_id)  => $cost,
            ]
        );
        $method->process_admin_options();
    }

    /**
     * Ensure a basic California tax rate exists.
     */
    private function ensure_tax_rate(): void
    {
        if (! class_exists('\WC_Tax')) {
            return;
        }

        $rates = \WC_Tax::find_rates(['country' => 'US', 'state' => 'CA']);

        if (! empty($rates)) {
            return;
        }

        \WC_Tax::_insert_tax_rate(
            [
                'tax_rate_country'  => 'US',
                'tax_rate_state'    => 'CA',
                'tax_rate'          => '7.2500',
                'tax_rate_name'     => 'CA Local Dev Tax',
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
            $names = array_merge($names, ['Home Office', 'Seasonal', 'Clearance']);
        }

        $ids = [];

        foreach ($names as $name) {
            $slug = sanitize_title('wdf-' . $name);
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
            [
                'sku'        => 'WDF-TEE-BASIC',
                'name'       => 'Everyday Logo Tee',
                'price'      => '24.00',
                'sale_price' => '19.00',
                'stock'      => 80,
                'low_stock'  => 10,
                'category'   => 'wdf-apparel',
                'weight'     => '0.4',
                'dimensions' => ['10', '8', '1'],
                'attributes' => ['Color' => ['Black', 'White']],
            ],
            [
                'sku'        => 'WDF-MUG',
                'name'       => 'Ceramic Developer Mug',
                'price'      => '16.00',
                'stock'      => 120,
                'category'   => 'wdf-accessories',
                'weight'     => '0.9',
                'dimensions' => ['5', '4', '4'],
                'attributes' => ['Material' => ['Ceramic']],
            ],
            [
                'sku'          => 'WDF-EBOOK',
                'name'         => 'Plugin Patterns Ebook',
                'price'        => '9.00',
                'stock'        => null,
                'category'     => 'wdf-digital-goods',
                'virtual'      => true,
                'downloadable' => true,
            ],
        ];

        if ('small' !== $profile) {
            $products[] = [
                'sku'        => 'WDF-HOODIE',
                'name'       => 'Variable Fleece Hoodie',
                'category'   => 'wdf-apparel',
                'variable'   => true,
                'attributes' => [
                    'Size'  => ['Small', 'Medium', 'Large'],
                    'Color' => ['Black', 'Blue'],
                ],
            ];
            $products[] = [
                'sku'        => 'WDF-STAND',
                'name'       => 'Aluminum Laptop Stand',
                'price'      => '59.00',
                'sale_price' => '49.00',
                'stock'      => 8,
                'low_stock'  => 5,
                'category'   => 'wdf-home-office',
                'weight'     => '2.1',
                'dimensions' => ['11', '9', '3'],
            ];
            $products[] = [
                'sku'        => 'WDF-STICKERS',
                'name'       => 'Seasonal Sticker Pack',
                'price'      => '6.00',
                'stock'      => 0,
                'category'   => 'wdf-seasonal',
                'weight'     => '0.1',
                'dimensions' => ['6', '4', '0.2'],
            ];
            $products[] = [
                'sku'        => 'WDF-NOTEBOOK',
                'name'       => 'Backordered Debug Notebook',
                'price'      => '14.00',
                'stock'      => 0,
                'backorders' => 'yes',
                'category'   => 'wdf-accessories',
                'weight'     => '0.7',
                'dimensions' => ['8', '5', '0.5'],
            ];
            $products[] = [
                'sku'      => 'WDF-GIFTCARD',
                'name'     => 'Digital Gift Card',
                'price'    => '25.00',
                'stock'    => null,
                'category' => 'wdf-digital-goods',
                'virtual'  => true,
            ];
        }

        if ('performance' === $profile) {
            $category_keys = array_keys($category_ids);

            for ($i = 1; $i <= $performance_count; $i++) {
                $products[] = [
                    'sku'        => sprintf('WDF-PERF-%04d', $i),
                    'name'       => sprintf('Performance Product %04d', $i),
                    'price'      => (string) (10 + ($i % 90)),
                    'sale_price' => 0 === $i % 6 ? (string) (8 + ($i % 70)) : '',
                    'stock'      => 5 + ($i % 75),
                    'category'   => $category_keys[$i % count($category_keys)],
                    'weight'     => (string) (0.2 + (($i % 10) / 10)),
                    'dimensions' => ['8', '6', '2'],
                ];
            }
        }

        $ids = [];

        foreach ($products as $data) {
            if (! empty($data['variable'])) {
                $ids = array_merge($ids, $this->upsert_variable_product($data, $category_ids));
                continue;
            }

            $ids[] = $this->upsert_simple_product($data, $category_ids);
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
        $product->set_sale_price((string) ($data['sale_price'] ?? ''));
        $product->set_description('Seeded fixture product for WooCommerce plugin development.');
        $product->set_short_description('Fixture product.');
        $product->set_status('publish');
        $product->set_catalog_visibility('visible');
        $product->set_tax_status('taxable');
        $product->set_virtual(! empty($data['virtual']));
        $product->set_downloadable(! empty($data['downloadable']));
        $product->set_backorders((string) ($data['backorders'] ?? 'no'));

        if (null === ($data['stock'] ?? null)) {
            $product->set_manage_stock(false);
            $product->set_stock_status('instock');
        } else {
            $stock = (int) $data['stock'];
            $product->set_manage_stock(true);
            $product->set_stock_quantity($stock);
            $product->set_low_stock_amount((int) ($data['low_stock'] ?? 3));
            $product->set_stock_status($this->stock_status($stock, (string) ($data['backorders'] ?? 'no')));
        }

        if (isset($data['weight'])) {
            $product->set_weight((string) $data['weight']);
        }

        if (isset($data['dimensions']) && is_array($data['dimensions'])) {
            $product->set_length((string) ($data['dimensions'][0] ?? ''));
            $product->set_width((string) ($data['dimensions'][1] ?? ''));
            $product->set_height((string) ($data['dimensions'][2] ?? ''));
        }

        if (isset($data['attributes']) && is_array($data['attributes'])) {
            $product->set_attributes($this->build_attributes($data['attributes']));
        }

        if (isset($category_ids[(string) $data['category']])) {
            $product->set_category_ids([$category_ids[(string) $data['category']]]);
        }

        $product->update_meta_data(self::META_KEY, '1');

        return $product->save();
    }

    /**
     * Create or update a variable product with size/color variations.
     *
     * @param array<string, mixed> $data Product data.
     * @param array<string, int>   $category_ids Category map.
     * @return array<int, int>
     */
    private function upsert_variable_product(array $data, array $category_ids): array
    {
        $product_id = wc_get_product_id_by_sku((string) $data['sku']);
        $product = $product_id ? wc_get_product($product_id) : new \WC_Product_Variable();

        if (! $product instanceof \WC_Product_Variable) {
            return [];
        }

        $product->set_name((string) $data['name']);
        $product->set_sku((string) $data['sku']);
        $product->set_status('publish');
        $product->set_description('Seeded variable fixture product.');
        $product->set_tax_status('taxable');
        $product->set_attributes($this->build_attributes((array) $data['attributes'], true));
        $product->set_default_attributes(['size' => 'Medium', 'color' => 'Black']);

        if (isset($category_ids[(string) $data['category']])) {
            $product->set_category_ids([$category_ids[(string) $data['category']]]);
        }

        $product->update_meta_data(self::META_KEY, '1');
        $parent_id = $product->save();

        $variations = [
            ['size' => 'Small', 'color' => 'Black', 'sku' => 'WDF-HOODIE-S-BLK', 'price' => '44.00', 'sale_price' => '39.00', 'stock' => 9],
            ['size' => 'Medium', 'color' => 'Black', 'sku' => 'WDF-HOODIE-M-BLK', 'price' => '48.00', 'sale_price' => '', 'stock' => 15],
            ['size' => 'Large', 'color' => 'Blue', 'sku' => 'WDF-HOODIE-L-BLU', 'price' => '52.00', 'sale_price' => '', 'stock' => 4],
        ];

        $ids = [$parent_id];

        foreach ($variations as $variation_data) {
            $variation_id = wc_get_product_id_by_sku($variation_data['sku']);
            $variation = $variation_id ? wc_get_product($variation_id) : new \WC_Product_Variation();

            if (! $variation instanceof \WC_Product_Variation) {
                continue;
            }

            $variation->set_parent_id($parent_id);
            $variation->set_sku($variation_data['sku']);
            $variation->set_regular_price($variation_data['price']);
            $variation->set_sale_price($variation_data['sale_price']);
            $variation->set_attributes(
                [
                    'size'  => $variation_data['size'],
                    'color' => $variation_data['color'],
                ]
            );
            $variation->set_tax_status('taxable');
            $variation->set_manage_stock(true);
            $variation->set_stock_quantity((int) $variation_data['stock']);
            $variation->set_low_stock_amount(3);
            $variation->set_stock_status($this->stock_status((int) $variation_data['stock'], 'no'));
            $variation->set_weight('1.2');
            $variation->set_length('14');
            $variation->set_width('12');
            $variation->set_height('2');
            $variation->set_status('publish');
            $variation->update_meta_data(self::META_KEY, '1');
            $ids[] = $variation->save();
        }

        \WC_Product_Variable::sync($parent_id);

        return $ids;
    }

    /**
     * Build custom product attributes.
     *
     * @param array<string, array<int, string>> $attributes Attribute map.
     * @return array<int, \WC_Product_Attribute>
     */
    private function build_attributes(array $attributes, bool $for_variations = false): array
    {
        $built = [];

        foreach ($attributes as $name => $options) {
            $attribute = new \WC_Product_Attribute();
            $attribute->set_id(0);
            $attribute->set_name((string) $name);
            $attribute->set_options(array_values($options));
            $attribute->set_visible(true);
            $attribute->set_variation($for_variations);
            $built[] = $attribute;
        }

        return $built;
    }

    /**
     * Normalize stock status from stock and backorder settings.
     */
    private function stock_status(int $stock, string $backorders): string
    {
        if ($stock > 0) {
            return 'instock';
        }

        return 'no' === $backorders ? 'outofstock' : 'onbackorder';
    }

    /**
     * Seed customers.
     *
     * @return array<int, int>
     */
    private function seed_customers(string $profile): array
    {
        $customers = [
            [
                'first'    => 'Ada',
                'last'     => 'Lovelace',
                'email'    => 'ada@example.test',
                'billing'  => ['city' => 'Los Angeles', 'state' => 'CA', 'postcode' => '90013', 'address_1' => '100 Market Street'],
                'shipping' => ['city' => 'Pasadena', 'state' => 'CA', 'postcode' => '91101', 'address_1' => '200 Lake Avenue'],
            ],
            [
                'first'    => 'Grace',
                'last'     => 'Hopper',
                'email'    => 'grace@example.test',
                'billing'  => ['city' => 'San Diego', 'state' => 'CA', 'postcode' => '92101', 'address_1' => '300 Harbor Drive'],
                'shipping' => ['city' => 'San Diego', 'state' => 'CA', 'postcode' => '92101', 'address_1' => '300 Harbor Drive'],
            ],
        ];

        if ('small' !== $profile) {
            $customers[] = [
                'first'    => 'Katherine',
                'last'     => 'Johnson',
                'email'    => 'katherine@example.test',
                'billing'  => ['city' => 'Oakland', 'state' => 'CA', 'postcode' => '94612', 'address_1' => '400 Telegraph Avenue'],
                'shipping' => ['city' => 'Berkeley', 'state' => 'CA', 'postcode' => '94704', 'address_1' => '500 University Avenue'],
            ];
            $customers[] = [
                'first'    => 'Dorothy',
                'last'     => 'Vaughan',
                'email'    => 'dorothy@example.test',
                'billing'  => ['city' => 'Sacramento', 'state' => 'CA', 'postcode' => '95814', 'address_1' => '600 Capitol Mall'],
                'shipping' => ['city' => 'Sacramento', 'state' => 'CA', 'postcode' => '95814', 'address_1' => '600 Capitol Mall'],
            ];
            $customers[] = [
                'first'    => 'Mary',
                'last'     => 'Jackson',
                'email'    => 'mary@example.test',
                'billing'  => ['city' => 'Long Beach', 'state' => 'CA', 'postcode' => '90802', 'address_1' => '700 Ocean Boulevard'],
                'shipping' => ['city' => 'Irvine', 'state' => 'CA', 'postcode' => '92614', 'address_1' => '800 Main Street'],
            ];
        }

        $ids = [];

        foreach ($customers as $customer) {
            $user = get_user_by('email', $customer['email']);

            if ($user) {
                $user_id = (int) $user->ID;
            } else {
                $email_parts = explode('@', $customer['email']);
                $user_id = wp_insert_user(
                    [
                        'user_login' => sanitize_user($email_parts[0]),
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

            $this->update_customer_address((int) $user_id, $customer, 'billing');
            $this->update_customer_address((int) $user_id, $customer, 'shipping');
            update_user_meta((int) $user_id, self::META_KEY, '1');
            $ids[] = (int) $user_id;
        }

        return $ids;
    }

    /**
     * Update customer billing or shipping metadata.
     *
     * @param array<string, mixed> $customer Customer fixture data.
     */
    private function update_customer_address(int $user_id, array $customer, string $type): void
    {
        $address = (array) $customer[$type];

        update_user_meta($user_id, "{$type}_first_name", $customer['first']);
        update_user_meta($user_id, "{$type}_last_name", $customer['last']);
        update_user_meta($user_id, "{$type}_email", $customer['email']);
        update_user_meta($user_id, "{$type}_country", 'US');
        update_user_meta($user_id, "{$type}_state", $address['state']);
        update_user_meta($user_id, "{$type}_city", $address['city']);
        update_user_meta($user_id, "{$type}_postcode", $address['postcode']);
        update_user_meta($user_id, "{$type}_address_1", $address['address_1']);
    }

    /**
     * Seed coupons.
     *
     * @return array<int, int>
     */
    private function seed_coupons(string $profile): array
    {
        $coupons = [
            ['code' => 'WELCOME10', 'type' => 'percent', 'amount' => '10', 'minimum' => '20'],
            ['code' => 'FREESHIP', 'type' => 'fixed_cart', 'amount' => '7.50', 'free_shipping' => true],
        ];

        if ('small' !== $profile) {
            $coupons[] = ['code' => 'VIP25', 'type' => 'percent', 'amount' => '25', 'minimum' => '75', 'usage_limit' => 50];
            $coupons[] = ['code' => 'BULK15', 'type' => 'fixed_cart', 'amount' => '15', 'minimum' => '120'];
        }

        $ids = [];

        foreach ($coupons as $data) {
            $coupon_id = wc_get_coupon_id_by_code($data['code']);
            $coupon = $coupon_id ? new \WC_Coupon($coupon_id) : new \WC_Coupon();
            $coupon->set_code($data['code']);
            $coupon->set_discount_type($data['type']);
            $coupon->set_amount($data['amount']);
            $coupon->set_description('Seeded coupon for WooCommerce plugin development.');
            $coupon->set_minimum_amount((string) ($data['minimum'] ?? ''));
            $coupon->set_free_shipping(! empty($data['free_shipping']));

            if (isset($data['usage_limit'])) {
                $coupon->set_usage_limit((int) $data['usage_limit']);
            }

            $coupon->update_meta_data(self::META_KEY, '1');
            $ids[] = $coupon->save();
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
        $product_ids = $this->purchasable_product_ids($product_ids);

        if (empty($product_ids)) {
            return [];
        }

        $count = match ($profile) {
            'small' => 3,
            'performance' => $performance_count,
            default => 24,
        };

        $statuses = ['pending', 'processing', 'completed', 'on-hold', 'cancelled', 'refunded', 'failed'];
        $ids = [];

        for ($i = 1; $i <= $count; $i++) {
            $order_key = self::order_key_for_index($i);
            $existing = wc_get_orders(
                [
                    'limit'      => 1,
                    'return'     => 'ids',
                    'status'     => array_keys(wc_get_order_statuses()),
                    'meta_key'   => self::ORDER_KEY_META,
                    'meta_value' => $order_key,
                ]
            );

            $order = ! empty($existing) ? wc_get_order((int) $existing[0]) : wc_create_order();

            if (! $order instanceof \WC_Order) {
                continue;
            }

            if (empty($existing)) {
                $order->set_created_via('woo-dev-fixtures');
                $order->set_date_created(new \WC_DateTime('@' . (string) (1704067200 + ($i * DAY_IN_SECONDS))));
                $this->add_order_products($order, $product_ids, $i);
            }

            $this->ensure_order_shipping($order);
            $this->apply_order_customer($order, $customer_ids, $i);

            if (empty($existing) && 0 === $i % 4 && ! empty($coupon_ids)) {
                $order->apply_coupon(new \WC_Coupon($coupon_ids[$i % count($coupon_ids)]));
            }

            $status = $statuses[($i - 1) % count($statuses)];
            $order->set_status($status);
            $order->set_payment_method(0 === $i % 3 ? 'cheque' : 'bacs');
            $order->set_payment_method_title(0 === $i % 3 ? 'Check payments' : 'Direct bank transfer');
            $order->update_meta_data(self::META_KEY, '1');
            $order->update_meta_data(self::ORDER_KEY_META, $order_key);
            $order->calculate_taxes();
            $order->calculate_totals();
            $order->save();

            if ('refunded' === $status) {
                $this->ensure_refund($order);
            }

            $ids[] = $order->get_id();
        }

        return $ids;
    }

    /**
     * Filter product IDs down to purchasable products and variations.
     *
     * @param array<int, int> $product_ids Product IDs.
     * @return array<int, int>
     */
    private function purchasable_product_ids(array $product_ids): array
    {
        $ids = [];

        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);

            if ($product && $product->is_purchasable()) {
                $ids[] = (int) $product_id;
            }
        }

        return $ids;
    }

    /**
     * Add one or two line items to a new order.
     *
     * @param array<int, int> $product_ids Product IDs.
     */
    private function add_order_products(\WC_Order $order, array $product_ids, int $index): void
    {
        $line_count = 1 + ($index % 2);

        for ($line = 0; $line < $line_count; $line++) {
            $product = wc_get_product($product_ids[($index + $line) % count($product_ids)]);

            if ($product) {
                $order->add_product($product, 1 + (($index + $line) % 3));
            }
        }
    }

    /**
     * Ensure each order has a shipping line.
     */
    private function ensure_order_shipping(\WC_Order $order): void
    {
        if (! empty($order->get_items('shipping'))) {
            return;
        }

        $shipping = new \WC_Order_Item_Shipping();
        $shipping->set_method_title('Flat rate');
        $shipping->set_method_id('flat_rate');
        $shipping->set_total('7.50');
        $order->add_item($shipping);
    }

    /**
     * Apply customer or deterministic guest addresses to an order.
     *
     * @param array<int, int> $customer_ids Customer IDs.
     */
    private function apply_order_customer(\WC_Order $order, array $customer_ids, int $index): void
    {
        if (empty($customer_ids) || 0 === $index % 5) {
            $guest = $this->guest_address($index);
            $order->set_customer_id(0);
            $this->apply_order_address($order, $guest, 'billing');
            $this->apply_order_address($order, $guest, 'shipping');
            return;
        }

        $customer_id = $customer_ids[($index - 1) % count($customer_ids)];
        $order->set_customer_id($customer_id);
        $this->apply_order_address($order, $this->customer_address($customer_id, 'billing'), 'billing');
        $this->apply_order_address($order, $this->customer_address($customer_id, 'shipping'), 'shipping');
    }

    /**
     * Deterministic guest checkout address.
     *
     * @return array<string, string>
     */
    private function guest_address(int $index): array
    {
        $guests = [
            ['first_name' => 'Guest', 'last_name' => 'Buyer', 'email' => 'guest-buyer@example.test', 'address_1' => '900 Sunset Boulevard', 'city' => 'Los Angeles', 'state' => 'CA', 'postcode' => '90028', 'country' => 'US'],
            ['first_name' => 'Sample', 'last_name' => 'Shopper', 'email' => 'sample-shopper@example.test', 'address_1' => '901 Mission Street', 'city' => 'San Francisco', 'state' => 'CA', 'postcode' => '94103', 'country' => 'US'],
        ];

        return $guests[$index % count($guests)];
    }

    /**
     * Read a customer address from user metadata.
     *
     * @return array<string, string>
     */
    private function customer_address(int $customer_id, string $type): array
    {
        $address = [];

        foreach (['first_name', 'last_name', 'email', 'address_1', 'city', 'state', 'postcode', 'country'] as $field) {
            $address[$field] = (string) get_user_meta($customer_id, "{$type}_{$field}", true);
        }

        return $address;
    }

    /**
     * Apply a billing or shipping address to an order.
     *
     * @param array<string, string> $address Address data.
     */
    private function apply_order_address(\WC_Order $order, array $address, string $type): void
    {
        $methods = [
            'first_name' => sprintf('set_%s_first_name', $type),
            'last_name'  => sprintf('set_%s_last_name', $type),
            'address_1'  => sprintf('set_%s_address_1', $type),
            'city'       => sprintf('set_%s_city', $type),
            'state'      => sprintf('set_%s_state', $type),
            'postcode'   => sprintf('set_%s_postcode', $type),
            'country'    => sprintf('set_%s_country', $type),
        ];

        if ('billing' === $type) {
            $methods['email'] = 'set_billing_email';
        }

        foreach ($methods as $field => $method) {
            if (method_exists($order, $method)) {
                $order->{$method}($address[$field] ?? '');
            }
        }
    }

    /**
     * Create a full refund for refunded fixture orders.
     */
    private function ensure_refund(\WC_Order $order): void
    {
        if (! empty($order->get_refunds())) {
            return;
        }

        $amount = (float) $order->get_total();

        if ($amount <= 0) {
            return;
        }

        $refund = wc_create_refund(
            [
                'amount'         => wc_format_decimal($amount, wc_get_price_decimals()),
                'reason'         => 'Seeded full refund',
                'order_id'       => $order->get_id(),
                'refund_payment' => false,
                'restock_items'  => false,
            ]
        );

        if ($refund instanceof \WC_Order_Refund) {
            $refund->update_meta_data(self::META_KEY, '1');
            $refund->save();
        }

        $order->set_status('refunded');
        $order->save();
    }

    /**
     * Apply deterministic post-order inventory states.
     */
    private function apply_inventory_movements(string $profile): void
    {
        if ('performance' === $profile) {
            return;
        }

        $stock_by_sku = [
            'WDF-TEE-BASIC'    => 62,
            'WDF-MUG'          => 103,
            'WDF-STAND'        => 4,
            'WDF-STICKERS'     => 0,
            'WDF-NOTEBOOK'     => 0,
            'WDF-HOODIE-L-BLU' => 2,
        ];

        foreach ($stock_by_sku as $sku => $stock) {
            $product_id = wc_get_product_id_by_sku($sku);
            $product = $product_id ? wc_get_product($product_id) : null;

            if (! $product || ! $product->managing_stock()) {
                continue;
            }

            $product->set_stock_quantity($stock);
            $product->set_stock_status($this->stock_status($stock, $product->get_backorders()));
            $product->save();
        }
    }
}
