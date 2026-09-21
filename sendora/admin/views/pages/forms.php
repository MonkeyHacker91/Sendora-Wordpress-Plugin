<?php
/**
 * @var array<string, mixed> $settings
 * @var array<int, array{id: string, name: string, content: string, type: string}> $templates
 * @var array<int, array{id: string, name: string, language?: string}> $meta_templates
 * @var array<int, array{id: string, name: string}> $connections
 * @var array<int, array{id: string, name: string}> $wabas
 * @var array<int, array{id: string, title: string, tags: array<int, string>}> $cf7_forms
 */

if (!defined('ABSPATH')) {
    exit;
}

$native = is_array($settings['native_form'] ?? null) ? $settings['native_form'] : [];
$native_enabled = !empty($native['enabled']);
$native_template = (string) ($native['template_id'] ?? '');
$phone_country = (string) ($settings['phone_country'] ?? 'BR');
$templates = $templates ?? [];
$meta_templates = $meta_templates ?? [];
$connections = $connections ?? [];
$wabas = $wabas ?? [];
?>
<section class="sendora-hero">
    <div>
        <p class="sendora-eyebrow"><?php echo esc_html__('Formulários', 'sendora'); ?></p>
        <h1><?php echo esc_html__('Escolha quando o Sendora deve falar com seu lead', 'sendora'); ?></h1>
        <p><?php echo esc_html__('Configure a mensagem automática enviada quando alguém preenche um formulário.', 'sendora'); ?></p>
    </div>
</section>

<?php settings_errors(Sendora_Settings::OPTION_KEY); ?>

<form action="options.php" method="post" class="sendora-form-panel">
    <?php settings_fields('sendora_settings_group'); ?>
    <input type="hidden" name="sendora_settings[_partial]" value="forms">

    <label class="sendora-field" for="sendora-phone-country">
        <span><?php echo esc_html__('País do telefone', 'sendora'); ?></span>
        <select class="sendora-input" id="sendora-phone-country" name="sendora_settings[phone_country]">
            <?php echo Sendora_Phone::country_options_html($phone_country); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper ?>
        </select>
    </label>
    <p class="sendora-help"><?php echo esc_html__('Aplica DDI e máscara no formulário [sendora_form] e na normalização dos envios.', 'sendora'); ?></p>

    <h2 class="sendora-section-title"><?php echo esc_html__('Formulário Sendora', 'sendora'); ?></h2>
    <div class="sendora-woo-events">
        <?php
        $channel = Sendora_Outbound::normalize_provider((string) ($native['channel'] ?? 'evolution'));
        $instance_id = (string) ($native['instance_id'] ?? '');
        $template_id = $native_template;
        $template_language = (string) ($native['template_language'] ?? 'pt_BR');
        $body_vars = is_array($native['body_vars'] ?? null) ? $native['body_vars'] : [];
        $body_vars_str = implode(', ', $body_vars);
        $field_base = 'sendora_settings[native_form]';
        $message_required = true;
        ?>
        <article class="sendora-woo-card<?php echo $native_enabled ? ' is-enabled' : ''; ?>" data-sendora-woo-card>
            <header class="sendora-woo-card__head">
                <div class="sendora-woo-card__title">
                    <span class="sendora-woo-card__icon" aria-hidden="true">📝</span>
                    <div>
                        <h3><?php echo esc_html__('Shortcode [sendora_form]', 'sendora'); ?></h3>
                        <p><?php echo esc_html__('Quando alguém envia o formulário nativo da Sendora.', 'sendora'); ?></p>
                    </div>
                </div>
                <label class="sendora-woo-switch">
                    <input type="checkbox" name="sendora_settings[native_form][enabled]" value="1"
                        <?php checked($native_enabled); ?> data-sendora-woo-toggle>
                    <span class="sendora-woo-switch__ui" aria-hidden="true"></span>
                    <span class="sendora-woo-switch__label" data-sendora-woo-state>
                        <?php echo esc_html($native_enabled ? __('Ativo', 'sendora') : __('Desativado', 'sendora')); ?>
                    </span>
                </label>
            </header>
            <div class="sendora-woo-card__body" data-sendora-woo-body <?php echo $native_enabled ? '' : 'hidden'; ?>>
                <code class="sendora-code">[sendora_form]</code>
                <?php require SENDORA_PLUGIN_DIR . 'admin/views/partials/message-channel-fields.php'; ?>
                <p class="sendora-woo-card__recipient">
                    <span><?php echo esc_html__('Destinatário', 'sendora'); ?></span>
                    <strong><?php echo esc_html__('Quem preencheu o formulário', 'sendora'); ?></strong>
                </p>
                <div class="sendora-actions" style="margin-top:12px">
                    <?php
                    $source = 'native_form';
                    $event_key = '';
                    $form_id = '';
                    $test_phone = (string) ($settings['test_phone'] ?? '');
                    require SENDORA_PLUGIN_DIR . 'admin/views/partials/test-send-row.php';
                    ?>
                </div>
            </div>
        </article>
    </div>

    <?php if (defined('WPCF7_VERSION')) : ?>
        <h2 class="sendora-section-title"><?php echo esc_html__('Contact Form 7', 'sendora'); ?></h2>
        <?php if ($cf7_forms === []) : ?>
            <section class="sendora-card">
                <p class="sendora-empty"><?php echo esc_html__('Nenhum formulário CF7 publicado.', 'sendora'); ?></p>
            </section>
        <?php else : ?>
            <div class="sendora-woo-events">
                <?php foreach ($cf7_forms as $sendora_form) :
                    $fid = $sendora_form['id'];
                    $map = is_array($settings['cf7_mappings'][$fid] ?? null) ? $settings['cf7_mappings'][$fid] : [];
                    $enabled = !empty($map['enabled']);
                    $channel = Sendora_Outbound::normalize_provider((string) ($map['channel'] ?? 'evolution'));
                    $instance_id = (string) ($map['instance_id'] ?? '');
                    $template_id = (string) ($map['template_id'] ?? '');
                    $template_language = (string) ($map['template_language'] ?? 'pt_BR');
                    $body_vars = is_array($map['body_vars'] ?? null) ? $map['body_vars'] : [];
                    $body_vars_str = implode(', ', $body_vars);
                    $field_base = 'sendora_settings[cf7_mappings][' . $fid . ']';
                    $message_required = true;
                    ?>
                    <article class="sendora-woo-card<?php echo $enabled ? ' is-enabled' : ''; ?>" data-sendora-woo-card>
                        <header class="sendora-woo-card__head">
                            <div class="sendora-woo-card__title">
                                <span class="sendora-woo-card__icon" aria-hidden="true">✉️</span>
                                <div>
                                    <h3><?php echo esc_html($sendora_form['title']); ?></h3>
                                    <p><?php echo esc_html__('Envie uma mensagem Sendora quando este formulário for enviado.', 'sendora'); ?></p>
                                </div>
                            </div>
                            <label class="sendora-woo-switch">
                                <input type="checkbox" name="<?php echo esc_attr($field_base); ?>[enabled]" value="1"
                                    <?php checked($enabled); ?> data-sendora-woo-toggle>
                                <span class="sendora-woo-switch__ui" aria-hidden="true"></span>
                                <span class="sendora-woo-switch__label" data-sendora-woo-state>
                                    <?php echo esc_html($enabled ? __('Ativo', 'sendora') : __('Desativado', 'sendora')); ?>
                                </span>
                            </label>
                        </header>
                        <div class="sendora-woo-card__body" data-sendora-woo-body <?php echo $enabled ? '' : 'hidden'; ?>>
                            <input type="hidden" name="<?php echo esc_attr($field_base); ?>[form_id]" value="<?php echo esc_attr($fid); ?>">
                            <div class="sendora-grid--stats" style="margin-bottom:12px">
                                <?php foreach (['name' => __('Campo nome', 'sendora'), 'phone' => __('Campo telefone', 'sendora'), 'email' => __('Campo e-mail', 'sendora')] as $field => $label) : ?>
                                    <label class="sendora-field">
                                        <span><?php echo esc_html($label); ?></span>
                                        <select class="sendora-input" name="<?php echo esc_attr($field_base); ?>[<?php echo esc_attr($field); ?>]" <?php echo $field === 'phone' ? 'required' : ''; ?>>
                                            <option value=""><?php echo esc_html__('—', 'sendora'); ?></option>
                                            <?php foreach ($sendora_form['tags'] as $tag) : ?>
                                                <option value="<?php echo esc_attr($tag); ?>" <?php selected($map[$field] ?? '', $tag); ?>>
                                                    <?php echo esc_html($tag); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <?php require SENDORA_PLUGIN_DIR . 'admin/views/partials/message-channel-fields.php'; ?>
                            <div class="sendora-actions" style="margin-top:12px">
                                <?php
                                $source = 'cf7';
                                $event_key = '';
                                $form_id = (string) $fid;
                                $test_phone = (string) ($settings['test_phone'] ?? '');
                                require SENDORA_PLUGIN_DIR . 'admin/views/partials/test-send-row.php';
                                ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php else : ?>
        <section class="sendora-card">
            <p class="sendora-empty"><?php echo esc_html__('Contact Form 7 não está ativo. Instale-o para mapear formulários existentes.', 'sendora'); ?></p>
        </section>
    <?php endif; ?>

    <div class="sendora-actions">
        <button type="submit" class="sendora-btn sendora-btn--primary"><?php echo esc_html__('Salvar', 'sendora'); ?></button>
    </div>
</form>
