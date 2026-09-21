<?php
/**
 * @var array<string, mixed> $settings
 * @var array<int, array{id: string, name: string}> $templates
 * @var array<int, array{id: string, name: string, language?: string}> $meta_templates
 * @var array<int, array{id: string, name: string}> $connections
 * @var array<int, array{id: string, name: string}> $wabas
 * @var array<int, array{id: string, name: string}> $flows
 * @var array<int, array{id: string, name: string}> $funnels
 * @var array<int, array{id: string, label: string, funnel_id: string}> $stages
 */

if (!defined('ABSPATH')) {
    exit;
}

$rules = is_array($settings['automations'] ?? null) ? $settings['automations'] : [];
$triggers = class_exists('Sendora_Automations') ? Sendora_Automations::trigger_catalog() : [];
$cond_fields = class_exists('Sendora_Automations') ? Sendora_Automations::condition_fields() : [];
$cond_ops = class_exists('Sendora_Automations') ? Sendora_Automations::condition_ops() : [];
$templates = $templates ?? [];
$meta_templates = $meta_templates ?? [];
$connections = $connections ?? [];
$wabas = $wabas ?? [];
$flows = $flows ?? [];
$funnels = $funnels ?? [];
$stages = $stages ?? [];

// One blank card for "add new".
$blank = [
    'id' => 'auto_new_' . substr(md5((string) microtime(true)), 0, 8),
    'name' => '',
    'enabled' => false,
    'trigger' => 'form.native',
    'conditions' => [],
    'delay_minutes' => 0,
    'actions' => [
        'channel' => 'evolution',
        'instance_id' => '',
        'template_id' => '',
        'template_language' => 'pt_BR',
        'body_vars' => [],
        'flow_id' => '',
        'funnel_id' => '',
        'stage' => '',
        'tags' => [],
    ],
];
$rules[] = $blank;
?>
<section class="sendora-hero">
    <div>
        <p class="sendora-eyebrow"><?php echo esc_html__('Automações', 'sendora'); ?></p>
        <h1><?php echo esc_html__('Regras gatilho → ação', 'sendora'); ?></h1>
        <p><?php echo esc_html__('Condições e delay no WordPress; mensagens, CRM e fluxos (IA/sequências) na Sendora.', 'sendora'); ?></p>
    </div>
</section>

<?php settings_errors(Sendora_Settings::OPTION_KEY); ?>

<form action="options.php" method="post" class="sendora-form-panel" id="sendora-automations-form">
    <?php settings_fields('sendora_settings_group'); ?>
    <input type="hidden" name="sendora_settings[_partial]" value="automations">

    <div class="sendora-woo-events">
        <?php foreach ($rules as $index => $rule) :
            $is_new = str_starts_with((string) ($rule['id'] ?? ''), 'auto_new_');
            $enabled = !empty($rule['enabled']);
            $actions = is_array($rule['actions'] ?? null) ? $rule['actions'] : [];
            $conditions = is_array($rule['conditions'] ?? null) ? $rule['conditions'] : [];
            $channel = Sendora_Outbound::normalize_provider((string) ($actions['channel'] ?? 'evolution'));
            $instance_id = (string) ($actions['instance_id'] ?? '');
            $template_id = (string) ($actions['template_id'] ?? '');
            $template_language = (string) ($actions['template_language'] ?? 'pt_BR');
            $body_vars = is_array($actions['body_vars'] ?? null) ? $actions['body_vars'] : [];
            $body_vars_str = implode(', ', $body_vars);
            $flow_id = (string) ($actions['flow_id'] ?? '');
            $funnel_id = (string) ($actions['funnel_id'] ?? '');
            $stage = (string) ($actions['stage'] ?? '');
            $tags_str = implode(', ', is_array($actions['tags'] ?? null) ? $actions['tags'] : []);
            $field_base = 'sendora_settings[automations][' . $index . ']';
            $message_required = false;
            $active_templates = $channel === 'meta' ? $meta_templates : $templates;
            ?>
            <article class="sendora-woo-card<?php echo $enabled ? ' is-enabled' : ''; ?>" data-sendora-woo-card data-sendora-auto-card>
                <header class="sendora-woo-card__head">
                    <div class="sendora-woo-card__title">
                        <span class="sendora-woo-card__icon" aria-hidden="true">⚡</span>
                        <div>
                            <h3>
                                <?php
                                echo $is_new
                                    ? esc_html__('Nova automação', 'sendora')
                                    : esc_html((string) ($rule['name'] !== '' ? $rule['name'] : __('Automação', 'sendora')));
                                ?>
                            </h3>
                            <p><?php echo esc_html__('Ative, escolha o gatilho e as ações Sendora.', 'sendora'); ?></p>
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

                <div class="sendora-woo-card__body" data-sendora-woo-body <?php echo $enabled || $is_new ? '' : 'hidden'; ?>>
                    <input type="hidden" name="<?php echo esc_attr($field_base); ?>[id]" value="<?php echo esc_attr((string) $rule['id']); ?>">

                    <label class="sendora-field">
                        <span><?php echo esc_html__('Nome', 'sendora'); ?></span>
                        <input class="sendora-input" type="text" name="<?php echo esc_attr($field_base); ?>[name]"
                            value="<?php echo esc_attr((string) ($rule['name'] ?? '')); ?>"
                            placeholder="<?php echo esc_attr__('Ex.: Pedido pago → boas-vindas', 'sendora'); ?>">
                    </label>

                    <label class="sendora-field">
                        <span><?php echo esc_html__('Gatilho', 'sendora'); ?></span>
                        <select class="sendora-input" name="<?php echo esc_attr($field_base); ?>[trigger]">
                            <?php foreach ($triggers as $tkey => $tmeta) : ?>
                                <option value="<?php echo esc_attr($tkey); ?>" <?php selected((string) ($rule['trigger'] ?? ''), $tkey); ?>>
                                    <?php echo esc_html((string) $tmeta['label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="sendora-field">
                        <span><?php echo esc_html__('Delay (minutos)', 'sendora'); ?></span>
                        <input class="sendora-input sendora-input--sm" type="number" min="0" max="10080"
                            name="<?php echo esc_attr($field_base); ?>[delay_minutes]"
                            value="<?php echo esc_attr((string) (int) ($rule['delay_minutes'] ?? 0)); ?>">
                    </label>
                    <p class="sendora-help"><?php echo esc_html__('0 = imediato. Usa Action Scheduler / cron do WordPress.', 'sendora'); ?></p>

                    <h3 class="sendora-section-title" style="margin-top:16px"><?php echo esc_html__('Condição (opcional)', 'sendora'); ?></h3>
                    <?php
                    $cond = $conditions[0] ?? ['field' => '', 'op' => 'eq', 'value' => ''];
                    $cbase = $field_base . '[conditions][0]';
                    ?>
                    <div class="sendora-grid--stats" style="margin-bottom:12px">
                        <label class="sendora-field">
                            <span><?php echo esc_html__('Campo', 'sendora'); ?></span>
                            <select class="sendora-input" name="<?php echo esc_attr($cbase); ?>[field]">
                                <option value=""><?php echo esc_html__('Sem condição', 'sendora'); ?></option>
                                <?php foreach ($cond_fields as $fkey => $flabel) : ?>
                                    <option value="<?php echo esc_attr($fkey); ?>" <?php selected((string) ($cond['field'] ?? ''), $fkey); ?>>
                                        <?php echo esc_html($flabel); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="sendora-field">
                            <span><?php echo esc_html__('Operador', 'sendora'); ?></span>
                            <select class="sendora-input" name="<?php echo esc_attr($cbase); ?>[op]">
                                <?php foreach ($cond_ops as $okey => $olabel) : ?>
                                    <option value="<?php echo esc_attr($okey); ?>" <?php selected((string) ($cond['op'] ?? 'eq'), $okey); ?>>
                                        <?php echo esc_html($olabel); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="sendora-field">
                            <span><?php echo esc_html__('Valor', 'sendora'); ?></span>
                            <input class="sendora-input" type="text" name="<?php echo esc_attr($cbase); ?>[value]"
                                value="<?php echo esc_attr((string) ($cond['value'] ?? '')); ?>">
                        </label>
                    </div>

                    <h3 class="sendora-section-title"><?php echo esc_html__('Ações Sendora', 'sendora'); ?></h3>
                    <?php
                    $field_base = $field_base . '[actions]';
                    require SENDORA_PLUGIN_DIR . 'admin/views/partials/message-channel-fields.php';
                    $field_base = 'sendora_settings[automations][' . $index . ']';
                    ?>

                    <label class="sendora-field">
                        <span><?php echo esc_html__('Fluxo Sendora (opcional)', 'sendora'); ?></span>
                        <select class="sendora-input" name="<?php echo esc_attr($field_base); ?>[actions][flow_id]">
                            <option value=""><?php echo esc_html__('Nenhum', 'sendora'); ?></option>
                            <?php foreach ($flows as $flow) : ?>
                                <option value="<?php echo esc_attr($flow['id']); ?>" <?php selected($flow_id, $flow['id']); ?>>
                                    <?php echo esc_html($flow['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <p class="sendora-help"><?php echo esc_html__('Use o fluxo para IA, sequências e lógica rica na Sendora.', 'sendora'); ?></p>

                    <label class="sendora-field">
                        <span><?php echo esc_html__('Funil (CRM)', 'sendora'); ?></span>
                        <select class="sendora-input" name="<?php echo esc_attr($field_base); ?>[actions][funnel_id]" data-sendora-woo-funnel>
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
                        <select class="sendora-input" name="<?php echo esc_attr($field_base); ?>[actions][stage]" data-sendora-woo-stage data-selected="<?php echo esc_attr($stage); ?>">
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
                        <input class="sendora-input" type="text" name="<?php echo esc_attr($field_base); ?>[actions][tags]"
                            value="<?php echo esc_attr($tags_str); ?>"
                            placeholder="<?php echo esc_attr__('auto, lead', 'sendora'); ?>">
                    </label>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <div class="sendora-actions">
        <button type="submit" class="sendora-btn sendora-btn--primary"><?php echo esc_html__('Salvar automações', 'sendora'); ?></button>
    </div>
</form>
