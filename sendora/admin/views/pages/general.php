<?php
/**
 * @var array<string, mixed> $settings
 */

if (!defined('ABSPATH')) {
    exit;
}
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
        <label class="sendora-field" for="sendora-default-cc-general">
            <span><?php echo esc_html__('DDI padrão', 'sendora'); ?></span>
            <input class="sendora-input sendora-input--sm" id="sendora-default-cc-general" name="sendora_settings[default_cc]" type="text"
                value="<?php echo esc_attr((string) $settings['default_cc']); ?>" inputmode="numeric">
        </label>
        <p class="sendora-help"><?php echo esc_html__('Usado ao normalizar telefones de formulários e pedidos.', 'sendora'); ?></p>
    </section>

    <div class="sendora-actions">
        <button type="submit" class="sendora-btn sendora-btn--primary"><?php echo esc_html__('Salvar', 'sendora'); ?></button>
    </div>
</form>
