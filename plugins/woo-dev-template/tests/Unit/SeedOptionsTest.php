<?php
declare(strict_types=1);

namespace WooDevTemplate\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WooDevFixtures\Cli\SeedOptions;
use WooDevFixtures\Seed\StoreSeeder;

final class SeedOptionsTest extends TestCase
{
    public function test_defaults_are_rich_and_sized_for_performance_overrides(): void
    {
        self::assertSame(
            [
                'profile'  => 'rich',
                'reset'    => false,
                'products' => 250,
                'orders'   => 500,
            ],
            SeedOptions::from_assoc_args([])
        );
    }

    public function test_options_are_sanitized_and_counts_are_positive(): void
    {
        self::assertSame(
            [
                'profile'  => 'performance',
                'reset'    => true,
                'products' => 1,
                'orders'   => 20,
            ],
            SeedOptions::from_assoc_args(
                [
                    'profile'  => 'Performance!',
                    'reset'    => true,
                    'products' => '0',
                    'orders'   => '-20',
                ]
            )
        );
    }

    public function test_invalid_profile_fails_fast(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile must be one of: small, rich, performance.');

        SeedOptions::from_assoc_args(['profile' => 'demo']);
    }

    public function test_seeder_identity_keys_are_stable(): void
    {
        self::assertSame('_woo_dev_fixtures_seeded', StoreSeeder::seeded_meta_key());
        self::assertSame('wdf-order-0001', StoreSeeder::order_key_for_index(1));
        self::assertSame('wdf-order-0042', StoreSeeder::order_key_for_index(42));
    }
}
