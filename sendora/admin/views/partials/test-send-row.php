<?php
/**
 * Test-send row with destination phone.
 *
 * @var string $source
 * @var string $event_key Optional Woo event key.
 * @var string $form_id Optional CF7 form id.
 * @var string $test_phone Prefill from settings.
 */

if (!defined('ABSPATH')) {
    exit;
}

$source = (string) ($source ?? '');
$event_key = (string) ($event_key ?? '');
$form_id = (string) ($form_id ?? '');
$test_phone = (string) ($test_phone ?? '');
$phone_country = (string) ($settings['phone_country'] ?? 'BR');
$placeholder = Sendora_Phone::country($phone_country)['placeholder'];
?>
<div class="sendora-test-row">
    <label class="sendora-field sendora-test-row__phone">
        <span><?php echo esc_html__('Enviar teste para', 'sendora'); ?></span>
        <input
            class="sendora-input"
            type="text"
            inputmode="numeric"
            autocomplete="tel"
            value="<?php echo esc_attr($test_phone); ?>"
            placeholder="<?php echo esc_attr($placeholder); ?>"
            data-sendora-test-phone
        >
    </label>
    <button
        type="button"
        class="sendora-btn sendora-btn--ghost"
        data-sendora-test-send
        data-source="<?php echo esc_attr($source); ?>"
        <?php if ($event_key !== '') : ?>
            data-event="<?php echo esc_attr($event_key); ?>"
        <?php endif; ?>
        <?php if ($form_id !== '') : ?>
            data-form-id="<?php echo esc_attr($form_id); ?>"
        <?php endif; ?>
    >
        <?php echo esc_html__('Enviar teste', 'sendora'); ?>
    </button>
    <span class="sendora-inline-status" data-sendora-test-result hidden></span>
</div>
<p class="sendora-help">
    <?php echo esc_html__('Pode ser o seu próprio número. O valor em Configurações é só o padrão.', 'sendora'); ?>
</p>
