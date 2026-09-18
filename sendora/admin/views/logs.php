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
    <h1><?php echo esc_html__('Sendora logs', 'sendora'); ?></h1>
    <p><?php echo esc_html__('Local sync and API events from this site. API keys are never stored in logs.', 'sendora'); ?></p>

    <?php if (!empty($cleared)) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html__('Logs cleared.', 'sendora'); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" style="margin: 16px 0;">
        <?php wp_nonce_field('sendora_clear_logs'); ?>
        <?php submit_button(__('Clear logs', 'sendora'), 'delete', 'sendora_clear_logs', false); ?>
    </form>

    <table class="widefat striped sendora-logs-table">
        <thead>
            <tr>
                <th scope="col"><?php echo esc_html__('Time (UTC)', 'sendora'); ?></th>
                <th scope="col"><?php echo esc_html__('Source', 'sendora'); ?></th>
                <th scope="col"><?php echo esc_html__('Level', 'sendora'); ?></th>
                <th scope="col"><?php echo esc_html__('Message', 'sendora'); ?></th>
                <th scope="col"><?php echo esc_html__('Context', 'sendora'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($logs === []) : ?>
                <tr>
                    <td colspan="5"><?php echo esc_html__('No log entries yet.', 'sendora'); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ($logs as $entry) : ?>
                    <tr>
                        <td><?php echo esc_html($entry['created_at']); ?></td>
                        <td><code><?php echo esc_html($entry['source']); ?></code></td>
                        <td><?php echo esc_html($entry['level']); ?></td>
                        <td><?php echo esc_html($entry['message']); ?></td>
                        <td>
                            <?php if ($entry['context'] !== []) : ?>
                                <details>
                                    <summary><?php echo esc_html__('Details', 'sendora'); ?></summary>
                                    <pre class="sendora-log-context"><?php
                                        echo esc_html(
                                            wp_json_encode($entry['context'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
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
