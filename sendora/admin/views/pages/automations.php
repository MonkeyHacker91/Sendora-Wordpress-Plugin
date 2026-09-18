<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<section class="sendora-hero">
    <div>
        <h1><?php echo esc_html__('Automações', 'sendora'); ?></h1>
        <p><?php echo esc_html__('Gatilho → ação Sendora (fluxos e templates), no estilo FluentCRM.', 'sendora'); ?></p>
    </div>
</section>

<section class="sendora-card sendora-card--coming">
    <h2><?php echo esc_html__('Em construção', 'sendora'); ?></h2>
    <p>
        <?php echo esc_html__('Aqui você vai criar regras como: “Pedido pago → enviar template X” ou “Formulário Y → disparar fluxo Z”.', 'sendora'); ?>
    </p>
    <p class="sendora-help">
        <?php echo esc_html__('Enquanto isso, use Formulários e WooCommerce para as ações básicas. A base de eventos já está no plugin.', 'sendora'); ?>
    </p>
</section>
