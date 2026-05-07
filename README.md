# WordPress + WooCommerce Plugin Dev Template

Template repository for starting a local WordPress + WooCommerce plugin development project with one command.

## Requirements

- Docker with Compose v2
- `make`

No host PHP, Composer, WP-CLI, MySQL, or MariaDB installation is required.

## Quick Start

```sh
make dev-up
```

The first run creates `.env`, builds the WordPress PHP image, starts MariaDB, WordPress, WP-CLI, Mailpit, and Adminer, installs Composer dependencies for the scaffold plugin, installs WordPress, installs WooCommerce, activates the local plugin, and seeds sample store data.

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
make test
make lint
make stan
make smoke
```

`make dev-init` is idempotent. It updates configuration and creates missing fixtures without clearing local data.

`make dev-reset` is destructive. It removes Docker volumes for the local database and WordPress core, then bootstraps from scratch.

## Fixture Data

The scaffold plugin registers a WP-CLI command:

```sh
make wp ARGS="woo-dev seed --profile=rich"
make wp ARGS="woo-dev seed --profile=small"
make wp ARGS="woo-dev seed --profile=performance --products=500 --orders=1000"
make wp ARGS="woo-dev seed --profile=rich --reset --yes"
```

The default `rich` profile creates deterministic WooCommerce data:

- Product categories
- Simple, variable, virtual, downloadable, in-stock, low-stock, and out-of-stock products
- Customers with billing data
- Coupons
- Orders across common statuses
- Basic tax and flat-rate shipping settings

Seeded records are marked with metadata so fixture generation can be rerun without uncontrolled duplication. Use `--reset --yes` to remove and rebuild seeded records.

## Plugin Scaffold

The local plugin lives in `plugins/woo-dev-template`.

It includes:

- Composer PSR-4 autoloading
- A main plugin bootstrap
- A sample admin settings page
- A sample WooCommerce order hook
- A WP-CLI fixture command
- PHPUnit, PHPCS/WPCS, and PHPStan configuration

The scaffold intentionally starts PHP-first. Add Node, block tooling, or storefront build steps only when a plugin needs them.

## Version Defaults

Pinned defaults are kept in `.env.example`:

- WordPress: `6.9.4`
- PHP runtime: `8.3`
- WooCommerce: `10.7.0`
- MariaDB: `11.8.6`

Update `.env.example`, rebuild with `make dev-reset`, and adjust CI when intentionally changing baseline versions.

