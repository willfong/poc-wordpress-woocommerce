#!/bin/sh
set -eu

RESET=0
if [ "${1:-}" = "--reset" ]; then
  RESET=1
fi

wait_for_wordpress() {
  tries=0
  until wp core is-installed >/dev/null 2>&1 || wp core version >/dev/null 2>&1; do
    tries=$((tries + 1))
    if [ "$tries" -gt 60 ]; then
      echo "Timed out waiting for WordPress files."
      exit 1
    fi
    sleep 2
  done
}

wait_for_wordpress

if [ "$RESET" = "1" ] && wp core is-installed >/dev/null 2>&1; then
  wp db reset --yes
fi

if ! wp core is-installed >/dev/null 2>&1; then
  wp core install \
    --url="${WORDPRESS_URL}" \
    --title="${WORDPRESS_TITLE}" \
    --admin_user="${WORDPRESS_ADMIN_USER}" \
    --admin_password="${WORDPRESS_ADMIN_PASSWORD}" \
    --admin_email="${WORDPRESS_ADMIN_EMAIL}" \
    --locale="${WORDPRESS_LOCALE}" \
    --skip-email
fi

wp option update home "${WORDPRESS_URL}"
wp option update siteurl "${WORDPRESS_URL}"
wp rewrite structure '/%postname%/' --hard

if ! wp plugin is-installed woocommerce >/dev/null 2>&1; then
  if [ "${WOOCOMMERCE_VERSION}" = "latest" ]; then
    wp plugin install woocommerce
  else
    wp plugin install woocommerce --version="${WOOCOMMERCE_VERSION}"
  fi
fi

wp plugin activate woocommerce
wp plugin activate "${PLUGIN_SLUG}"

wp option update woocommerce_allow_tracking no
wp option update woocommerce_onboarding_profile '{"skipped":true}' --format=json
wp option update woocommerce_store_address '123 Template Street'
wp option update woocommerce_store_city 'Portland'
wp option update woocommerce_default_country 'US:OR'
wp option update woocommerce_store_postcode '97205'
wp option update woocommerce_currency 'USD'
wp option update woocommerce_price_decimal_sep '.'
wp option update woocommerce_price_thousand_sep ','
wp option update woocommerce_dimension_unit 'in'
wp option update woocommerce_weight_unit 'lbs'

wp wc tool run install_pages --user="${WORDPRESS_ADMIN_USER}" >/dev/null 2>&1 || true
wp rewrite flush --hard

if [ "$RESET" = "1" ]; then
  wp woo-dev seed --profile="${FIXTURE_PROFILE}" --reset --yes
else
  wp woo-dev seed --profile="${FIXTURE_PROFILE}"
fi

echo "WordPress: ${WORDPRESS_URL}"
echo "Admin: ${WORDPRESS_URL}/wp-admin"
echo "Mailpit: http://localhost:${MAILPIT_WEB_PORT:-8025}"
echo "Adminer: http://localhost:${ADMINER_PORT:-8081}"
