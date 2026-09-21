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
            <img class="sendora-topbar__logo"
                src="<?php echo esc_url(plugins_url('admin/assets/logo-sendora.svg', SENDORA_PLUGIN_FILE)); ?>"
                alt="<?php echo esc_attr__('Sendora', 'sendora'); ?>"
                width="140"
                height="29">
            <span class="sendora-topbar__tag"><?php echo esc_html__('WordPress', 'sendora'); ?></span>
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

        if (
            class_exists('Sendora_Onboarding')
            && current_user_can('manage_options')
            && Sendora_Onboarding::is_wizard_due()
        ) {
            $ob_settings = Sendora_Settings::get_settings();
            $onboarding = Sendora_Onboarding::state($ob_settings);
            $forced_step = Sendora_Onboarding::request_wizard_step();
            if ($forced_step !== null) {
                $onboarding['wizard_step'] = $forced_step;
            }
            $qr_count = count(Sendora_Settings::load_templates_public($ob_settings));
            $meta_count = count(Sendora_Settings::load_meta_templates_public($ob_settings));
            $settings = $ob_settings;
            require SENDORA_PLUGIN_DIR . 'admin/views/partials/onboarding-wizard.php';
        }
        ?>
    </main>
</div>
