SHELL := /bin/sh
COMPOSE := docker compose
WP := $(COMPOSE) run --rm wp-cli
PLUGIN_DIR := plugins/woo-dev-template

.DEFAULT_GOAL := help

.PHONY: help dev-up dev-init dev-reset dev-down dev-logs wp composer test lint stan smoke env

help:
	@printf '%s\n' \
		'WordPress + WooCommerce plugin development template' \
		'' \
		'Targets:' \
		'  make dev-up      Start services and run first-time bootstrap' \
		'  make dev-init    Idempotently install/configure WordPress and seed data' \
		'  make dev-reset   Destroy local DB/uploads state and bootstrap again' \
		'  make dev-down    Stop services' \
		'  make dev-logs    Tail service logs' \
		'  make wp ARGS="..."       Run WP-CLI' \
		'  make composer ARGS="..." Run Composer in the WordPress container' \
		'  make test       Run PHPUnit' \
		'  make lint       Run PHPCS' \
		'  make stan       Run PHPStan' \
		'  make smoke      Run a local smoke check'

env:
	@test -f .env || cp .env.example .env

dev-up: env
	$(COMPOSE) up -d --build
	$(MAKE) dev-init

dev-init: env
	$(COMPOSE) up -d --build mariadb wordpress mailpit adminer
	$(COMPOSE) run --rm wordpress composer --working-dir=/var/www/html/wp-content/plugins/woo-dev-template install
	$(COMPOSE) run --rm wp-cli sh /workspace/bin/dev-init.sh

dev-reset: env
	$(COMPOSE) down -v
	$(COMPOSE) up -d --build
	$(COMPOSE) run --rm wordpress composer --working-dir=/var/www/html/wp-content/plugins/woo-dev-template install
	$(COMPOSE) run --rm wp-cli sh /workspace/bin/dev-init.sh --reset

dev-down:
	$(COMPOSE) down

dev-logs:
	$(COMPOSE) logs -f

wp: env
	$(WP) $(ARGS)

composer: env
	$(COMPOSE) run --rm wordpress composer --working-dir=/var/www/html/wp-content/plugins/woo-dev-template $(ARGS)

test: env
	$(COMPOSE) run --rm wordpress composer --working-dir=/var/www/html/wp-content/plugins/woo-dev-template test

lint: env
	$(COMPOSE) run --rm wordpress composer --working-dir=/var/www/html/wp-content/plugins/woo-dev-template lint

stan: env
	$(COMPOSE) run --rm wordpress composer --working-dir=/var/www/html/wp-content/plugins/woo-dev-template stan

smoke: env
	$(COMPOSE) run --rm wp-cli sh /workspace/bin/smoke.sh
