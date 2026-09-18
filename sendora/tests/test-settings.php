<?php

declare(strict_types=1);

abstract class SendoraSettingsTest extends SendoraApiClientTest
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['sendora_test_actions'] = [];
        $GLOBALS['sendora_test_registered_settings'] = [];
        $GLOBALS['sendora_test_settings_errors'] = [];
        $GLOBALS['sendora_test_json_response'] = null;
    }

    public function test_run_registers_admin_and_ajax_hooks(): void
    {
        $this->assertTrue(class_exists('Sendora_Settings'), 'Sendora_Settings must exist.');

        (new Sendora_Settings())->run();

        $this->assertArrayHasKey('admin_menu', $GLOBALS['sendora_test_actions']);
        $this->assertArrayHasKey('admin_init', $GLOBALS['sendora_test_actions']);
        $this->assertArrayHasKey('admin_enqueue_scripts', $GLOBALS['sendora_test_actions']);
        $this->assertArrayHasKey('wp_ajax_sendora_test_connection', $GLOBALS['sendora_test_actions']);
    }

    public function test_sanitize_accepts_https_and_sk_key(): void
    {
        $this->assertTrue(class_exists('Sendora_Settings'), 'Sendora_Settings must exist.');

        $settings = Sendora_Settings::sanitize([
            'api_base' => 'https://api.example.com/',
            'api_key' => 'sk_new_key',
            'widget_enabled' => '1',
            'widget_id' => ' widget-1 ',
            'default_flow_id' => 'flow-1',
            'default_cc' => '+55',
            'woo_on_created' => '1',
            'woo_on_paid' => '1',
            'woo_on_cancelled' => '1',
            'woo_created_mode' => 'contact_and_flow',
            'woo_paid_flow_id' => 'flow-paid',
            'woo_paid_mode' => 'flow',
        ]);

        $this->assertSame('https://api.example.com', $settings['api_base']);
        $this->assertSame('sk_new_key', $settings['api_key']);
        $this->assertTrue($settings['widget_enabled']);
        $this->assertSame('widget-1', $settings['widget_id']);
        $this->assertSame('55', $settings['default_cc']);
        $this->assertSame('contact_and_flow', $settings['woo_created_mode']);
        $this->assertSame('flow', $settings['woo_paid_mode']);
    }

    public function test_sanitize_rejects_http_and_non_sk_key_without_overwriting_saved_values(): void
    {
        $GLOBALS['sendora_test_options']['sendora_settings'] = [
            'api_base' => 'https://api.sendora.com.br',
            'api_key' => 'sk_existing_key',
        ];

        $settings = Sendora_Settings::sanitize([
            'api_base' => 'http://insecure.example.com',
            'api_key' => 'not-a-sendora-key',
            'woo_paid_mode' => 'invalid',
        ]);

        $this->assertSame('https://api.sendora.com.br', $settings['api_base']);
        $this->assertSame('sk_existing_key', $settings['api_key']);
        $this->assertSame('off', $settings['woo_paid_mode']);
        $this->assertSame('off', $settings['woo_created_mode']);
        $this->assertCount(2, $GLOBALS['sendora_test_settings_errors']);
    }

    public function test_mask_api_key_only_reveals_last_four_characters(): void
    {
        $this->assertSame('••••••••cdef', Sendora_Settings::mask_api_key('sk_abcdef'));
        $this->assertSame('', Sendora_Settings::mask_api_key(''));
    }

    public function test_sanitize_normalizes_cf7_mapping_rows(): void
    {
        $settings = Sendora_Settings::sanitize([
            'api_base' => 'https://api.sendora.com.br',
            'cf7_mappings' => [
                '17' => [
                    'form_id' => '17',
                    'name' => ' your-name ',
                    'phone' => 'your-phone<script>',
                    'email' => 'your_email',
                ],
                'invalid' => [
                    'form_id' => 'not-a-form',
                    'phone' => 'phone',
                ],
            ],
        ]);

        $this->assertSame([
            '17' => [
                'form_id' => '17',
                'name' => 'your-name',
                'phone' => 'your-phonescript',
                'email' => 'your_email',
            ],
        ], $settings['cf7_mappings']);
    }

    public function test_sanitize_preserves_cf7_mappings_when_cf7_fields_are_absent(): void
    {
        $mapping = [
            '17' => [
                'form_id' => '17',
                'name' => 'your-name',
                'phone' => 'your-phone',
                'email' => 'your-email',
            ],
        ];
        $GLOBALS['sendora_test_options']['sendora_settings']['cf7_mappings'] = $mapping;

        $settings = Sendora_Settings::sanitize([
            'api_base' => 'https://api.sendora.com.br',
        ]);

        $this->assertSame($mapping, $settings['cf7_mappings']);
    }

    public function test_ajax_connection_returns_only_status_and_message(): void
    {
        $GLOBALS['sendora_test_http_handler'] = fn (): array => [
            'response' => ['code' => 200],
            'body' => '[]',
        ];

        try {
            Sendora_Settings::ajax_test_connection();
            $this->fail('Expected wp_send_json to end the request.');
        } catch (RuntimeException $exception) {
            $this->assertSame('sendora_test_json_complete', $exception->getMessage());
        }

        $this->assertSame(
            ['ok' => true, 'message' => 'Connected to Sendora.'],
            $GLOBALS['sendora_test_json_response']
        );
        $this->assertStringNotContainsString(
            'sk_test_key',
            json_encode($GLOBALS['sendora_test_json_response'], JSON_THROW_ON_ERROR)
        );
    }
}
