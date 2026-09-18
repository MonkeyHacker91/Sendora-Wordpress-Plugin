<?php
/**
 * Sendora logs page.
 *
 * @var array<int, array{id: int, created_at: string, source: string, level: string, message: string, context: array<string, mixed>}> $logs
 * @var bool $cleared
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap sendora-settings sendora-logs">
    <h1><?php echo esc_html__('Logs da Sendora', 'sendora'); ?></h1>
    <p><?php echo esc_html__('Eventos locais de sincronização e API deste site. Chaves de API nunca são gravadas nos logs.', 'sendora'); ?></p>

    <?php if (!empty($cleared)) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html__('Logs limpos.', 'sendora'); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" style="margin: 16px 0;">
        <?php wp_nonce_field('sendora_clear_logs'); ?>
        <?php submit_button(__('Limpar logs', 'sendora'), 'delete', 'sendora_clear_logs', false); ?>
    </form>

    <table class="widefat striped sendora-logs-table">
        <thead>
            <tr>
                <th scope="col"><?php echo esc_html__('Horário (UTC)', 'sendora'); ?></th>
                <th scope="col"><?php echo esc_html__('Origem', 'sendora'); ?></th>
                <th scope="col"><?php echo esc_html__('Nível', 'sendora'); ?></th>
                <th scope="col"><?php echo esc_html__('Mensagem', 'sendora'); ?></th>
                <th scope="col"><?php echo esc_html__('Contexto', 'sendora'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($logs === []) : ?>
                <tr>
                    <td colspan="5"><?php echo esc_html__('Nenhum registro ainda.', 'sendora'); ?></td>
                </tr>
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
                                            wp_json_encode($sendora_entry['context'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                                                ?: '{}'
                                        );
                                    ?></pre>
                                </details>
                            <?php else : ?>
                                <span aria-hidden="true">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
