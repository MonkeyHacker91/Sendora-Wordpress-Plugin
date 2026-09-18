<?php
/**
 * Dashboard — visão geral.
 *
 * @var array<string, mixed> $status
 * @var array<string, mixed> $settings
 * @var array<int, array<string, mixed>> $logs
 * @var array<int, array{label: string, active: bool}> $integrations
 */

if (!defined('ABSPATH')) {
    exit;
}

$connected = !empty($status['connected']);
$error_logs = array_values(array_filter($logs, static fn ($row) => ($row['level'] ?? '') === 'error'));
$last_event = $logs[0] ?? null;
?>
<section class="sendora-hero">
    <div>
        <h1><?php echo esc_html__('Visão geral', 'sendora'); ?></h1>
        <p><?php echo esc_html__('Status da integração deste site com a Sendora.', 'sendora'); ?></p>
    </div>
    <div class="sendora-hero__actions">
        <button type="button" class="sendora-btn sendora-btn--primary" id="sendora-test-connection">
            <?php echo esc_html__('Testar conexão', 'sendora'); ?>
        </button>
        <button type="button" class="sendora-btn sendora-btn--ghost" id="sendora-test-event">
            <?php echo esc_html__('Enviar evento de teste', 'sendora'); ?>
        </button>
    </div>
</section>

<p id="sendora-connection-result" class="sendora-inline-status" role="status" aria-live="polite"></p>

<div class="sendora-grid sendora-grid--stats">
    <article class="sendora-card sendora-stat">
        <span class="sendora-stat__label"><?php echo esc_html__('Conexão', 'sendora'); ?></span>
        <strong class="sendora-stat__value">
            <?php echo esc_html($connected ? __('Ativa', 'sendora') : __('Inativa', 'sendora')); ?>
        </strong>
        <span class="sendora-stat__meta"><?php echo esc_html(Sendora_Connection::workspace_label()); ?></span>
    </article>
    <article class="sendora-card sendora-stat">
        <span class="sendora-stat__label"><?php echo esc_html__('Último evento', 'sendora'); ?></span>
        <strong class="sendora-stat__value">
            <?php
            echo esc_html(
                $last_event
                    ? (string) $last_event['message']
                    : __('Nenhum ainda', 'sendora')
            );
            ?>
        </strong>
        <span class="sendora-stat__meta">
            <?php echo $last_event ? esc_html((string) $last_event['created_at']) : '—'; ?>
        </span>
    </article>
    <article class="sendora-card sendora-stat">
        <span class="sendora-stat__label"><?php echo esc_html__('Eventos recentes', 'sendora'); ?></span>
        <strong class="sendora-stat__value"><?php echo esc_html((string) count($logs)); ?></strong>
        <span class="sendora-stat__meta"><?php echo esc_html__('Últimos registros locais', 'sendora'); ?></span>
    </article>
    <article class="sendora-card sendora-stat">
        <span class="sendora-stat__label"><?php echo esc_html__('Erros recentes', 'sendora'); ?></span>
        <strong class="sendora-stat__value"><?php echo esc_html((string) count($error_logs)); ?></strong>
        <span class="sendora-stat__meta">
            <a href="<?php echo esc_url(admin_url('admin.php?page=sendora-logs')); ?>">
                <?php echo esc_html__('Ver logs', 'sendora'); ?>
            </a>
        </span>
    </article>
</div>

<section class="sendora-card">
    <div class="sendora-card__head">
        <h2><?php echo esc_html__('Integrações', 'sendora'); ?></h2>
    </div>
    <ul class="sendora-integration-list">
        <?php foreach ($integrations as $item) : ?>
            <li>
                <span><?php echo esc_html($item['label']); ?></span>
                <?php if (!empty($item['active'])) : ?>
                    <span class="sendora-badge sendora-badge--ok"><?php echo esc_html__('Ativa', 'sendora'); ?></span>
                <?php else : ?>
                    <span class="sendora-badge sendora-badge--off"><?php echo esc_html__('Off', 'sendora'); ?></span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<?php if ($error_logs !== []) : ?>
    <section class="sendora-card">
        <div class="sendora-card__head">
            <h2><?php echo esc_html__('Erros recentes', 'sendora'); ?></h2>
        </div>
        <ul class="sendora-simple-list">
            <?php foreach (array_slice($error_logs, 0, 5) as $row) : ?>
                <li>
                    <code><?php echo esc_html((string) $row['created_at']); ?></code>
                    <?php echo esc_html((string) $row['message']); ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>
