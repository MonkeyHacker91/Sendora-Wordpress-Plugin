<?php
/**
 * Fluent-style admin layout shell.
 *
 * @var string $current
 * @var string $page_file
 */

if (!defined('ABSPATH')) {
    exit;
}

$sendora_nav = [
    'sendora' => __('Visão geral', 'sendora'),
    'sendora-connection' => __('Conexão', 'sendora'),
    'sendora-forms' => __('Formulários', 'sendora'),
    'sendora-widget' => __('Widget', 'sendora'),
    'sendora-woocommerce' => __('WooCommerce', 'sendora'),
    'sendora-automations' => __('Automações', 'sendora'),
    'sendora-settings' => __('Configurações', 'sendora'),
    'sendora-logs' => __('Logs', 'sendora'),
];

$status = class_exists('Sendora_Connection') ? Sendora_Connection::get_status() : ['connected' => false];
?>
<div class="sendora-app wrap">
    <header class="sendora-topbar">
        <div class="sendora-topbar__brand">
            <span class="sendora-topbar__mark" aria-hidden="true">S</span>
            <div>
                <strong class="sendora-topbar__name"><?php echo esc_html__('Sendora', 'sendora'); ?></strong>
                <span class="sendora-topbar__tag"><?php echo esc_html__('WordPress', 'sendora'); ?></span>
            </div>
        </div>
        <div class="sendora-topbar__status">
            <?php if (!empty($status['connected'])) : ?>
                <span class="sendora-badge sendora-badge--ok"><?php echo esc_html__('Conectado', 'sendora'); ?></span>
                <span class="sendora-topbar__workspace"><?php echo esc_html(Sendora_Connection::workspace_label()); ?></span>
            <?php else : ?>
                <span class="sendora-badge sendora-badge--off"><?php echo esc_html__('Desconectado', 'sendora'); ?></span>
            <?php endif; ?>
        </div>
    </header>

    <nav class="sendora-nav" aria-label="<?php echo esc_attr__('Sendora', 'sendora'); ?>">
        <?php foreach ($sendora_nav as $slug => $label) : ?>
            <a class="sendora-nav__link<?php echo $current === $slug ? ' is-active' : ''; ?>"
                href="<?php echo esc_url(admin_url('admin.php?page=' . $slug)); ?>">
                <?php echo esc_html($label); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <main class="sendora-main">
        <?php
        if (!empty($page_file) && is_readable($page_file)) {
            require $page_file;
        }
        ?>
    </main>
</div>
