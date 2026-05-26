#!/bin/sh
set -eu

wp core is-installed
wp plugin is-active woocommerce
wp plugin is-active "${PLUGIN_SLUG:-woo-dev-template}"

wp eval '
$fail = static function ( string $message ): void {
    fwrite( STDERR, $message . PHP_EOL );
    exit( 1 );
};

$products = wc_get_products( [ "limit" => -1, "return" => "ids", "status" => "publish" ] );
$variations = wc_get_products( [ "limit" => -1, "return" => "ids", "type" => "variation", "status" => "publish" ] );
$customers = get_users( [ "role__in" => [ "customer" ], "meta_key" => "_woo_dev_fixtures_seeded", "fields" => "ID" ] );
$coupons = get_posts( [ "post_type" => "shop_coupon", "post_status" => "any", "posts_per_page" => -1, "fields" => "ids", "meta_key" => "_woo_dev_fixtures_seeded", "meta_value" => "1" ] );
$orders = wc_get_orders( [ "limit" => -1, "return" => "objects", "status" => array_keys( wc_get_order_statuses() ), "meta_key" => "_woo_dev_fixtures_seeded", "meta_value" => "1" ] );

if ( count( $products ) < 8 ) {
    $fail( "Expected rich seeded products." );
}

if ( count( $variations ) < 3 ) {
    $fail( "Expected seeded product variations." );
}

if ( count( $customers ) < 4 ) {
    $fail( "Expected seeded customers." );
}

if ( count( $coupons ) < 3 ) {
    $fail( "Expected seeded coupons." );
}

if ( count( $orders ) < 12 ) {
    $fail( "Expected seeded orders." );
}

$statuses = [];
$has_refund = false;
$has_shipping = false;
$has_tax = false;
$has_guest = false;

foreach ( $orders as $order ) {
    $statuses[ $order->get_status() ] = true;
    $has_refund = $has_refund || ! empty( $order->get_refunds() );
    $has_shipping = $has_shipping || ! empty( $order->get_items( "shipping" ) );
    $has_tax = $has_tax || ! empty( $order->get_items( "tax" ) );
    $has_guest = $has_guest || 0 === (int) $order->get_customer_id();
}

foreach ( [ "pending", "processing", "completed", "refunded", "failed" ] as $status ) {
    if ( empty( $statuses[ $status ] ) ) {
        $fail( "Expected seeded order status: {$status}." );
    }
}

if ( ! $has_refund ) {
    $fail( "Expected at least one seeded refund." );
}

if ( ! $has_shipping ) {
    $fail( "Expected seeded shipping lines." );
}

if ( ! $has_tax ) {
    $fail( "Expected seeded tax lines." );
}

if ( ! $has_guest ) {
    $fail( "Expected seeded guest orders." );
}

$stock_statuses = [];

foreach ( $products as $product_id ) {
    $product = wc_get_product( $product_id );

    if ( $product ) {
        $stock_statuses[ $product->get_stock_status() ] = true;
    }
}

foreach ( [ "instock", "outofstock", "onbackorder" ] as $stock_status ) {
    if ( empty( $stock_statuses[ $stock_status ] ) ) {
        $fail( "Expected stock status: {$stock_status}." );
    }
}

printf(
    "Smoke OK: %d products, %d variations, %d customers, %d coupons, %d orders.\n",
    count( $products ),
    count( $variations ),
    count( $customers ),
    count( $coupons ),
    count( $orders )
);
'
