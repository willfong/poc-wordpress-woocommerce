<?php
declare(strict_types=1);

if (! defined('WOO_DEV_TEMPLATE_VERSION')) {
    define('WOO_DEV_TEMPLATE_VERSION', '0.1.0');
}

if (! defined('WOO_DEV_TEMPLATE_PATH')) {
    define('WOO_DEV_TEMPLATE_PATH', __DIR__ . '/../../');
}

if (! class_exists('WooCommerce')) {
    class WooCommerce
    {
    }
}

if (! class_exists('WP_CLI')) {
    class WP_CLI
    {
        public static function add_command(string $name, callable|array $callable): void
        {
        }

        public static function error(string $message): void
        {
        }

        public static function confirm(string $message): void
        {
        }

        public static function success(string $message): void
        {
        }
    }
}

if (! class_exists('WC_Product')) {
    class WC_Product
    {
        public function save(): int
        {
            return 1;
        }
    }
}

if (! class_exists('WC_Product_Simple')) {
    class WC_Product_Simple extends WC_Product
    {
        public function set_name(string $value): void {}
        public function set_sku(string $value): void {}
        public function set_regular_price(string $value): void {}
        public function set_description(string $value): void {}
        public function set_short_description(string $value): void {}
        public function set_status(string $value): void {}
        public function set_catalog_visibility(string $value): void {}
        public function set_virtual(bool $value): void {}
        public function set_downloadable(bool $value): void {}
        public function set_manage_stock(bool $value): void {}
        public function set_stock_status(string $value): void {}
        public function set_stock_quantity(int $value): void {}
        /** @param array<int, int> $value */
        public function set_category_ids(array $value): void {}
    }
}

if (! class_exists('WC_Product_Variable')) {
    class WC_Product_Variable extends WC_Product_Simple
    {
        /** @param array<int, WC_Product_Attribute> $value */
        public function set_attributes(array $value): void {}
        public static function sync(int $product_id): void {}
    }
}

if (! class_exists('WC_Product_Variation')) {
    class WC_Product_Variation extends WC_Product_Simple
    {
        public function set_parent_id(int $value): void {}
        /** @param array<string, string> $value */
        public function set_attributes(array $value): void {}
    }
}

if (! class_exists('WC_Product_Attribute')) {
    class WC_Product_Attribute
    {
        public function set_id(int $value): void {}
        public function set_name(string $value): void {}
        /** @param array<int, string> $value */
        public function set_options(array $value): void {}
        public function set_visible(bool $value): void {}
        public function set_variation(bool $value): void {}
    }
}

if (! class_exists('WC_Order')) {
    class WC_Order
    {
        public function add_product(mixed $product, int $quantity): void {}
        public function apply_coupon(WC_Coupon $coupon): void {}
        public function set_customer_id(int $value): void {}
        public function set_status(string $value): void {}
        public function set_billing_first_name(string $value): void {}
        public function set_billing_last_name(string $value): void {}
        public function set_billing_email(string $value): void {}
        public function set_billing_address_1(string $value): void {}
        public function set_billing_city(string $value): void {}
        public function set_billing_state(string $value): void {}
        public function set_billing_postcode(string $value): void {}
        public function set_billing_country(string $value): void {}
        public function set_payment_method(string $value): void {}
        public function set_payment_method_title(string $value): void {}
        public function calculate_totals(): void {}
        public function save(): int { return 1; }
        public function get_id(): int { return 1; }
        public function delete(bool $force_delete = false): bool { return true; }
        public function add_order_note(string $note): void {}
    }
}

if (! class_exists('WC_Coupon')) {
    class WC_Coupon
    {
        public function __construct(int|string $id = 0) {}
        public function set_code(string $value): void {}
        public function set_discount_type(string $value): void {}
        public function set_amount(string $value): void {}
        public function set_description(string $value): void {}
        public function save(): int { return 1; }
    }
}

if (! class_exists('WC_Shipping_Zone')) {
    class WC_Shipping_Zone
    {
        public function __construct(int $id = 0) {}
        /** @return array<int, object{id: string}> */
        public function get_shipping_methods(): array { return []; }
        public function add_shipping_method(string $type): int { return 1; }
    }
}

if (! class_exists('WC_Shipping_Zones')) {
    class WC_Shipping_Zones
    {
        public static function get_shipping_method(int $instance_id): ?object
        {
            return new class {
                /** @param array<string, string> $data */
                public function set_post_data(array $data): void {}
                public function process_admin_options(): void {}
            };
        }
    }
}

if (! class_exists('WC_Tax')) {
    class WC_Tax
    {
        /** @param array<string, string> $args */
        public static function find_rates(array $args): array { return []; }
        /** @param array<string, mixed> $args */
        public static function _insert_tax_rate(array $args): int { return 1; }
    }
}

if (! function_exists('wc_get_product')) {
    function wc_get_product(int $product_id): WC_Product|false
    {
        return new WC_Product_Simple();
    }
}

if (! function_exists('wc_get_product_id_by_sku')) {
    function wc_get_product_id_by_sku(string $sku): int
    {
        return 0;
    }
}

if (! function_exists('wc_get_coupon_id_by_code')) {
    function wc_get_coupon_id_by_code(string $code): int
    {
        return 0;
    }
}

if (! function_exists('wc_create_order')) {
    function wc_create_order(): WC_Order
    {
        return new WC_Order();
    }
}

if (! function_exists('wc_get_order')) {
    function wc_get_order(int $order_id): WC_Order|false
    {
        return new WC_Order();
    }
}

if (! function_exists('wc_get_orders')) {
    /** @param array<string, mixed> $args */
    function wc_get_orders(array $args): array
    {
        return [];
    }
}

if (! function_exists('wc_get_order_statuses')) {
    function wc_get_order_statuses(): array
    {
        return ['wc-pending' => 'Pending'];
    }
}

