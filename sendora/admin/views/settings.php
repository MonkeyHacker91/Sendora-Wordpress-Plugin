<?php
/**
 * Sendora settings page.
 *
 * @var array<string, bool|string> $settings
 * @var array<int, array{id: string, name: string}> $flows
 * @var string $masked_api_key
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap sendora-settings">
    <h1><?php echo esc_html__('Sendora settings', 'sendora'); ?></h1>
    <p><?php echo esc_html__('Connect WordPress to Sendora and configure default automation behavior.', 'sendora'); ?></p>

    <?php settings_errors(Sendora_Settings::OPTION_KEY); ?>

    <form action="options.php" method="post">
        <?php settings_fields('sendora_settings_group'); ?>

        <section class="sendora-card">
            <h2><?php echo esc_html__('Connection', 'sendora'); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="sendora-api-base"><?php echo esc_html__('API base URL', 'sendora'); ?></label>
                    </th>
                    <td>
                        <input class="regular-text" id="sendora-api-base" name="sendora_settings[api_base]" type="url"
                            value="<?php echo esc_attr((string) $settings['api_base']); ?>" required pattern="https://.*">
                        <p class="description"><?php echo esc_html__('HTTPS is required.', 'sendora'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="sendora-api-key"><?php echo esc_html__('API key', 'sendora'); ?></label>
                    </th>
                    <td>
                        <input class="regular-text" id="sendora-api-key" name="sendora_settings[api_key]" type="password"
                            value="" autocomplete="new-password" placeholder="<?php echo esc_attr__('Enter an sk_ key', 'sendora'); ?>">
                        <?php if ($masked_api_key !== '') : ?>
                            <p class="description">
                                <?php
                                printf(
                                    /* translators: %s is a masked API key showing only its final four characters. */
                                    esc_html__('Saved key: %s. Leave the field empty to keep it.', 'sendora'),
                                    '<code>' . esc_html($masked_api_key) . '</code>'
                                );
                                ?>
                            </p>
                        <?php endif; ?>
                        <p class="description">
                            <?php echo esc_html__('Recommended scopes: contacts:write, flows:write, and messages:write when sending messages.', 'sendora'); ?>
                        </p>
                        <button class="button" id="sendora-test-connection" type="button">
                            <?php echo esc_html__('Test connection', 'sendora'); ?>
                        </button>
                        <span id="sendora-connection-result" class="sendora-connection-result" role="status" aria-live="polite"></span>
                    </td>
                </tr>
            </table>
        </section>

        <section class="sendora-card">
            <h2><?php echo esc_html__('Forms and widget', 'sendora'); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="sendora-default-flow"><?php echo esc_html__('Default flow', 'sendora'); ?></label></th>
                    <td>
                        <select id="sendora-default-flow" name="sendora_settings[default_flow_id]">
                            <option value=""><?php echo esc_html__('No default flow', 'sendora'); ?></option>
                            <?php foreach ($flows as $flow) : ?>
                                <option value="<?php echo esc_attr($flow['id']); ?>" <?php selected($settings['default_flow_id'], $flow['id']); ?>>
                                    <?php echo esc_html($flow['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="sendora-default-cc"><?php echo esc_html__('Default country code', 'sendora'); ?></label></th>
                    <td><input class="small-text" id="sendora-default-cc" name="sendora_settings[default_cc]" type="text"
                        value="<?php echo esc_attr((string) $settings['default_cc']); ?>" inputmode="numeric"></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Chat widget', 'sendora'); ?></th>
                    <td>
                        <label>
                            <input name="sendora_settings[widget_enabled]" type="checkbox" value="1" <?php checked($settings['widget_enabled']); ?>>
                            <?php echo esc_html__('Enable the Sendora widget', 'sendora'); ?>
                        </label>
                        <p><label for="sendora-widget-id"><?php echo esc_html__('Widget ID', 'sendora'); ?></label></p>
                        <input class="regular-text" id="sendora-widget-id" name="sendora_settings[widget_id]" type="text"
                            value="<?php echo esc_attr((string) $settings['widget_id']); ?>">
                    </td>
                </tr>
            </table>
        </section>

        <section class="sendora-card">
            <h2><?php echo esc_html__('WooCommerce', 'sendora'); ?></h2>
            <fieldset class="sendora-checkboxes">
                <legend class="screen-reader-text"><?php echo esc_html__('WooCommerce synchronization events', 'sendora'); ?></legend>
                <label><input name="sendora_settings[woo_on_created]" type="checkbox" value="1" <?php checked($settings['woo_on_created']); ?>>
                    <?php echo esc_html__('Sync when an order is created', 'sendora'); ?></label>
                <label><input name="sendora_settings[woo_on_paid]" type="checkbox" value="1" <?php checked($settings['woo_on_paid']); ?>>
                    <?php echo esc_html__('Sync when payment completes', 'sendora'); ?></label>
                <label><input name="sendora_settings[woo_on_cancelled]" type="checkbox" value="1" <?php checked($settings['woo_on_cancelled']); ?>>
                    <?php echo esc_html__('Sync when an order is cancelled', 'sendora'); ?></label>
            </fieldset>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="sendora-paid-mode"><?php echo esc_html__('Paid order action', 'sendora'); ?></label></th>
                    <td>
                        <select id="sendora-paid-mode" name="sendora_settings[woo_paid_mode]">
                            <option value="off" <?php selected($settings['woo_paid_mode'], 'off'); ?>><?php echo esc_html__('Off', 'sendora'); ?></option>
                            <option value="flow" <?php selected($settings['woo_paid_mode'], 'flow'); ?>><?php echo esc_html__('Trigger flow', 'sendora'); ?></option>
                            <option value="message" <?php selected($settings['woo_paid_mode'], 'message'); ?>><?php echo esc_html__('Send message', 'sendora'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="sendora-paid-flow"><?php echo esc_html__('Paid order flow', 'sendora'); ?></label></th>
                    <td>
                        <select id="sendora-paid-flow" name="sendora_settings[woo_paid_flow_id]">
                            <option value=""><?php echo esc_html__('Select a flow', 'sendora'); ?></option>
                            <?php foreach ($flows as $flow) : ?>
                                <option value="<?php echo esc_attr($flow['id']); ?>" <?php selected($settings['woo_paid_flow_id'], $flow['id']); ?>>
                                    <?php echo esc_html($flow['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
        </section>

        <?php submit_button(); ?>
    </form>
</div>
