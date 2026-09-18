<?php
/**
 * @var array<int, array{id: int, created_at: string, source: string, level: string, message: string, context: array<string, mixed>}> $logs
 * @var bool $cleared
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<section class="sendora-hero">
    <div>
        <h1><?php echo esc_html__('Logs', 'sendora'); ?></h1>
        <p><?php echo esc_html__('Diagnóstico local. Chaves de API nunca são gravadas aqui.', 'sendora'); ?></p>
    </div>
    <form method="post" class="sendora-hero__actions">
        <?php wp_nonce_field('sendora_clear_logs'); ?>
        <button type="submit" name="sendora_clear_logs" value="1" class="sendora-btn sendora-btn--danger">
            <?php echo esc_html__('Limpar logs', 'sendora'); ?>
        </button>
    </form>
</section>

<?php if (!empty($cleared)) : ?>
    <div class="sendora-notice sendora-notice--ok"><?php echo esc_html__('Logs limpos.', 'sendora'); ?></div>
<?php endif; ?>

<section class="sendora-card">
    <div class="sendora-table-wrap">
        <table class="sendora-table sendora-logs-table">
            <thead>
                <tr>
                    <th><?php echo esc_html__('Horário (UTC)', 'sendora'); ?></th>
                    <th><?php echo esc_html__('Origem', 'sendora'); ?></th>
                    <th><?php echo esc_html__('Nível', 'sendora'); ?></th>
                    <th><?php echo esc_html__('Mensagem', 'sendora'); ?></th>
                    <th><?php echo esc_html__('Contexto', 'sendora'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($logs === []) : ?>
                    <tr><td colspan="5"><?php echo esc_html__('Nenhum registro ainda.', 'sendora'); ?></td></tr>
                <?php else : ?>
                    <?php foreach ($logs as $sendora_entry) : ?>
                        <tr>
                            <td><?php echo esc_html($sendora_entry['created_at']); ?></td>
                            <td><code><?php echo esc_html($sendora_entry['source']); ?></code></td>
                            <td><?php echo esc_html($sendora_entry['level']); ?></td>
                            <td><?php echo esc_html($sendora_entry['message']); ?></td>
                            <td>
                                <?php if ($sendora_entry['context'] !== []) : ?>
                                    <details>
                                        <summary><?php echo esc_html__('Detalhes', 'sendora'); ?></summary>
                                        <pre class="sendora-log-context"><?php
                                            echo esc_html(
                                                wp_json_encode($sendora_entry['context'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}'
                                            );
                                        ?></pre>
                                    </details>
                                <?php else : ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
