<?php
/**
 * WP-CLI seed command registration.
 *
 * @package WooDevFixtures\Cli
 */

declare(strict_types=1);

namespace WooDevFixtures\Cli;

use InvalidArgumentException;
use WooDevFixtures\Seed\StoreSeeder;

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

        try {
            $options = SeedOptions::from_assoc_args($assoc_args);
        } catch (InvalidArgumentException $exception) {
            \WP_CLI::error($exception->getMessage());
        }

        if ($options['reset'] && ! isset($assoc_args['yes'])) {
            \WP_CLI::confirm('Delete all records previously created by the Woo Dev fixture seeder?');
        }

        $summary = (new StoreSeeder())->seed($options);

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
