<?php
/**
 * Seed option parsing.
 *
 * @package WooDevFixtures\Cli
 */

declare(strict_types=1);

namespace WooDevFixtures\Cli;

use InvalidArgumentException;

/**
 * Parses and validates WP-CLI seed options without depending on WordPress globals.
 */
final class SeedOptions
{
    /**
     * Supported fixture profile names.
     *
     * @var array<int, string>
     */
    public const PROFILES = ['small', 'rich', 'performance'];

    /**
     * Parse associative WP-CLI args into seeder options.
     *
     * @param array<string, mixed> $assoc_args Raw associative args.
     * @return array{profile: string, reset: bool, products: int, orders: int}
     */
    public static function from_assoc_args(array $assoc_args): array
    {
        $profile = self::sanitize_key((string) ($assoc_args['profile'] ?? 'rich'));

        if (! in_array($profile, self::PROFILES, true)) {
            throw new InvalidArgumentException('Profile must be one of: small, rich, performance.');
        }

        return [
            'profile'  => $profile,
            'reset'    => array_key_exists('reset', $assoc_args),
            'products' => self::positive_int($assoc_args['products'] ?? null, 250),
            'orders'   => self::positive_int($assoc_args['orders'] ?? null, 500),
        ];
    }

    /**
     * WordPress-like key sanitization for profile names.
     */
    private static function sanitize_key(string $key): string
    {
        $sanitized = strtolower($key);
        $sanitized = preg_replace('/[^a-z0-9_\-]/', '', $sanitized);

        return is_string($sanitized) ? $sanitized : '';
    }

    /**
     * Coerce a CLI count into a positive integer.
     */
    private static function positive_int(mixed $value, int $default): int
    {
        if (null === $value || '' === $value) {
            return $default;
        }

        return max(1, abs((int) $value));
    }
}
