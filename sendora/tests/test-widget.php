<?php

declare(strict_types=1);

abstract class SendoraWidgetTest extends SendoraCf7Test
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['sendora_test_registered_scripts'] = [];
        $GLOBALS['sendora_test_enqueued_scripts'] = [];
        $GLOBALS['sendora_test_inline_scripts'] = [];
        $GLOBALS['sendora_test_is_admin'] = false;
        $GLOBALS['sendora_test_is_feed'] = false;
        $GLOBALS['sendora_test_is_preview'] = false;
    }

    public function test_run_registers_wp_enqueue_scripts_hook(): void
    {
        $this->assertTrue(class_exists('Sendora_Widget'), 'Sendora_Widget must exist.');

        (new Sendora_Widget())->run();

        $this->assertArrayHasKey('wp_enqueue_scripts', $GLOBALS['sendora_test_actions']);
    }

    public function test_enqueue_embed_registers_official_script_when_enabled(): void
    {
        $widget_id = 'a1b2c3d4-e5f6-4890-abcd-ef1234567890';
        $GLOBALS['sendora_test_options']['sendora_settings'] = array_merge(
            Sendora_Settings::get_settings(),
            [
                'widget_enabled' => true,
                'widget_id' => $widget_id,
                'api_base' => 'https://api.sendora.com.br',
            ]
        );

        Sendora_Widget::enqueue_embed();

        $expected_url = 'https://api.sendora.com.br/public/widget/embed?id='
            . $widget_id
            . '&v=6';

        $this->assertArrayHasKey('sendora-widget', $GLOBALS['sendora_test_enqueued_scripts']);
        $enqueued = $GLOBALS['sendora_test_enqueued_scripts']['sendora-widget'];
        $this->assertSame($expected_url, $enqueued['src']);
        $this->assertStringContainsString('&v=6', $enqueued['src']);
        $this->assertStringNotContainsString('&#038;', $enqueued['src']);
        $this->assertTrue($enqueued['args']);
    }

    public function test_enqueue_embed_is_silent_when_disabled_or_missing_id(): void
    {
        $GLOBALS['sendora_test_options']['sendora_settings'] = array_merge(
            Sendora_Settings::get_settings(),
            [
                'widget_enabled' => false,
                'widget_id' => 'widget-1',
            ]
        );

        Sendora_Widget::enqueue_embed();
        $this->assertArrayNotHasKey('sendora-widget', $GLOBALS['sendora_test_enqueued_scripts']);

        $GLOBALS['sendora_test_options']['sendora_settings']['widget_enabled'] = true;
        $GLOBALS['sendora_test_options']['sendora_settings']['widget_id'] = '';

        Sendora_Widget::enqueue_embed();
        $this->assertArrayNotHasKey('sendora-widget', $GLOBALS['sendora_test_enqueued_scripts']);
    }

    public function test_sanitize_widget_id_strips_query_junk(): void
    {
        $this->assertSame(
            '8ab21a48-c6e2-4cea-99ef-56f18eb8d4c3',
            Sendora_Widget::sanitize_widget_id('8ab21a48-c6e2-4cea-99ef-56f18eb8d4c3&v=6')
        );
        $this->assertSame(
            '8ab21a48-c6e2-4cea-99ef-56f18eb8d4c3',
            Sendora_Widget::sanitize_widget_id(
                'https://api.sendora.com.br/public/widget/embed?id=8ab21a48-c6e2-4cea-99ef-56f18eb8d4c3&v=6'
            )
        );
        $this->assertSame('', Sendora_Widget::sanitize_widget_id('not-a-uuid'));
    }

    public function test_should_display_respects_specific_pages(): void
    {
        $this->assertTrue(Sendora_Widget::should_display([
            'widget_display' => 'all',
            'widget_page_ids' => [],
        ]));

        $GLOBALS['sendora_test_is_page'] = true;
        $GLOBALS['sendora_test_queried_object_id'] = 42;
        $this->assertTrue(Sendora_Widget::should_display([
            'widget_display' => 'specific',
            'widget_page_ids' => [10, 42],
        ]));
        $this->assertFalse(Sendora_Widget::should_display([
            'widget_display' => 'specific',
            'widget_page_ids' => [10],
        ]));

        $GLOBALS['sendora_test_is_page'] = false;
        $this->assertFalse(Sendora_Widget::should_display([
            'widget_display' => 'specific',
            'widget_page_ids' => [42],
        ]));
    }

    public function test_enqueue_embed_rejects_unsafe_widget_id(): void
    {
        $GLOBALS['sendora_test_options']['sendora_settings'] = array_merge(
            Sendora_Settings::get_settings(),
            [
                'widget_enabled' => true,
                'widget_id' => '"><script>alert(1)</script>',
                'api_base' => 'https://api.sendora.com.br',
            ]
        );

        Sendora_Widget::enqueue_embed();
        $this->assertArrayNotHasKey('sendora-widget', $GLOBALS['sendora_test_enqueued_scripts']);
    }
}
