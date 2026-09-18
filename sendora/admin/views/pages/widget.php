<?php
/**
 * @var array<string, mixed> $settings
 * @var array<int, array{id: int, title: string}> $pages
 */

if (!defined('ABSPATH')) {
    exit;
}
$sendora_widget_display = (string) ($settings['widget_display'] ?? 'all');
$sendora_widget_page_ids = is_array($settings['widget_page_ids'] ?? null)
    ? array_map('intval', $settings['widget_page_ids'])
    : [];
?>
<section class="sendora-hero">
    <div>
        <h1><?php echo esc_html__('Widget de chat', 'sendora'); ?></h1>
        <p><?php echo esc_html__('Exibe o chat oficial da Sendora no site. Independente dos formulários.', 'sendora'); ?></p>
    </div>
</section>

<?php settings_errors(Sendora_Settings::OPTION_KEY); ?>

<form action="options.php" method="post" class="sendora-form-panel">
    <?php settings_fields('sendora_settings_group'); ?>
    <input type="hidden" name="sendora_settings[_partial]" value="widget">

    <section class="sendora-card">
        <label class="sendora-toggle">
            <input name="sendora_settings[widget_enabled]" type="checkbox" value="1" <?php checked($settings['widget_enabled']); ?>>
            <span><?php echo esc_html__('Ativar o widget da Sendora no site', 'sendora'); ?></span>
        </label>

        <label class="sendora-field" for="sendora-widget-id">
            <span><?php echo esc_html__('ID do widget', 'sendora'); ?></span>
            <input class="sendora-input" id="sendora-widget-id" name="sendora_settings[widget_id]" type="text"
                value="<?php echo esc_attr((string) $settings['widget_id']); ?>"
                placeholder="8ab21a48-c6e2-4cea-99ef-56f18eb8d4c3"
                spellcheck="false" autocomplete="off">
        </label>
        <p class="sendora-help">
            <?php echo esc_html__('Cole o UUID do widget. Se colar a URL com &v=6, o ID é extraído automaticamente.', 'sendora'); ?>
        </p>

        <fieldset class="sendora-fieldset sendora-widget-display">
            <legend><?php echo esc_html__('Onde exibir', 'sendora'); ?></legend>
            <label>
                <input type="radio" name="sendora_settings[widget_display]" value="all"
                    <?php checked($sendora_widget_display, 'all'); ?> data-sendora-widget-display="all">
                <?php echo esc_html__('Todas as páginas', 'sendora'); ?>
            </label>
            <label>
                <input type="radio" name="sendora_settings[widget_display]" value="specific"
                    <?php checked($sendora_widget_display, 'specific'); ?> data-sendora-widget-display="specific">
                <?php echo esc_html__('Apenas páginas específicas', 'sendora'); ?>
            </label>
        </fieldset>

        <div class="sendora-widget-pages" id="sendora-widget-pages"
            <?php echo $sendora_widget_display === 'specific' ? '' : ' hidden'; ?>>
            <?php if (empty($pages)) : ?>
                <p class="sendora-empty"><?php echo esc_html__('Nenhuma página publicada encontrada.', 'sendora'); ?></p>
            <?php else : ?>
                <label class="sendora-field" for="sendora-widget-page-ids">
                    <span><?php echo esc_html__('Selecione uma ou mais páginas', 'sendora'); ?></span>
                    <select id="sendora-widget-page-ids" name="sendora_settings[widget_page_ids][]" multiple size="8" class="sendora-input sendora-page-multiselect">
                        <?php foreach ($pages as $sendora_page) : ?>
                            <option value="<?php echo esc_attr((string) $sendora_page['id']); ?>"
                                <?php echo in_array((int) $sendora_page['id'], $sendora_widget_page_ids, true) ? 'selected="selected"' : ''; ?>>
                                <?php echo esc_html((string) $sendora_page['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <p class="sendora-help"><?php echo esc_html__('Segure Ctrl (Windows) ou Cmd (Mac) para selecionar várias.', 'sendora'); ?></p>
            <?php endif; ?>
        </div>
    </section>

    <div class="sendora-actions">
        <button type="submit" class="sendora-btn sendora-btn--primary"><?php echo esc_html__('Salvar', 'sendora'); ?></button>
    </div>
</form>
