<?php
/**
 * Connection page.
 *
 * @var array<string, mixed> $settings
 * @var array<string, mixed> $status
 * @var string $masked_api_key
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<section class="sendora-hero">
    <div>
        <h1><?php echo esc_html__('Conexão', 'sendora'); ?></h1>
        <p><?php echo esc_html__('Conecte este WordPress à sua conta Sendora com uma chave de API (sk_).', 'sendora'); ?></p>
    </div>
</section>

<?php settings_errors(Sendora_Settings::OPTION_KEY); ?>

<form action="options.php" method="post" class="sendora-form-panel">
    <?php settings_fields('sendora_settings_group'); ?>
    <input type="hidden" name="sendora_settings[_partial]" value="connection">

    <section class="sendora-card">
        <div class="sendora-card__head">
            <h2><?php echo esc_html__('Chave de API', 'sendora'); ?></h2>
            <?php if (!empty($status['connected'])) : ?>
                <span class="sendora-badge sendora-badge--ok"><?php echo esc_html__('Conectado', 'sendora'); ?></span>
            <?php else : ?>
                <span class="sendora-badge sendora-badge--off"><?php echo esc_html__('Desconectado', 'sendora'); ?></span>
            <?php endif; ?>
        </div>

        <?php if (!empty($status['connected'])) : ?>
            <dl class="sendora-meta">
                <div><dt><?php echo esc_html__('Workspace', 'sendora'); ?></dt>
                    <dd><?php echo esc_html(Sendora_Connection::workspace_label()); ?></dd></div>
                <?php if ($status['checked_at'] !== '') : ?>
                    <div><dt><?php echo esc_html__('Última verificação', 'sendora'); ?></dt>
                        <dd><?php echo esc_html($status['checked_at']); ?></dd></div>
                <?php endif; ?>
            </dl>
        <?php endif; ?>

        <label class="sendora-field" for="sendora-api-key">
            <span><?php echo esc_html__('Chave de API', 'sendora'); ?></span>
            <input class="sendora-input" id="sendora-api-key" name="sendora_settings[api_key]" type="password"
                value="" autocomplete="new-password"
                placeholder="<?php echo esc_attr__('Cole uma chave sk_', 'sendora'); ?>">
        </label>
        <?php if ($masked_api_key !== '') : ?>
            <p class="sendora-help">
                <?php echo esc_html__('Chave salva:', 'sendora'); ?>
                <code><?php echo esc_html($masked_api_key); ?></code>.
                <?php echo esc_html__('Deixe em branco para manter a atual.', 'sendora'); ?>
            </p>
        <?php endif; ?>
        <p class="sendora-help">
            <?php echo esc_html__('Escopos recomendados: contacts:write, flows:write e messages:write se for enviar mensagens.', 'sendora'); ?>
        </p>

        <div class="sendora-actions">
            <button type="submit" class="sendora-btn sendora-btn--primary"><?php echo esc_html__('Salvar', 'sendora'); ?></button>
            <button type="button" class="sendora-btn sendora-btn--ghost" id="sendora-test-connection">
                <?php echo esc_html__('Testar conexão', 'sendora'); ?>
            </button>
            <?php if ($masked_api_key !== '') : ?>
                <button type="button" class="sendora-btn sendora-btn--danger" id="sendora-disconnect">
                    <?php echo esc_html__('Desconectar', 'sendora'); ?>
                </button>
            <?php endif; ?>
            <span id="sendora-connection-result" class="sendora-inline-status" role="status" aria-live="polite"></span>
        </div>
    </section>
</form>
