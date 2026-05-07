#!/bin/sh
set -eu

wp core is-installed
wp plugin is-active woocommerce
wp plugin is-active "${PLUGIN_SLUG:-woo-dev-template}"

products="$(wp post list --post_type=product --format=count)"
orders="$(wp eval 'echo class_exists("WC_Order_Query") ? count( wc_get_orders( [ "limit" => -1, "return" => "ids" ] ) ) : 0;')"

if [ "$products" -lt 1 ]; then
  echo "Expected seeded products."
  exit 1
fi

echo "Smoke OK: ${products} products, ${orders} orders."
