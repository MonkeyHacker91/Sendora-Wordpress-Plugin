<?php
/**
 * @var array<string, mixed> $settings
 */

if (!defined('ABSPATH')) {
    exit;
}

$phone_country = (string) ($settings['phone_country'] ?? 'BR');
?>
<section class="sendora-hero">
    <div>
        <h1><?php echo esc_html__('Configurações', 'sendora'); ?></h1>
        <p><?php echo esc_html__('Preferências gerais deste site.', 'sendora'); ?></p>
    </div>
</section>

<?php settings_errors(Sendora_Settings::OPTION_KEY); ?>

<form action="options.php" method="post" class="sendora-form-panel">
    <?php settings_fields('sendora_settings_group'); ?>
    <input type="hidden" name="sendora_settings[_partial]" value="general">

    <section class="sendora-card">
        <label class="sendora-field" for="sendora-phone-country-general">
            <span><?php echo esc_html__('País do telefone', 'sendora'); ?></span>
            <select class="sendora-input" id="sendora-phone-country-general" name="sendora_settings[phone_country]">
                <?php echo Sendora_Phone::country_options_html($phone_country); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper ?>
            </select>
        </label>
        <p class="sendora-help">
            <?php echo esc_html__('Define o DDI e a máscara do telefone em formulários, pedidos e testes.', 'sendora'); ?>
        </p>

        <label class="sendora-field" for="sendora-test-phone">
            <span><?php echo esc_html__('Telefone para testes', 'sendora'); ?></span>
            <input class="sendora-input" id="sendora-test-phone" name="sendora_settings[test_phone]" type="text"
                value="<?php echo esc_attr((string) ($settings['test_phone'] ?? '')); ?>"
                placeholder="<?php echo esc_attr(Sendora_Phone::country($phone_country)['placeholder']); ?>"
                inputmode="numeric" autocomplete="tel">
        </label>
        <p class="sendora-help"><?php echo esc_html__('Padrão do campo “Enviar teste para” nos cards. Você também pode digitar o número na hora do teste (inclusive o seu).', 'sendora'); ?></p>
    </section>

    <div class="sendora-actions">
        <button type="submit" class="sendora-btn sendora-btn--primary"><?php echo esc_html__('Salvar', 'sendora'); ?></button>
    </div>
</form>
