<?php
/**
 * Shared channel / instance / message fields for Woo + Forms cards.
 *
 * Expects in scope:
 * @var string $field_base Name prefix e.g. sendora_settings[woo_events][order_created]
 * @var string $channel
 * @var string $instance_id
 * @var string $template_id
 * @var string $template_language
 * @var string $body_vars_str
 * @var array<int, array{id: string, name: string}> $templates QR Code / Sendora templates
 * @var array<int, array{id: string, name: string, language?: string}> $meta_templates
 * @var array<int, array{id: string, name: string}> $connections
 * @var array<int, array{id: string, name: string}> $wabas
 * @var bool $message_required
 */

if (!defined('ABSPATH')) {
    exit;
}

$channel = Sendora_Outbound::normalize_provider($channel ?? Sendora_Outbound::PROVIDER_EVOLUTION);
$instance_id = (string) ($instance_id ?? '');
$template_id = (string) ($template_id ?? '');
$template_language = (string) ($template_language ?? 'pt_BR');
$body_vars_str = (string) ($body_vars_str ?? '');
$templates = $templates ?? [];
$meta_templates = $meta_templates ?? [];
$connections = $connections ?? [];
$wabas = $wabas ?? [];
$message_required = !empty($message_required);
$is_meta = $channel === Sendora_Outbound::PROVIDER_META;
$active_templates = $is_meta ? $meta_templates : $templates;
$active_instances = $is_meta ? $wabas : $connections;
?>
<label class="sendora-field">
    <span><?php echo esc_html__('Canal', 'sendora'); ?></span>
    <select class="sendora-input" name="<?php echo esc_attr($field_base); ?>[channel]" data-sendora-provider>
        <option value="evolution" <?php selected($channel, 'evolution'); ?>>
            <?php echo esc_html__('WhatsApp (QR Code)', 'sendora'); ?>
        </option>
        <option value="meta" <?php selected($channel, 'meta'); ?>>
            <?php echo esc_html__('WhatsApp Oficial (Meta API)', 'sendora'); ?>
        </option>
    </select>
</label>
<p class="sendora-help" data-sendora-channel-help>
    <?php
    echo $is_meta
        ? esc_html__('Templates oficiais aprovados na Meta (WABA). Diferente dos modelos com {{variáveis}} do app.', 'sendora')
        : esc_html__('Modelos com variáveis dinâmicas ({{nome}}, {{pedido}}…) criados em app.sendora.com.br/templates.', 'sendora');
    ?>
</p>

<label class="sendora-field">
    <span><?php echo esc_html__('Instância', 'sendora'); ?></span>
    <select class="sendora-input" name="<?php echo esc_attr($field_base); ?>[instance_id]" data-sendora-instance>
        <option value=""><?php echo esc_html__('Padrão da conta', 'sendora'); ?></option>
        <?php foreach ($active_instances as $inst) : ?>
            <option value="<?php echo esc_attr($inst['id']); ?>" <?php selected($instance_id, $inst['id']); ?>>
                <?php echo esc_html($inst['name']); ?>
            </option>
        <?php endforeach; ?>
    </select>
</label>
<p class="sendora-help" data-sendora-instance-help>
    <?php
    echo $is_meta
        ? esc_html__('Conta WABA da API Oficial. Deixe em branco para usar a mais recente.', 'sendora')
        : esc_html__('Número conectado via QR Code. Deixe em branco para o padrão.', 'sendora');
    ?>
</p>

<label class="sendora-field">
    <span><?php echo esc_html__('Mensagem', 'sendora'); ?></span>
    <select
        class="sendora-input"
        name="<?php echo esc_attr($field_base); ?>[template_id]"
        data-sendora-woo-template
        <?php echo $message_required ? 'required' : ''; ?>
    >
        <option value="">
            <?php
            echo $message_required
                ? esc_html__('Selecione uma mensagem', 'sendora')
                : esc_html__('Nenhuma (só CRM)', 'sendora');
            ?>
        </option>
        <?php foreach ($active_templates as $template) : ?>
            <option
                value="<?php echo esc_attr($template['id']); ?>"
                <?php selected($template_id, $template['id']); ?>
                <?php if (!empty($template['language'])) : ?>
                    data-language="<?php echo esc_attr((string) $template['language']); ?>"
                <?php endif; ?>
            >
                <?php echo esc_html($template['name']); ?>
            </option>
        <?php endforeach; ?>
    </select>
</label>
<?php if (!$is_meta && $active_templates === []) : ?>
    <p class="sendora-help">
        <?php echo esc_html__('Nenhum modelo QR encontrado.', 'sendora'); ?>
        <a href="https://app.sendora.com.br/templates" target="_blank" rel="noopener noreferrer">
            <?php echo esc_html__('Criar em /templates', 'sendora'); ?>
        </a>
    </p>
<?php elseif ($is_meta && $active_templates === []) : ?>
    <p class="sendora-help">
        <?php echo esc_html__('Nenhum template Meta aprovado.', 'sendora'); ?>
        <a href="https://app.sendora.com.br/campaign-templates" target="_blank" rel="noopener noreferrer">
            <?php echo esc_html__('Ver templates Meta', 'sendora'); ?>
        </a>
    </p>
<?php endif; ?>

<label class="sendora-field" data-sendora-meta-only <?php echo $is_meta ? '' : 'hidden'; ?>>
    <span><?php echo esc_html__('Idioma do template (Meta)', 'sendora'); ?></span>
    <input
        class="sendora-input sendora-input--sm"
        type="text"
        name="<?php echo esc_attr($field_base); ?>[template_language]"
        value="<?php echo esc_attr($template_language !== '' ? $template_language : 'pt_BR'); ?>"
        placeholder="pt_BR"
        data-sendora-template-language
    >
</label>

<label class="sendora-field" data-sendora-meta-only <?php echo $is_meta ? '' : 'hidden'; ?>>
    <span><?php echo esc_html__('Variáveis do body (Meta)', 'sendora'); ?></span>
    <input
        class="sendora-input"
        type="text"
        name="<?php echo esc_attr($field_base); ?>[body_vars]"
        value="<?php echo esc_attr($body_vars_str); ?>"
        placeholder="<?php echo esc_attr__('first_name, order_number, order_total', 'sendora'); ?>"
        data-sendora-body-vars
    >
</label>
<p class="sendora-help" data-sendora-meta-only <?php echo $is_meta ? '' : 'hidden'; ?>>
    <?php echo esc_html__('Ordem das variáveis {{1}}, {{2}}… do template aprovado na Meta. Opcional se o template não tiver variáveis.', 'sendora'); ?>
</p>
