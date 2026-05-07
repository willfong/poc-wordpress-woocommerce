<?php
/**
 * Example settings page.
 *
 * @package WooDevTemplate\Admin
 */

declare(strict_types=1);

namespace WooDevTemplate\Admin;

/**
 * Adds a small plugin settings page for scaffold validation.
 */
final class SettingsPage
{
    private const OPTION_NAME = 'woo_dev_template_settings';

    /**
     * Register WordPress admin hooks.
     */
    public function register(): void
    {
        add_action('admin_menu', [$this, 'add_page']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    /**
     * Add the settings page.
     */
    public function add_page(): void
    {
        add_options_page(
            __('Woo Dev Template', 'woo-dev-template'),
            __('Woo Dev Template', 'woo-dev-template'),
            'manage_options',
            'woo-dev-template',
            [$this, 'render']
        );
    }

    /**
     * Register settings and fields.
     */
    public function register_settings(): void
    {
        register_setting(
            'woo_dev_template',
            self::OPTION_NAME,
            [
                'type'              => 'array',
                'sanitize_callback' => [$this, 'sanitize'],
                'default'           => $this->defaults(),
            ]
        );

        add_settings_section(
            'woo_dev_template_general',
            __('General', 'woo-dev-template'),
            '__return_false',
            'woo_dev_template'
        );

        add_settings_field(
            'enabled',
            __('Enable sample behavior', 'woo-dev-template'),
            [$this, 'render_enabled_field'],
            'woo_dev_template',
            'woo_dev_template_general'
        );
    }

    /**
     * Render the settings page.
     */
    public function render(): void
    {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('woo_dev_template');
                do_settings_sections('woo_dev_template');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render the enabled checkbox.
     */
    public function render_enabled_field(): void
    {
        $settings = $this->get_settings();
        ?>
        <label>
            <input
                type="checkbox"
                name="<?php echo esc_attr(self::OPTION_NAME); ?>[enabled]"
                value="1"
                <?php checked(true, $settings['enabled']); ?>
            />
            <?php echo esc_html__('Add a note to newly created WooCommerce orders.', 'woo-dev-template'); ?>
        </label>
        <?php
    }

    /**
     * Sanitize settings.
     *
     * @param mixed $value Raw option value.
     * @return array{enabled: bool}
     */
    public function sanitize(mixed $value): array
    {
        $value = is_array($value) ? $value : [];

        return [
            'enabled' => ! empty($value['enabled']),
        ];
    }

    /**
     * Current settings.
     *
     * @return array{enabled: bool}
     */
    public function get_settings(): array
    {
        $settings = get_option(self::OPTION_NAME, []);

        return wp_parse_args(
            is_array($settings) ? $settings : [],
            $this->defaults()
        );
    }

    /**
     * Default settings.
     *
     * @return array{enabled: bool}
     */
    private function defaults(): array
    {
        return [
            'enabled' => true,
        ];
    }
}

