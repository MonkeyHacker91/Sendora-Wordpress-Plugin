<?php
/**
 * Dashboard onboarding checklist (only while onboarding is open).
 *
 * @var array<int, array{id: string, label: string, done: bool, url: string, cta: string}> $onboard_steps
 */

if (!defined('ABSPATH')) {
    exit;
}

$onboard_steps = $onboard_steps ?? [];
$done_count = count(array_filter($onboard_steps, static fn ($s) => !empty($s['done'])));
$total = count($onboard_steps);
?>
<section class="sendora-card sendora-onboard-check" id="sendora-onboard-checklist">
    <div class="sendora-card__head">
        <div>
            <h2><?php echo esc_html__('Primeiros passos', 'sendora'); ?></h2>
            <p class="sendora-help" style="margin:4px 0 0">
                <?php
                echo esc_html(sprintf(
                    /* translators: 1: done, 2: total */
                    __('%1$d de %2$d concluídos', 'sendora'),
                    $done_count,
                    $total
                ));
                ?>
            </p>
        </div>
        <div class="sendora-onboard-check__actions">
            <button type="button" class="sendora-btn sendora-btn--ghost sendora-btn--sm" data-sendora-onboard-action="dismiss">
                <?php echo esc_html__('Finalizar', 'sendora'); ?>
            </button>
        </div>
    </div>
    <ul class="sendora-onboard-check__list">
        <?php foreach ($onboard_steps as $row) : ?>
            <li class="<?php echo !empty($row['done']) ? 'is-done' : ''; ?>">
                <span class="sendora-onboard-check__mark" aria-hidden="true">
                    <?php echo !empty($row['done']) ? '✓' : '○'; ?>
                </span>
                <span class="sendora-onboard-check__label"><?php echo esc_html((string) $row['label']); ?></span>
                <?php if (empty($row['done'])) : ?>
                    <a class="sendora-onboard-check__cta" href="<?php echo esc_url((string) $row['url']); ?>"
                        <?php echo str_starts_with((string) $row['url'], 'http') ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
                        <?php echo esc_html((string) $row['cta']); ?>
                    </a>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
