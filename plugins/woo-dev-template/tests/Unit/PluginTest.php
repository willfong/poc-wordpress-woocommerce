<?php
declare(strict_types=1);

namespace WooDevTemplate\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WooDevTemplate\Plugin;

final class PluginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_plugin_instance_is_singleton(): void
    {
        self::assertSame(Plugin::instance(), Plugin::instance());
    }

    public function test_activation_stores_plugin_version(): void
    {
        Functions\expect('update_option')
            ->once()
            ->with('woo_dev_template_version', WOO_DEV_TEMPLATE_VERSION)
            ->andReturn(true);

        Plugin::activate();
    }

    public function test_deactivation_clears_scheduled_hook(): void
    {
        Functions\expect('wp_clear_scheduled_hook')
            ->once()
            ->with('woo_dev_template_hourly')
            ->andReturn(true);

        Plugin::deactivate();
    }
}
