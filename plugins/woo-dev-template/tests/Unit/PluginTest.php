<?php
declare(strict_types=1);

namespace WooDevTemplate\Tests\Unit;

use Brain\Monkey;
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
}

