<?php

declare(strict_types=1);

abstract class SendoraWidgetTest extends SendoraCf7Test
{
    public function test_run_registers_wp_footer_hook(): void
    {
        $this->assertTrue(class_exists('Sendora_Widget'), 'Sendora_Widget must exist.');

        (new Sendora_Widget())->run();

        $this->assertArrayHasKey('wp_footer', $GLOBALS['sendora_test_actions']);
    }

    public function test_render_embed_outputs_official_script_when_enabled(): void
    {
        $widget_id = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
        $GLOBALS['sendora_test_options']['sendora_settings'] = array_merge(
            Sendora_Settings::get_settings(),
            [
                'widget_enabled' => true,
                'widget_id' => $widget_id,
                'api_base' => 'https://api.sendora.com.br',
            ]
        );

        ob_start();
        Sendora_Widget::render_embed();
        $html = (string) ob_get_clean();

        $expected_url = 'https://api.sendora.com.br/public/widget/embed?id='
            . $widget_id
            . '&v=6';

        $this->assertStringContainsString('Sendora Chat Widget', $html);
        $this->assertStringContainsString('__sendora_widget', $html);
        $this->assertStringContainsString(wp_json_encode($expected_url), $html);
        $this->assertStringNotContainsString('<iframe', $html);
    }

    public function test_render_embed_is_silent_when_disabled_or_missing_id(): void
    {
        $GLOBALS['sendora_test_options']['sendora_settings'] = array_merge(
            Sendora_Settings::get_settings(),
            [
                'widget_enabled' => false,
                'widget_id' => 'widget-1',
            ]
        );

        ob_start();
        Sendora_Widget::render_embed();
        $disabled = (string) ob_get_clean();

        $GLOBALS['sendora_test_options']['sendora_settings']['widget_enabled'] = true;
        $GLOBALS['sendora_test_options']['sendora_settings']['widget_id'] = '';

        ob_start();
        Sendora_Widget::render_embed();
        $missing_id = (string) ob_get_clean();

        $this->assertSame('', $disabled);
        $this->assertSame('', $missing_id);
    }
}
