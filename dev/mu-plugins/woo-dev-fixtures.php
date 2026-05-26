<?php
/**
 * Plugin Name: Woo Dev Fixture Tools
 * Description: Local-only WP-CLI fixture commands for the development environment.
 * Version: 0.1.0
 * Author: Template Author
 *
 * @package WooDevFixtures
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/woo-dev-fixtures/src/Cli/SeedOptions.php';
require_once __DIR__ . '/woo-dev-fixtures/src/Cli/SeedCommand.php';
require_once __DIR__ . '/woo-dev-fixtures/src/Seed/StoreSeeder.php';

if (defined('WP_CLI') && WP_CLI) {
    \WooDevFixtures\Cli\SeedCommand::register();
}
