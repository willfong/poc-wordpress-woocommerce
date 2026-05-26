SHELL := /bin/sh
COMPOSE := docker compose
WP := $(COMPOSE) run --rm wp-cli
PLUGIN_SLUG ?= woo-dev-template
PLUGIN_DIR := plugins/$(PLUGIN_SLUG)
PROFILE ?= rich
RESET ?= 0
SEED_RESET_ARGS := $(if $(filter 1 yes true,$(RESET)),--reset --yes,)

.DEFAULT_GOAL := help

.PHONY: help init-plugin dev-up dev-init dev-reset dev-down dev-logs wp composer test lint stan smoke seed quality plugin-info env

help:
	@printf '%s\n' \
		'WordPress + WooCommerce plugin development template' \
		'' \
		'Targets:' \
		'  make init-plugin SLUG=... NAME=... NAMESPACE=... AUTHOR=...' \
		'                   Rename the scaffold for a new plugin repo' \
		'  make dev-up      Start services and run first-time bootstrap' \
		'  make dev-init    Idempotently install/configure WordPress and seed data' \
		'  make dev-reset   Destroy local DB/uploads state and bootstrap again' \
		'  make dev-down    Stop services' \
		'  make dev-logs    Tail service logs' \
		'  make wp ARGS="..."       Run WP-CLI' \
		'  make composer ARGS="..." Run Composer in the WordPress container' \
		'  make seed PROFILE=rich   Seed fixture data; add RESET=1 to rebuild' \
		'  make test       Run PHPUnit' \
		'  make lint       Run PHPCS' \
		'  make stan       Run PHPStan' \
		'  make smoke      Run a local smoke check' \
		'  make quality    Run lint, static analysis, tests, and smoke' \
		'  make plugin-info Print current plugin paths and identifiers'

env:
	@sh bin/create-env.sh "" "$(PLUGIN_SLUG)"

init-plugin:
	@sh bin/init-plugin.sh "$(SLUG)" "$(NAME)" "$(NAMESPACE)" "$(AUTHOR)"

dev-up: env
	$(COMPOSE) up -d --build
	$(MAKE) dev-init

dev-init: env
	$(COMPOSE) up -d --build mariadb wordpress mailpit adminer
	$(COMPOSE) run --rm wordpress composer --working-dir=/var/www/html/wp-content/$(PLUGIN_DIR) install
	$(COMPOSE) run --rm wp-cli sh /workspace/bin/dev-init.sh

dev-reset: env
	$(COMPOSE) down -v
	$(COMPOSE) up -d --build
	$(COMPOSE) run --rm wordpress composer --working-dir=/var/www/html/wp-content/$(PLUGIN_DIR) install
	$(COMPOSE) run --rm wp-cli sh /workspace/bin/dev-init.sh --reset

dev-down:
	$(COMPOSE) down

dev-logs:
	$(COMPOSE) logs -f

wp: env
	$(WP) $(ARGS)

composer: env
	$(COMPOSE) run --rm wordpress composer --working-dir=/var/www/html/wp-content/$(PLUGIN_DIR) $(ARGS)

test: env
	$(COMPOSE) run --rm wordpress composer --working-dir=/var/www/html/wp-content/$(PLUGIN_DIR) test

lint: env
	$(COMPOSE) run --rm wordpress composer --working-dir=/var/www/html/wp-content/$(PLUGIN_DIR) lint

stan: env
	$(COMPOSE) run --rm wordpress composer --working-dir=/var/www/html/wp-content/$(PLUGIN_DIR) stan

smoke: env
	$(COMPOSE) run --rm wp-cli sh /workspace/bin/smoke.sh

seed: env
	$(WP) woo-dev seed --profile="$(PROFILE)" $(SEED_RESET_ARGS)

quality: lint stan test smoke

plugin-info: env
	@printf 'Plugin slug: %s\n' "$(PLUGIN_SLUG)"
	@printf 'Plugin directory: %s\n' "$(PLUGIN_DIR)"
	@printf 'Fixture profile: %s\n' "$(PROFILE)"
