<?php
/**
 * @var array<string, mixed> $settings
 * @var array<int, array{id: string, name: string}> $flows
 * @var bool $woo_active
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<section class="sendora-hero">
    <div>
        <h1><?php echo esc_html__('WooCommerce', 'sendora'); ?></h1>
        <p><?php echo esc_html__('Sincronize pedidos e dispare fluxos ou mensagens na Sendora.', 'sendora'); ?></p>
    </div>
</section>

<?php if (empty($woo_active)) : ?>
    <section class="sendora-card">
        <p class="sendora-empty"><?php echo esc_html__('WooCommerce não está ativo neste site.', 'sendora'); ?></p>
    </section>
<?php else : ?>
    <?php settings_errors(Sendora_Settings::OPTION_KEY); ?>
    <form action="options.php" method="post" class="sendora-form-panel">
        <?php settings_fields('sendora_settings_group'); ?>
        <input type="hidden" name="sendora_settings[_partial]" value="woo">

        <section class="sendora-card">
            <div class="sendora-card__head"><h2><?php echo esc_html__('Pedido criado', 'sendora'); ?></h2></div>
            <label class="sendora-field" for="sendora-created-mode">
                <span><?php echo esc_html__('Ação', 'sendora'); ?></span>
                <select class="sendora-input" id="sendora-created-mode" name="sendora_settings[woo_created_mode]">
                    <option value="off" <?php selected($settings['woo_created_mode'], 'off'); ?>><?php echo esc_html__('Desligado', 'sendora'); ?></option>
                    <option value="contact_only" <?php selected($settings['woo_created_mode'], 'contact_only'); ?>><?php echo esc_html__('Só sincronizar contato', 'sendora'); ?></option>
                    <option value="contact_and_flow" <?php selected($settings['woo_created_mode'], 'contact_and_flow'); ?>><?php echo esc_html__('Contato + fluxo padrão (Formulários)', 'sendora'); ?></option>
                </select>
            </label>
        </section>

        <section class="sendora-card">
            <div class="sendora-card__head"><h2><?php echo esc_html__('Pagamento concluído', 'sendora'); ?></h2></div>
            <label class="sendora-toggle">
                <input name="sendora_settings[woo_on_paid]" type="checkbox" value="1" <?php checked($settings['woo_on_paid']); ?>>
                <span><?php echo esc_html__('Ativar neste evento', 'sendora'); ?></span>
            </label>
            <label class="sendora-field" for="sendora-paid-mode">
                <span><?php echo esc_html__('Ação', 'sendora'); ?></span>
                <select class="sendora-input" id="sendora-paid-mode" name="sendora_settings[woo_paid_mode]">
                    <option value="off" <?php selected($settings['woo_paid_mode'], 'off'); ?>><?php echo esc_html__('Desligado', 'sendora'); ?></option>
                    <option value="flow" <?php selected($settings['woo_paid_mode'], 'flow'); ?>><?php echo esc_html__('Disparar fluxo', 'sendora'); ?></option>
                    <option value="message" <?php selected($settings['woo_paid_mode'], 'message'); ?>><?php echo esc_html__('Enviar mensagem (texto fixo por enquanto)', 'sendora'); ?></option>
                </select>
            </label>
            <label class="sendora-field" for="sendora-paid-flow">
                <span><?php echo esc_html__('Fluxo', 'sendora'); ?></span>
                <select class="sendora-input" id="sendora-paid-flow" name="sendora_settings[woo_paid_flow_id]">
                    <option value=""><?php echo esc_html__('Selecione um fluxo', 'sendora'); ?></option>
                    <?php foreach ($flows as $sendora_flow) : ?>
                        <option value="<?php echo esc_attr($sendora_flow['id']); ?>" <?php selected($settings['woo_paid_flow_id'], $sendora_flow['id']); ?>>
                            <?php echo esc_html($sendora_flow['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <p class="sendora-help">
                <?php echo esc_html__('Em breve: escolher template da Sendora por status (estilo automações).', 'sendora'); ?>
            </p>
        </section>

        <section class="sendora-card">
            <div class="sendora-card__head"><h2><?php echo esc_html__('Pedido cancelado', 'sendora'); ?></h2></div>
            <label class="sendora-toggle">
                <input name="sendora_settings[woo_on_cancelled]" type="checkbox" value="1" <?php checked($settings['woo_on_cancelled']); ?>>
                <span><?php echo esc_html__('Sincronizar contato ao cancelar', 'sendora'); ?></span>
            </label>
        </section>

        <div class="sendora-actions">
            <button type="submit" class="sendora-btn sendora-btn--primary"><?php echo esc_html__('Salvar', 'sendora'); ?></button>
        </div>
    </form>
<?php endif; ?>
