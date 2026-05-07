<?php
/**
 * WP-CLI seed command registration.
 *
 * @package WooDevTemplate\Cli
 */

declare(strict_types=1);

namespace WooDevTemplate\Cli;

use WooDevTemplate\Seed\StoreSeeder;

/**
 * Registers `wp woo-dev seed`.
 */
final class SeedCommand
{
    /**
     * Register WP-CLI commands.
     */
    public static function register(): void
    {
        \WP_CLI::add_command('woo-dev seed', [self::class, 'seed']);
    }

    /**
     * Seed WooCommerce development data.
     *
     * ## OPTIONS
     *
     * [--profile=<profile>]
     * : Fixture profile. Supports rich, small, and performance.
     *
     * [--products=<count>]
     * : Product count for the performance profile.
     *
     * [--orders=<count>]
     * : Order count for the performance profile.
     *
     * [--reset]
     * : Delete previously seeded data before creating fixtures.
     *
     * [--yes]
     * : Confirm destructive reset.
     *
     * @param array<int, string>   $args Positional args.
     * @param array<string, mixed> $assoc_args Associative args.
     */
    public static function seed(array $args, array $assoc_args): void
    {
        unset($args);

        if (! class_exists('WooCommerce')) {
            \WP_CLI::error('WooCommerce must be active before seeding.');
        }

        $profile = isset($assoc_args['profile']) ? sanitize_key((string) $assoc_args['profile']) : 'rich';

        if (! in_array($profile, ['small', 'rich', 'performance'], true)) {
            \WP_CLI::error('Profile must be one of: small, rich, performance.');
        }

        $reset = isset($assoc_args['reset']);

        if ($reset && ! isset($assoc_args['yes'])) {
            \WP_CLI::confirm('Delete all records previously created by the Woo Dev Template seeder?');
        }

        $products = isset($assoc_args['products']) ? max(1, absint($assoc_args['products'])) : 250;
        $orders   = isset($assoc_args['orders']) ? max(1, absint($assoc_args['orders'])) : 500;

        $summary = (new StoreSeeder())->seed(
            [
                'profile'  => $profile,
                'reset'    => $reset,
                'products' => $products,
                'orders'   => $orders,
            ]
        );

        \WP_CLI::success(
            sprintf(
                'Seeded %d categories, %d products, %d customers, %d coupons, and %d orders.',
                $summary['categories'],
                $summary['products'],
                $summary['customers'],
                $summary['coupons'],
                $summary['orders']
            )
        );
    }
}

