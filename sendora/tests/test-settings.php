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

    public function test_run_registers_settings_hooks(): void
    {
        $this->assertTrue(class_exists('Sendora_Settings'), 'Sendora_Settings must exist.');

        (new Sendora_Settings())->run();

        $this->assertArrayHasKey('admin_init', $GLOBALS['sendora_test_actions']);
        $this->assertArrayNotHasKey('admin_menu', $GLOBALS['sendora_test_actions']);
    }

    public function test_admin_registers_menu_and_ajax_hooks(): void
    {
        $this->assertTrue(class_exists('Sendora_Admin'), 'Sendora_Admin must exist.');

        (new Sendora_Admin())->run();

        $this->assertArrayHasKey('admin_menu', $GLOBALS['sendora_test_actions']);
        $this->assertArrayHasKey('wp_ajax_sendora_test_connection', $GLOBALS['sendora_test_actions']);
        $this->assertArrayHasKey('wp_ajax_sendora_disconnect', $GLOBALS['sendora_test_actions']);
        $this->assertArrayHasKey('wp_ajax_sendora_test_send', $GLOBALS['sendora_test_actions']);
    }

    public function test_sanitize_accepts_https_and_sk_key(): void
    {
        $this->assertTrue(class_exists('Sendora_Settings'), 'Sendora_Settings must exist.');

        $settings = Sendora_Settings::sanitize([
            'api_base' => 'https://api.example.com/',
            'api_key' => 'sk_new_key',
            'widget_enabled' => '1',
            'widget_id' => ' a1b2c3d4-e5f6-4890-abcd-ef1234567890&v=6 ',
            'default_flow_id' => 'flow-1',
            'default_cc' => '+55',
            'woo_on_created' => '1',
            'woo_on_paid' => '1',
            'woo_on_cancelled' => '1',
            'woo_created_mode' => 'contact_and_flow',
            'woo_paid_flow_id' => 'flow-paid',
            'woo_paid_mode' => 'flow',
        ]);

        $this->assertSame('https://api.sendora.com.br', $settings['api_base']);
        $this->assertSame('sk_new_key', $settings['api_key']);
        $this->assertTrue($settings['widget_enabled']);
        $this->assertSame('a1b2c3d4-e5f6-4890-abcd-ef1234567890', $settings['widget_id']);
        $this->assertSame('55', $settings['default_cc']);
        $this->assertSame('contact_and_flow', $settings['woo_created_mode']);
        $this->assertSame('flow', $settings['woo_paid_mode']);
    }

    public function test_sanitize_extracts_widget_uuid_from_url(): void
    {
        $settings = Sendora_Settings::sanitize([
            'widget_id' => 'https://api.sendora.com.br/public/widget/embed?id=a1b2c3d4-e5f6-4890-abcd-ef1234567890&v=6',
            'widget_enabled' => '1',
        ]);

        $this->assertSame('a1b2c3d4-e5f6-4890-abcd-ef1234567890', $settings['widget_id']);
    }

    public function test_sanitize_widget_display_and_page_ids(): void
    {
        $settings = Sendora_Settings::sanitize([
            'widget_display' => 'specific',
            'widget_page_ids' => ['12', '0', '12', '5', 'abc'],
        ]);

        $this->assertSame('specific', $settings['widget_display']);
        $this->assertSame([5, 12], $settings['widget_page_ids']);
    }

    public function test_list_pages_for_widget_shows_titles_not_bare_ids(): void
    {
        $GLOBALS['sendora_test_pages'] = [
            (object) ['ID' => 10, 'post_title' => 'Sobre nós', 'post_name' => 'sobre-nos'],
            (object) ['ID' => 3487, 'post_title' => '', 'post_name' => '3487-2'],
            (object) ['ID' => 99, 'post_title' => '', 'post_name' => ''],
        ];

        $pages = Sendora_Settings::list_pages_for_widget();

        $this->assertSame('Sobre nós (#10)', $pages[0]['title']);
        $this->assertSame('(sem título) (#3487)', $pages[1]['title']);
        $this->assertSame('(sem título) (#99)', $pages[2]['title']);
    }

    public function test_sanitize_rejects_http_and_non_sk_key_without_overwriting_saved_values(): void
    {
        $GLOBALS['sendora_test_options']['sendora_settings'] = [
            'api_base' => 'https://evil.example.com',
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
        $this->assertCount(1, $GLOBALS['sendora_test_settings_errors']);
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
                'enabled' => false,
                'channel' => 'evolution',
                'instance_id' => '',
                'template_id' => '',
                'template_language' => 'pt_BR',
                'body_vars' => [],
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
            'body' => wp_json_encode([
                'ok' => true,
                'data' => [
                    'user_id' => 'u1',
                    'company_name' => 'Acme',
                    'full_name' => 'Ada',
                    'billing_email' => 'ada@example.com',
                ],
            ]),
        ];

        try {
            Sendora_Admin::ajax_test_connection();
            $this->fail('Expected wp_send_json to end the request.');
        } catch (RuntimeException $exception) {
            $this->assertSame('sendora_test_json_complete', $exception->getMessage());
        }

        $response = $GLOBALS['sendora_test_json_response'];
        $this->assertIsArray($response);
        $this->assertTrue($response['ok']);
        $this->assertSame('Conectado à Sendora.', $response['message']);
        $this->assertSame('Acme', $response['workspace']);
        $this->assertStringNotContainsString(
            'sk_test_key',
            json_encode($response, JSON_THROW_ON_ERROR)
        );
    }

    public function test_partial_connection_sanitize_keeps_other_settings(): void
    {
        $GLOBALS['sendora_test_options']['sendora_settings'] = array_merge(
            Sendora_Settings::get_settings(),
            [
                'api_key' => 'sk_old',
                'widget_enabled' => true,
                'widget_id' => 'a1b2c3d4-e5f6-4890-abcd-ef1234567890',
            ]
        );

        $settings = Sendora_Settings::sanitize([
            '_partial' => 'connection',
            'api_key' => 'sk_new_partial',
        ]);

        $this->assertSame('sk_new_partial', $settings['api_key']);
        $this->assertTrue($settings['widget_enabled']);
        $this->assertSame('a1b2c3d4-e5f6-4890-abcd-ef1234567890', $settings['widget_id']);
    }

    public function test_sanitize_woo_events_partial_save(): void
    {
        $settings = Sendora_Settings::sanitize([
            '_partial' => 'woo',
            'woo_events' => [
                'order_created' => [
                    'enabled' => '1',
                    'channel' => 'whatsapp',
                    'template_id' => 'tpl-ok',
                    'instance_id' => 'Instancia_1',
                ],
                'payment_approved' => [
                    'enabled' => '1',
                    'channel' => 'sms',
                    'template_id' => 'bad id!',
                ],
            ],
        ]);

        $this->assertTrue($settings['woo_events']['order_created']['enabled']);
        $this->assertSame('tpl-ok', $settings['woo_events']['order_created']['template_id']);
        $this->assertSame('evolution', $settings['woo_events']['order_created']['channel']);
        $this->assertSame('Instancia_1', $settings['woo_events']['order_created']['instance_id']);
        $this->assertTrue($settings['woo_events']['payment_approved']['enabled']);
        $this->assertSame('evolution', $settings['woo_events']['payment_approved']['channel']);
        $this->assertSame('', $settings['woo_events']['payment_approved']['template_id']);
        $this->assertFalse($settings['woo_events']['order_cancelled']['enabled']);
    }

    public function test_sanitize_woo_events_meta_official(): void
    {
        $settings = Sendora_Settings::sanitize([
            '_partial' => 'woo',
            'woo_events' => [
                'payment_approved' => [
                    'enabled' => '1',
                    'channel' => 'meta',
                    'instance_id' => 'waba-uuid-1',
                    'template_id' => 'pedido_pago',
                    'template_language' => 'pt_BR',
                    'body_vars' => 'first_name, order_number',
                ],
            ],
        ]);

        $row = $settings['woo_events']['payment_approved'];
        $this->assertSame('meta', $row['channel']);
        $this->assertSame('waba-uuid-1', $row['instance_id']);
        $this->assertSame('pedido_pago', $row['template_id']);
        $this->assertSame('pt_BR', $row['template_language']);
        $this->assertSame(['first_name', 'order_number'], $row['body_vars']);
    }

    public function test_sanitize_woo_events_crm_fields(): void
    {
        $settings = Sendora_Settings::sanitize([
            '_partial' => 'woo',
            'woo_events' => [
                'order_created' => [
                    'enabled' => '1',
                    'template_id' => 'tpl-1',
                    'funnel_id' => 'fun-abc',
                    'stage' => 'novo-lead',
                    'tags' => 'woo, pedido-criado, woo',
                ],
            ],
        ]);

        $this->assertSame('fun-abc', $settings['woo_events']['order_created']['funnel_id']);
        $this->assertSame('novo-lead', $settings['woo_events']['order_created']['stage']);
        $this->assertSame(['woo', 'pedido-criado'], $settings['woo_events']['order_created']['tags']);
    }
}
