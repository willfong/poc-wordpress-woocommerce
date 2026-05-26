# WordPress + WooCommerce Plugin Dev Template

Template repository for starting a local WordPress + WooCommerce plugin development project with one command.

## Requirements

- Docker with Compose v2
- `make`

No host PHP, Composer, WP-CLI, MySQL, or MariaDB installation is required.

## Quick Start

Initialize the scaffold for a new one-plugin repo:

```sh
make init-plugin SLUG=acme-shop NAME="Acme Shop" NAMESPACE=AcmeShop AUTHOR="Acme Inc"
```

Then start the local stack:

```sh
make dev-up
```

The first run creates `.env` with a Compose project name derived from the plugin/repo, builds the WordPress PHP image, starts MariaDB, WordPress, WP-CLI, Mailpit, and Adminer, installs Composer dependencies for the scaffold plugin, installs WordPress, installs WooCommerce, activates the local plugin, and seeds sample store data.

Default local services:

- WordPress: <http://localhost:8080>
- WordPress admin: <http://localhost:8080/wp-admin>
- Adminer: <http://localhost:8081>
- Mailpit: <http://localhost:8025>

Default admin login:

- Username: `admin`
- Password: `password`

Change defaults in `.env` after the first run.

## Common Commands

```sh
make dev-up
make dev-init
make dev-reset
make dev-down
make dev-logs
make wp ARGS="plugin list"
make composer ARGS="install"
make seed PROFILE=rich
make seed PROFILE=small RESET=1
make test
make lint
make stan
make smoke
make quality
make plugin-info
```

`make dev-init` is idempotent. It updates configuration and creates missing fixtures without clearing local data.

`make dev-reset` is destructive. It removes Docker volumes for the local database and WordPress core, then bootstraps from scratch.

## Local Fixture Data

The local development environment mounts a dev-only mu-plugin that registers a WP-CLI command:

```sh
make seed PROFILE=rich
make seed PROFILE=small RESET=1
make wp ARGS="woo-dev seed --profile=performance --products=500 --orders=1000"
make wp ARGS="woo-dev seed --profile=rich --reset --yes"
```

The default `rich` profile creates deterministic WooCommerce data:

- Product categories
- Simple, variable, virtual, downloadable, sale-priced, in-stock, low-stock, out-of-stock, and backordered products
- Product attributes, variations, dimensions, and weights
- Customers and guest orders with billing/shipping variation
- Coupons with percentage, fixed-cart, minimum spend, and free-shipping behavior
- Orders across pending, processing, completed, on-hold, cancelled, refunded, and failed statuses
- Shipping lines, tax lines, refunds, and deterministic inventory states

Seeded records are marked with metadata so fixture generation can be rerun without uncontrolled duplication. Use `--reset --yes` to remove and rebuild seeded records.

## Plugin Scaffold

The local plugin lives in `plugins/woo-dev-template`.

It includes:

- Composer PSR-4 autoloading
- A main plugin bootstrap
- A sample admin settings page
- A sample WooCommerce order hook
- PHPUnit, PHPCS/WPCS, and PHPStan configuration

Fixture commands live under `dev/mu-plugins` so the production plugin scaffold stays clean.

The scaffold intentionally starts PHP-first. Add Node, block tooling, or storefront build steps only when a plugin needs them.

## Version Defaults

Pinned defaults are kept in `.env.example`:

- WordPress: `6.9.4`
- PHP runtime: `8.3`
- WooCommerce: `10.7.0`
- MariaDB: `11.8.6`

CI runs plugin quality checks on PHP 8.3 and 8.4, and Docker smoke checks across WordPress 6.9.4 and 7.0 with WooCommerce pinned to 10.7.0. Update `.env.example`, rebuild with `make dev-reset`, and adjust CI when intentionally changing baseline versions.
