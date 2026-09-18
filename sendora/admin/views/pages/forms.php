<?php
/**
 * @var array<string, mixed> $settings
 * @var array<int, array{id: string, name: string}> $flows
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<section class="sendora-hero">
    <div>
        <h1><?php echo esc_html__('Formulários', 'sendora'); ?></h1>
        <p><?php echo esc_html__('Defina o que acontece quando alguém envia um formulário neste site.', 'sendora'); ?></p>
    </div>
</section>

<?php settings_errors(Sendora_Settings::OPTION_KEY); ?>

<form action="options.php" method="post" class="sendora-form-panel">
    <?php settings_fields('sendora_settings_group'); ?>
    <input type="hidden" name="sendora_settings[_partial]" value="forms">

    <section class="sendora-card">
        <div class="sendora-card__head">
            <h2><?php echo esc_html__('Formulário rápido da Sendora', 'sendora'); ?></h2>
        </div>
        <p class="sendora-help">
            <?php echo esc_html__('Cole o shortcode abaixo em qualquer página. Nome, telefone, e-mail e mensagem são enviados à Sendora.', 'sendora'); ?>
        </p>
        <code class="sendora-code">[sendora_form]</code>
        <label class="sendora-field" for="sendora-default-flow">
            <span><?php echo esc_html__('Ao enviar, disparar este fluxo', 'sendora'); ?></span>
            <select class="sendora-input" id="sendora-default-flow" name="sendora_settings[default_flow_id]">
                <option value=""><?php echo esc_html__('Só salvar o contato (sem fluxo)', 'sendora'); ?></option>
                <?php foreach ($flows as $sendora_flow) : ?>
                    <option value="<?php echo esc_attr($sendora_flow['id']); ?>" <?php selected($settings['default_flow_id'], $sendora_flow['id']); ?>>
                        <?php echo esc_html($sendora_flow['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="sendora-field" for="sendora-default-cc">
            <span><?php echo esc_html__('DDI padrão do telefone', 'sendora'); ?></span>
            <input class="sendora-input sendora-input--sm" id="sendora-default-cc" name="sendora_settings[default_cc]" type="text"
                value="<?php echo esc_attr((string) $settings['default_cc']); ?>" inputmode="numeric">
        </label>
    </section>

    <?php if (defined('WPCF7_VERSION')) : ?>
        <?php $cf7_forms = $cf7_forms ?? []; ?>
        <section class="sendora-card">
            <div class="sendora-card__head">
                <h2><?php echo esc_html__('Contact Form 7', 'sendora'); ?></h2>
            </div>
            <p class="sendora-help">
                <?php echo esc_html__('Mapeie os campos do CF7. Quando o formulário for enviado, o contato vai para a Sendora e usa o fluxo acima.', 'sendora'); ?>
            </p>
            <?php if ($cf7_forms === []) : ?>
                <p class="sendora-empty"><?php echo esc_html__('Nenhum formulário CF7 publicado.', 'sendora'); ?></p>
            <?php else : ?>
                <div class="sendora-table-wrap">
                    <table class="sendora-table">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Formulário', 'sendora'); ?></th>
                                <th><?php echo esc_html__('Nome', 'sendora'); ?></th>
                                <th><?php echo esc_html__('Telefone', 'sendora'); ?></th>
                                <th><?php echo esc_html__('E-mail', 'sendora'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cf7_forms as $sendora_form) : ?>
                                <?php
                                $sendora_form_id = $sendora_form['id'];
                                $sendora_saved_mapping = is_array($settings['cf7_mappings'] ?? null)
                                    ? ($settings['cf7_mappings'][$sendora_form_id] ?? [])
                                    : [];
                                $sendora_saved_mapping = is_array($sendora_saved_mapping) ? $sendora_saved_mapping : [];
                                ?>
                                <tr>
                                    <td>
                                        <?php echo esc_html($sendora_form['title']); ?>
                                        <input type="hidden"
                                            name="sendora_settings[cf7_mappings][<?php echo esc_attr($sendora_form_id); ?>][form_id]"
                                            value="<?php echo esc_attr($sendora_form_id); ?>">
                                    </td>
                                    <?php foreach (['name', 'phone', 'email'] as $sendora_field) : ?>
                                        <td>
                                            <select name="sendora_settings[cf7_mappings][<?php echo esc_attr($sendora_form_id); ?>][<?php echo esc_attr($sendora_field); ?>]">
                                                <option value=""><?php echo esc_html__('—', 'sendora'); ?></option>
                                                <?php foreach ($sendora_form['tags'] as $sendora_tag) : ?>
                                                    <option value="<?php echo esc_attr($sendora_tag); ?>" <?php selected($sendora_saved_mapping[$sendora_field] ?? '', $sendora_tag); ?>>
                                                        <?php echo esc_html($sendora_tag); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php else : ?>
        <section class="sendora-card">
            <p class="sendora-empty"><?php echo esc_html__('Contact Form 7 não está ativo. Instale-o para mapear formulários existentes.', 'sendora'); ?></p>
        </section>
    <?php endif; ?>

    <div class="sendora-actions">
        <button type="submit" class="sendora-btn sendora-btn--primary"><?php echo esc_html__('Salvar', 'sendora'); ?></button>
    </div>
</form>
