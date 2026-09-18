<?php
/**
 * Sendora settings page.
 *
 * @var array<string, mixed> $settings
 * @var array<int, array{id: string, name: string}> $flows
 * @var array<int, array{id: string, title: string, tags: array<int, string>}> $cf7_forms
 * @var array<int, array{id: int, title: string}> $pages
 * @var string $masked_api_key
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap sendora-settings">
    <h1><?php echo esc_html__('Configurações da Sendora', 'sendora'); ?></h1>
    <p><?php echo esc_html__('Conecte o WordPress à Sendora e configure a automação padrão.', 'sendora'); ?></p>

    <?php settings_errors(Sendora_Settings::OPTION_KEY); ?>

    <form action="options.php" method="post">
        <?php settings_fields('sendora_settings_group'); ?>

        <section class="sendora-card">
            <h2><?php echo esc_html__('Conexão', 'sendora'); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="sendora-api-key"><?php echo esc_html__('Chave de API', 'sendora'); ?></label>
                    </th>
                    <td>
                        <input class="regular-text" id="sendora-api-key" name="sendora_settings[api_key]" type="password"
                            value="" autocomplete="new-password" placeholder="<?php echo esc_attr__('Cole uma chave sk_', 'sendora'); ?>">
                        <?php if ($masked_api_key !== '') : ?>
                            <p class="description">
                                <?php echo esc_html__('Chave salva:', 'sendora'); ?>
                                <code><?php echo esc_html($masked_api_key); ?></code>.
                                <?php echo esc_html__('Deixe em branco para manter a atual.', 'sendora'); ?>
                            </p>
                        <?php endif; ?>
                        <p class="description">
                            <?php echo esc_html__('Escopos recomendados: contacts:write, flows:write e messages:write se for enviar mensagens.', 'sendora'); ?>
                        </p>
                        <p class="description">
                            <?php echo esc_html__('Cole a chave e clique em Testar conexão (pode testar antes de salvar). Depois salve as alterações.', 'sendora'); ?>
                        </p>
                        <button class="button" id="sendora-test-connection" type="button">
                            <?php echo esc_html__('Testar conexão', 'sendora'); ?>
                        </button>
                        <span id="sendora-connection-result" class="sendora-connection-result" role="status" aria-live="polite"></span>
                    </td>
                </tr>
            </table>
        </section>

        <section class="sendora-card">
            <h2><?php echo esc_html__('Formulários', 'sendora'); ?></h2>
            <p class="description"><?php echo esc_html__('Usados pelo shortcode [sendora_form] e pelos bridges (Contact Form 7 / WooCommerce).', 'sendora'); ?></p>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="sendora-default-flow"><?php echo esc_html__('Fluxo padrão', 'sendora'); ?></label></th>
                    <td>
                        <select id="sendora-default-flow" name="sendora_settings[default_flow_id]">
                            <option value=""><?php echo esc_html__('Nenhum fluxo padrão', 'sendora'); ?></option>
                            <?php foreach ($flows as $sendora_flow) : ?>
                                <option value="<?php echo esc_attr($sendora_flow['id']); ?>" <?php selected($settings['default_flow_id'], $sendora_flow['id']); ?>>
                                    <?php echo esc_html($sendora_flow['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="sendora-default-cc"><?php echo esc_html__('DDI padrão', 'sendora'); ?></label></th>
                    <td><input class="small-text" id="sendora-default-cc" name="sendora_settings[default_cc]" type="text"
                        value="<?php echo esc_attr((string) $settings['default_cc']); ?>" inputmode="numeric"></td>
                </tr>
            </table>
        </section>

        <section class="sendora-card">
            <h2><?php echo esc_html__('Widget de chat', 'sendora'); ?></h2>
            <p class="description"><?php echo esc_html__('Independente dos formulários. Exibe o chat oficial da Sendora no site.', 'sendora'); ?></p>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php echo esc_html__('Ativação', 'sendora'); ?></th>
                    <td>
                        <label>
                            <input name="sendora_settings[widget_enabled]" type="checkbox" value="1" <?php checked($settings['widget_enabled']); ?>>
                            <?php echo esc_html__('Ativar o widget da Sendora no site', 'sendora'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="sendora-widget-id"><?php echo esc_html__('ID do widget', 'sendora'); ?></label></th>
                    <td>
                        <input class="regular-text" id="sendora-widget-id" name="sendora_settings[widget_id]" type="text"
                            value="<?php echo esc_attr((string) $settings['widget_id']); ?>"
                            placeholder="8ab21a48-c6e2-4cea-99ef-56f18eb8d4c3"
                            spellcheck="false" autocomplete="off">
                        <p class="description">
                            <?php echo esc_html__('Cole só o UUID do widget. Se colar a URL com &v=6, o ID é extraído automaticamente.', 'sendora'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Onde exibir', 'sendora'); ?></th>
                    <td>
                        <?php
                        $sendora_widget_display = (string) ($settings['widget_display'] ?? 'all');
                        $sendora_widget_page_ids = is_array($settings['widget_page_ids'] ?? null)
                            ? array_map('intval', $settings['widget_page_ids'])
                            : [];
                        ?>
                        <fieldset class="sendora-widget-display">
                            <legend class="screen-reader-text"><?php echo esc_html__('Onde exibir o widget', 'sendora'); ?></legend>
                            <label>
                                <input type="radio" name="sendora_settings[widget_display]" value="all"
                                    <?php checked($sendora_widget_display, 'all'); ?>
                                    data-sendora-widget-display="all">
                                <?php echo esc_html__('Todas as páginas', 'sendora'); ?>
                            </label>
                            <br>
                            <label>
                                <input type="radio" name="sendora_settings[widget_display]" value="specific"
                                    <?php checked($sendora_widget_display, 'specific'); ?>
                                    data-sendora-widget-display="specific">
                                <?php echo esc_html__('Apenas páginas específicas', 'sendora'); ?>
                            </label>
                        </fieldset>
                        <div class="sendora-widget-pages" id="sendora-widget-pages"
                            <?php echo $sendora_widget_display === 'specific' ? '' : ' hidden'; ?>>
                            <?php if (empty($pages)) : ?>
                                <p class="description"><?php echo esc_html__('Nenhuma página publicada encontrada.', 'sendora'); ?></p>
                            <?php else : ?>
                                <p class="description"><?php echo esc_html__('Selecione uma ou mais páginas:', 'sendora'); ?></p>
                                <select id="sendora-widget-page-ids" name="sendora_settings[widget_page_ids][]" multiple size="8" class="sendora-page-multiselect">
                                    <?php foreach ($pages as $sendora_page) : ?>
                                        <option value="<?php echo esc_attr((string) $sendora_page['id']); ?>"
                                            <?php echo in_array((int) $sendora_page['id'], $sendora_widget_page_ids, true) ? 'selected="selected"' : ''; ?>>
                                            <?php echo esc_html((string) $sendora_page['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php echo esc_html__('Segure Ctrl (Windows) ou Cmd (Mac) para selecionar várias.', 'sendora'); ?></p>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            </table>
        </section>

        <?php if (defined('WPCF7_VERSION')) : ?>
            <section class="sendora-card">
                <h2><?php echo esc_html__('Contact Form 7', 'sendora'); ?></h2>
                <?php if ($cf7_forms === []) : ?>
                    <p><?php echo esc_html__('Nenhum formulário publicado do Contact Form 7 foi encontrado.', 'sendora'); ?></p>
                <?php else : ?>
                    <p><?php echo esc_html__('Mapeie cada campo do formulário para a Sendora. O telefone é obrigatório para sincronizar.', 'sendora'); ?></p>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Formulário', 'sendora'); ?></th>
                                <th><?php echo esc_html__('Campo nome', 'sendora'); ?></th>
                                <th><?php echo esc_html__('Campo telefone', 'sendora'); ?></th>
                                <th><?php echo esc_html__('Campo e-mail', 'sendora'); ?></th>
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
                                        <code>#<?php echo esc_html($sendora_form_id); ?></code>
                                        <input type="hidden"
                                            name="sendora_settings[cf7_mappings][<?php echo esc_attr($sendora_form_id); ?>][form_id]"
                                            value="<?php echo esc_attr($sendora_form_id); ?>">
                                    </td>
                                    <?php foreach (['name' => __('Nome', 'sendora'), 'phone' => __('Telefone', 'sendora'), 'email' => __('E-mail', 'sendora')] as $sendora_field => $sendora_label) : ?>
                                        <td>
                                            <label class="screen-reader-text" for="sendora-cf7-<?php echo esc_attr($sendora_form_id . '-' . $sendora_field); ?>">
                                                <?php echo esc_html($sendora_form['title'] . ': ' . $sendora_label); ?>
                                            </label>
                                            <select id="sendora-cf7-<?php echo esc_attr($sendora_form_id . '-' . $sendora_field); ?>"
                                                name="sendora_settings[cf7_mappings][<?php echo esc_attr($sendora_form_id); ?>][<?php echo esc_attr($sendora_field); ?>]">
                                                <option value=""><?php echo esc_html__('Não mapeado', 'sendora'); ?></option>
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
                    <p class="description">
                        <?php echo esc_html__('Os envios mapeados são sincronizados após o e-mail do CF7 e usam o fluxo padrão acima.', 'sendora'); ?>
                    </p>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <section class="sendora-card">
            <h2><?php echo esc_html__('WooCommerce', 'sendora'); ?></h2>
            <fieldset class="sendora-checkboxes">
                <legend class="screen-reader-text"><?php echo esc_html__('Eventos de sincronização do WooCommerce', 'sendora'); ?></legend>
                <label><input name="sendora_settings[woo_on_paid]" type="checkbox" value="1" <?php checked($settings['woo_on_paid']); ?>>
                    <?php echo esc_html__('Sincronizar quando o pagamento for concluído', 'sendora'); ?></label>
                <label><input name="sendora_settings[woo_on_cancelled]" type="checkbox" value="1" <?php checked($settings['woo_on_cancelled']); ?>>
                    <?php echo esc_html__('Sincronizar quando o pedido for cancelado', 'sendora'); ?></label>
            </fieldset>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="sendora-created-mode"><?php echo esc_html__('Ação em pedido novo', 'sendora'); ?></label></th>
                    <td>
                        <select id="sendora-created-mode" name="sendora_settings[woo_created_mode]">
                            <option value="off" <?php selected($settings['woo_created_mode'], 'off'); ?>><?php echo esc_html__('Desligado', 'sendora'); ?></option>
                            <option value="contact_only" <?php selected($settings['woo_created_mode'], 'contact_only'); ?>><?php echo esc_html__('Só sincronizar contato', 'sendora'); ?></option>
                            <option value="contact_and_flow" <?php selected($settings['woo_created_mode'], 'contact_and_flow'); ?>><?php echo esc_html__('Sincronizar contato e disparar fluxo padrão', 'sendora'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="sendora-paid-mode"><?php echo esc_html__('Ação em pedido pago', 'sendora'); ?></label></th>
                    <td>
                        <select id="sendora-paid-mode" name="sendora_settings[woo_paid_mode]">
                            <option value="off" <?php selected($settings['woo_paid_mode'], 'off'); ?>><?php echo esc_html__('Desligado', 'sendora'); ?></option>
                            <option value="flow" <?php selected($settings['woo_paid_mode'], 'flow'); ?>><?php echo esc_html__('Disparar fluxo', 'sendora'); ?></option>
                            <option value="message" <?php selected($settings['woo_paid_mode'], 'message'); ?>><?php echo esc_html__('Enviar mensagem', 'sendora'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="sendora-paid-flow"><?php echo esc_html__('Fluxo do pedido pago', 'sendora'); ?></label></th>
                    <td>
                        <select id="sendora-paid-flow" name="sendora_settings[woo_paid_flow_id]">
                            <option value=""><?php echo esc_html__('Selecione um fluxo', 'sendora'); ?></option>
                            <?php foreach ($flows as $sendora_flow) : ?>
                                <option value="<?php echo esc_attr($sendora_flow['id']); ?>" <?php selected($settings['woo_paid_flow_id'], $sendora_flow['id']); ?>>
                                    <?php echo esc_html($sendora_flow['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
        </section>

        <?php submit_button(__('Salvar alterações', 'sendora')); ?>
    </form>
</div>
