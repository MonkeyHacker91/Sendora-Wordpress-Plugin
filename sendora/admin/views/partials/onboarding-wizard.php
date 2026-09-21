<?php
/**
 * First-visit onboarding wizard (plugin admin only).
 *
 * @var array<string, mixed> $settings
 * @var array{completed: bool, dismissed: bool, channels_seen: bool, test_sent: bool, wizard_step: int} $onboarding
 * @var int $qr_count
 * @var int $meta_count
 */

if (!defined('ABSPATH')) {
    exit;
}

$step = max(1, min(4, (int) ($onboarding['wizard_step'] ?? 1)));
$forced_step = Sendora_Onboarding::request_wizard_step();
if ($forced_step !== null) {
    $step = $forced_step;
}
$test_phone = (string) ($settings['test_phone'] ?? '');
$qr_count = (int) ($qr_count ?? 0);
$meta_count = (int) ($meta_count ?? 0);
?>
<div class="sendora-onboard" id="sendora-onboard-wizard" data-step="<?php echo esc_attr((string) $step); ?>" role="dialog" aria-modal="true" aria-labelledby="sendora-onboard-title">
    <div class="sendora-onboard__backdrop" data-sendora-onboard-dismiss></div>
    <div class="sendora-onboard__panel">
        <header class="sendora-onboard__head">
            <p class="sendora-eyebrow"><?php echo esc_html__('Guia rápido', 'sendora'); ?></p>
            <h2 id="sendora-onboard-title"><?php echo esc_html__('Configure o Sendora neste site', 'sendora'); ?></h2>
            <p class="sendora-onboard__sub">
                <?php echo esc_html__('Só neste plugin WordPress — 4 passos para a primeira mensagem.', 'sendora'); ?>
            </p>
            <ol class="sendora-onboard__dots" aria-label="<?php echo esc_attr__('Progresso', 'sendora'); ?>">
                <?php for ($i = 1; $i <= 4; $i++) : ?>
                    <li class="<?php echo $i === $step ? 'is-active' : ($i < $step ? 'is-done' : ''); ?>"><?php echo esc_html((string) $i); ?></li>
                <?php endfor; ?>
            </ol>
        </header>

        <div class="sendora-onboard__body">
            <section class="sendora-onboard__step" data-onboard-step="1" <?php echo $step === 1 ? '' : 'hidden'; ?>>
                <h3><?php echo esc_html__('1. Conectar a API', 'sendora'); ?></h3>
                <p><?php echo esc_html__('Cole uma chave sk_ da sua conta Sendora. Escopos: contacts:write, messages:write, flows:write.', 'sendora'); ?></p>
                <a class="sendora-btn sendora-btn--primary" href="<?php echo esc_url(admin_url('admin.php?page=sendora-connection')); ?>">
                    <?php echo esc_html__('Ir para Conexão', 'sendora'); ?>
                </a>
            </section>

            <section class="sendora-onboard__step" data-onboard-step="2" <?php echo $step === 2 ? '' : 'hidden'; ?>>
                <h3><?php echo esc_html__('2. Dois tipos de mensagem', 'sendora'); ?></h3>
                <div class="sendora-onboard__compare">
                    <article class="sendora-onboard__card">
                        <h4><?php echo esc_html__('WhatsApp (QR Code)', 'sendora'); ?></h4>
                        <p><?php echo esc_html__('Modelos com variáveis {{nome}}, {{pedido}}… Criados em', 'sendora'); ?>
                            <a href="<?php echo esc_url(Sendora_Onboarding::TEMPLATES_APP_URL); ?>" target="_blank" rel="noopener noreferrer">app.sendora.com.br/templates</a>.
                        </p>
                        <p class="sendora-help">
                            <?php
                            echo $qr_count > 0
                                ? esc_html(sprintf(
                                    /* translators: %d: count */
                                    _n('%d modelo disponível', '%d modelos disponíveis', $qr_count, 'sendora'),
                                    $qr_count
                                ))
                                : esc_html__('Nenhum modelo ainda — crie no app e volte aqui.', 'sendora');
                            ?>
                        </p>
                    </article>
                    <article class="sendora-onboard__card">
                        <h4><?php echo esc_html__('WhatsApp Oficial (Meta)', 'sendora'); ?></h4>
                        <p><?php echo esc_html__('Templates aprovados na WABA (HSM). No plugin, escolha o canal Meta no card.', 'sendora'); ?></p>
                        <p class="sendora-help">
                            <?php
                            echo $meta_count > 0
                                ? esc_html(sprintf(
                                    /* translators: %d: count */
                                    _n('%d template Meta', '%d templates Meta', $meta_count, 'sendora'),
                                    $meta_count
                                ))
                                : esc_html__('Nenhum template Meta aprovado nesta conta.', 'sendora');
                            ?>
                        </p>
                        <p>
                            <a href="<?php echo esc_url(Sendora_Onboarding::META_TEMPLATES_APP_URL); ?>" target="_blank" rel="noopener noreferrer">
                                <?php echo esc_html__('Ver templates Meta no app', 'sendora'); ?>
                            </a>
                        </p>
                    </article>
                </div>
                <button type="button" class="sendora-btn sendora-btn--primary" data-sendora-onboard-action="channels_seen">
                    <?php echo esc_html__('Entendi', 'sendora'); ?>
                </button>
            </section>

            <section class="sendora-onboard__step" data-onboard-step="3" <?php echo $step === 3 ? '' : 'hidden'; ?>>
                <h3><?php echo esc_html__('3. Ativar uma integração', 'sendora'); ?></h3>
                <p><?php echo esc_html__('Escolha um caminho — não precisa configurar tudo agora.', 'sendora'); ?></p>
                <div class="sendora-onboard__actions-row">
                    <a class="sendora-btn sendora-btn--primary" href="<?php echo esc_url(admin_url('admin.php?page=sendora-woocommerce')); ?>">
                        <?php echo esc_html__('WooCommerce', 'sendora'); ?>
                    </a>
                    <a class="sendora-btn sendora-btn--ghost" href="<?php echo esc_url(admin_url('admin.php?page=sendora-forms')); ?>">
                        <?php echo esc_html__('Formulários', 'sendora'); ?>
                    </a>
                </div>
            </section>

            <section class="sendora-onboard__step" data-onboard-step="4" <?php echo $step === 4 ? '' : 'hidden'; ?>>
                <h3><?php echo esc_html__('4. Enviar teste', 'sendora'); ?></h3>
                <p><?php echo esc_html__('Use o botão “Enviar teste” em WooCommerce ou Formulários, com um modelo/template selecionado.', 'sendora'); ?></p>
                <label class="sendora-field">
                    <span><?php echo esc_html__('Telefone de teste', 'sendora'); ?></span>
                    <input class="sendora-input" type="tel" id="sendora-onboard-test-phone"
                        value="<?php echo esc_attr($test_phone); ?>"
                        placeholder="<?php echo esc_attr__('54999004468', 'sendora'); ?>">
                </label>
                <p class="sendora-help"><?php echo esc_html__('Salvo em Configurações ao usar o teste nas telas.', 'sendora'); ?></p>
                <a class="sendora-btn sendora-btn--primary" href="<?php echo esc_url(admin_url('admin.php?page=sendora-woocommerce')); ?>">
                    <?php echo esc_html__('Ir para WooCommerce e testar', 'sendora'); ?>
                </a>
            </section>
        </div>

        <footer class="sendora-onboard__foot">
            <button type="button" class="sendora-btn sendora-btn--ghost" data-sendora-onboard-dismiss>
                <?php echo esc_html__('Finalizar', 'sendora'); ?>
            </button>
            <div class="sendora-onboard__nav">
                <button type="button" class="sendora-btn sendora-btn--ghost" data-sendora-onboard-prev <?php echo $step <= 1 ? 'hidden' : ''; ?>>
                    <?php echo esc_html__('Voltar', 'sendora'); ?>
                </button>
                <button type="button" class="sendora-btn sendora-btn--primary" data-sendora-onboard-next>
                    <?php echo $step >= 4 ? esc_html__('Concluir', 'sendora') : esc_html__('Próximo', 'sendora'); ?>
                </button>
            </div>
        </footer>
    </div>
</div>
