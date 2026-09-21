<?php
/**
 * @var array<string, mixed> $settings
 * @var array<int, array{id: string, name: string, content: string, type: string}> $templates
 * @var array<int, array{id: string, name: string, language?: string}> $meta_templates
 * @var array<int, array{id: string, name: string}> $connections
 * @var array<int, array{id: string, name: string}> $wabas
 * @var array<int, array{id: string, name: string}> $funnels
 * @var array<int, array{id: string, label: string, funnel_id: string}> $stages
 * @var bool $woo_active
 */

if (!defined('ABSPATH')) {
    exit;
}

$events = class_exists('Sendora_WooCommerce')
    ? Sendora_WooCommerce::event_definitions()
    : [];
$woo_events = is_array($settings['woo_events'] ?? null)
    ? $settings['woo_events']
    : [];
$funnels = $funnels ?? [];
$stages = $stages ?? [];
$templates = $templates ?? [];
$meta_templates = $meta_templates ?? [];
$connections = $connections ?? [];
$wabas = $wabas ?? [];

$icons = [
    'cart' => '🛒',
    'bag' => '🛍️',
    'card' => '💳',
    'gear' => '⚙️',
    'check' => '✅',
    'cancel' => '❌',
    'refund' => '↩️',
    'truck' => '🚚',
    'box' => '📦',
];
?>
<section class="sendora-hero">
    <div>
        <p class="sendora-eyebrow"><?php echo esc_html__('WooCommerce', 'sendora'); ?></p>
        <h1><?php echo esc_html__('Automatize as mensagens da sua loja', 'sendora'); ?></h1>
        <p><?php echo esc_html__('Mensagem no WhatsApp (QR Code ou API Oficial) e atualização no CRM Sendora conforme o pedido.', 'sendora'); ?></p>
    </div>
</section>

<?php if (empty($woo_active)) : ?>
    <section class="sendora-card">
        <p class="sendora-empty"><?php echo esc_html__('WooCommerce não está ativo neste site.', 'sendora'); ?></p>
    </section>
<?php else : ?>
    <?php settings_errors(Sendora_Settings::OPTION_KEY); ?>
    <form action="options.php" method="post" class="sendora-form-panel" id="sendora-woo-events-form">
        <?php settings_fields('sendora_settings_group'); ?>
        <input type="hidden" name="sendora_settings[_partial]" value="woo">

        <h2 class="sendora-section-title"><?php echo esc_html__('Eventos', 'sendora'); ?></h2>

        <div class="sendora-woo-events">
            <?php foreach ($events as $event_key => $meta) :
                $cfg = is_array($woo_events[$event_key] ?? null) ? $woo_events[$event_key] : [];
                $enabled = !empty($cfg['enabled']);
                $template_id = (string) ($cfg['template_id'] ?? '');
                $channel = Sendora_Outbound::normalize_provider((string) ($cfg['channel'] ?? 'evolution'));
                $instance_id = (string) ($cfg['instance_id'] ?? '');
                $template_language = (string) ($cfg['template_language'] ?? 'pt_BR');
                $body_vars = is_array($cfg['body_vars'] ?? null) ? $cfg['body_vars'] : [];
                $body_vars_str = implode(', ', $body_vars);
                $delay = (int) ($cfg['delay_minutes'] ?? 60);
                $funnel_id = (string) ($cfg['funnel_id'] ?? '');
                $stage = (string) ($cfg['stage'] ?? '');
                $tags = is_array($cfg['tags'] ?? null) ? $cfg['tags'] : [];
                $tags_str = implode(', ', $tags);
                $icon = $icons[$meta['icon'] ?? ''] ?? '💬';
                $field_base = 'sendora_settings[woo_events][' . $event_key . ']';
                $is_abandoned = !empty($meta['abandoned']);
                $message_required = false;
                ?>
                <article class="sendora-woo-card<?php echo $enabled ? ' is-enabled' : ''; ?>" data-sendora-woo-card>
                    <header class="sendora-woo-card__head">
                        <div class="sendora-woo-card__title">
                            <span class="sendora-woo-card__icon" aria-hidden="true"><?php echo esc_html($icon); ?></span>
                            <div>
                                <h3><?php echo esc_html((string) $meta['label']); ?></h3>
                                <p><?php echo esc_html((string) $meta['description']); ?></p>
                            </div>
                        </div>
                        <label class="sendora-woo-switch">
                            <input
                                type="checkbox"
                                name="<?php echo esc_attr($field_base); ?>[enabled]"
                                value="1"
                                <?php checked($enabled); ?>
                                data-sendora-woo-toggle
                            >
                            <span class="sendora-woo-switch__ui" aria-hidden="true"></span>
                            <span class="sendora-woo-switch__label" data-sendora-woo-state>
                                <?php echo esc_html($enabled ? __('Ativo', 'sendora') : __('Desativado', 'sendora')); ?>
                            </span>
                        </label>
                    </header>

                    <div class="sendora-woo-card__body" data-sendora-woo-body <?php echo $enabled ? '' : 'hidden'; ?>>
                        <?php require SENDORA_PLUGIN_DIR . 'admin/views/partials/message-channel-fields.php'; ?>

                        <label class="sendora-field">
                            <span><?php echo esc_html__('Funil (CRM)', 'sendora'); ?></span>
                            <select class="sendora-input" name="<?php echo esc_attr($field_base); ?>[funnel_id]" data-sendora-woo-funnel>
                                <option value=""><?php echo esc_html__('Não mover no CRM', 'sendora'); ?></option>
                                <?php foreach ($funnels as $funnel) : ?>
                                    <option value="<?php echo esc_attr($funnel['id']); ?>" <?php selected($funnel_id, $funnel['id']); ?>>
                                        <?php echo esc_html($funnel['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label class="sendora-field">
                            <span><?php echo esc_html__('Estágio', 'sendora'); ?></span>
                            <select class="sendora-input" name="<?php echo esc_attr($field_base); ?>[stage]" data-sendora-woo-stage data-selected="<?php echo esc_attr($stage); ?>">
                                <option value=""><?php echo esc_html__('Selecione um estágio', 'sendora'); ?></option>
                                <?php foreach ($stages as $stage_row) :
                                    if ($funnel_id !== '' && (string) $stage_row['funnel_id'] !== $funnel_id) {
                                        continue;
                                    }
                                    ?>
                                    <option value="<?php echo esc_attr($stage_row['id']); ?>" <?php selected($stage, $stage_row['id']); ?>
                                        data-funnel="<?php echo esc_attr($stage_row['funnel_id']); ?>">
                                        <?php echo esc_html($stage_row['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label class="sendora-field">
                            <span><?php echo esc_html__('Tags', 'sendora'); ?></span>
                            <input class="sendora-input" type="text"
                                name="<?php echo esc_attr($field_base); ?>[tags]"
                                value="<?php echo esc_attr($tags_str); ?>"
                                placeholder="<?php echo esc_attr__('woo, pedido-pago', 'sendora'); ?>">
                        </label>
                        <p class="sendora-help"><?php echo esc_html__('Separe as tags por vírgula. Opcional.', 'sendora'); ?></p>

                        <?php if ($is_abandoned) : ?>
                            <label class="sendora-field">
                                <span><?php echo esc_html__('Aguardar antes de enviar (minutos)', 'sendora'); ?></span>
                                <input class="sendora-input sendora-input--sm" type="number" min="5" max="10080"
                                    name="<?php echo esc_attr($field_base); ?>[delay_minutes]"
                                    value="<?php echo esc_attr((string) max(5, $delay)); ?>">
                            </label>
                            <p class="sendora-help">
                                <?php echo esc_html__('Dispara após o cliente informar o telefone no checkout e não concluir o pedido nesse intervalo.', 'sendora'); ?>
                            </p>
                        <?php endif; ?>

                        <?php if ($templates === [] && $meta_templates === [] && $funnels === []) : ?>
                            <p class="sendora-help">
                                <?php echo esc_html__('Nenhuma mensagem ou funil encontrado. Verifique a API Key e os dados na Sendora.', 'sendora'); ?>
                            </p>
                        <?php endif; ?>

                        <p class="sendora-woo-card__recipient">
                            <span><?php echo esc_html__('Destinatário', 'sendora'); ?></span>
                            <strong><?php echo esc_html__('Cliente do pedido', 'sendora'); ?></strong>
                        </p>

                        <?php if (!empty($meta['tracking'])) : ?>
                            <p class="sendora-help">
                                <?php echo esc_html__('Este evento usa status de envio/entrega ou código de rastreio gravado no pedido.', 'sendora'); ?>
                            </p>
                        <?php endif; ?>

                        <div class="sendora-actions" style="margin-top:12px">
                            <?php
                            $source = 'woo';
                            $event_key = $event_key;
                            $form_id = '';
                            $test_phone = (string) ($settings['test_phone'] ?? '');
                            require SENDORA_PLUGIN_DIR . 'admin/views/partials/test-send-row.php';
                            ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="sendora-actions">
            <button type="submit" class="sendora-btn sendora-btn--primary"><?php echo esc_html__('Salvar', 'sendora'); ?></button>
        </div>
    </form>
<?php endif; ?>
