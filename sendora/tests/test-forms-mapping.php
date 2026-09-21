<?php

declare(strict_types=1);

abstract class SendoraFormsMappingTest extends SendoraLoggerTest
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['sendora_test_shortcodes'] = [];
        $GLOBALS['sendora_test_enqueued_styles'] = [];
        $GLOBALS['sendora_test_enqueued_scripts'] = [];
        $GLOBALS['sendora_test_transients'] = [];
        $GLOBALS['sendora_test_nonce_valid'] = 1;
        $GLOBALS['sendora_test_json_response'] = null;
        $_POST = [];
        $_SERVER['REMOTE_ADDR'] = '203.0.113.10';
    }

    public function test_phone_is_required(): void
    {
        $this->assertTrue(class_exists('Sendora_Forms'), 'Sendora_Forms must exist.');

        $result = Sendora_Forms::map_submission([
            'name' => 'Ada',
            'phone' => '',
            'email' => 'ada@example.com',
            'message' => 'Hello',
        ]);

        $this->assertFalse($result['ok']);
        $this->assertSame('O telefone é obrigatório.', $result['error']);
    }

    public function test_submission_maps_and_sanitizes_contact_fields(): void
    {
        $result = Sendora_Forms::map_submission([
            'name' => ' <b>Ada</b> ',
            'phone' => '(11) 99999-9999',
            'email' => ' ADA@example.com ',
            'message' => " Hello\nthere ",
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame([
            'name' => 'Ada',
            'phone' => '5511999999999',
            'email' => 'ADA@example.com',
        ], $result['fields']);
        $this->assertSame("Hello\nthere", $result['message']);
    }

    public function test_run_registers_shortcode_and_public_ajax_hooks(): void
    {
        (new Sendora_Forms())->run();

        $this->assertArrayHasKey('sendora_form', $GLOBALS['sendora_test_shortcodes']);
        $this->assertArrayHasKey('wp_ajax_sendora_form_submit', $GLOBALS['sendora_test_actions']);
        $this->assertArrayHasKey('wp_ajax_nopriv_sendora_form_submit', $GLOBALS['sendora_test_actions']);
        $this->assertSame([], $GLOBALS['sendora_test_enqueued_styles']);
        $this->assertSame([], $GLOBALS['sendora_test_enqueued_scripts']);
    }

    public function test_plugin_bootstrap_runs_forms(): void
    {
        Sendora_Plugin::instance()->run();

        $this->assertArrayHasKey('sendora_form', $GLOBALS['sendora_test_shortcodes']);
    }

    public function test_render_enqueues_assets_and_contains_security_fields(): void
    {
        $html = Sendora_Forms::render_shortcode();

        $this->assertArrayHasKey('sendora-form', $GLOBALS['sendora_test_enqueued_styles']);
        $this->assertArrayHasKey('sendora-form', $GLOBALS['sendora_test_enqueued_scripts']);
        $this->assertStringContainsString('name="sendora_nonce"', $html);
        $this->assertStringContainsString('name="website"', $html);
        $this->assertStringContainsString('name="phone"', $html);
    }

    public function test_submit_upserts_contact_and_triggers_default_flow(): void
    {
        $GLOBALS['sendora_test_options']['sendora_settings']['default_flow_id'] = 'flow-1';
        $GLOBALS['sendora_test_options']['sendora_settings']['native_form'] = [
            'enabled' => false,
            'channel' => 'evolution',
            'template_id' => '',
        ];
        $requests = [];
        $GLOBALS['sendora_test_http_handler'] = function (string $url, array $arguments) use (&$requests): array {
            $requests[] = [$url, json_decode((string) ($arguments['body'] ?? ''), true)];

            return [
                'response' => ['code' => 200],
                'body' => '{}',
            ];
        };

        $result = Sendora_Forms::handle_submission([
            'name' => 'Ada',
            'phone' => '(11) 99999-9999',
            'email' => 'ada@example.com',
            'message' => 'Hello',
            'website' => '',
        ], '203.0.113.10');

        $this->assertTrue($result['ok']);
        $this->assertSame([
            [
                'https://api.sendora.com.br/api/contacts',
                ['name' => 'Ada', 'phone' => '5511999999999', 'email' => 'ada@example.com'],
            ],
            [
                'https://api.sendora.com.br/api/flows/flow-1/trigger',
                ['phone' => '5511999999999', 'message' => 'Hello'],
            ],
        ], $requests);
        $this->assertSame('form', Sendora_Logger::list(1)[0]['source']);
    }

    public function test_submit_sends_configured_template_message(): void
    {
        $GLOBALS['sendora_test_options']['sendora_settings']['native_form'] = [
            'enabled' => true,
            'channel' => 'evolution',
            'template_id' => 'tpl-form',
        ];
        $GLOBALS['sendora_test_options']['sendora_settings']['default_flow_id'] = 'flow-1';
        delete_transient('sendora_templates_cache');

        $requests = [];
        $GLOBALS['sendora_test_http_handler'] = function (string $url, array $arguments) use (&$requests): array {
            $requests[] = $url;
            if (str_contains($url, '/api/templates')) {
                return [
                    'response' => ['code' => 200],
                    'body' => wp_json_encode([
                        'data' => [[
                            'id' => 'tpl-form',
                            'name' => 'Lead',
                            'content' => 'Oi {{nome}}',
                            'type' => 'text',
                        ]],
                    ]),
                ];
            }

            return ['response' => ['code' => 200], 'body' => '{}'];
        };

        $result = Sendora_Forms::handle_submission([
            'name' => 'Ada',
            'phone' => '11999999999',
            'website' => '',
        ], '203.0.113.10');

        $this->assertTrue($result['ok']);
        $this->assertContains('https://api.sendora.com.br/api/messages/send', $requests);
        $this->assertNotContains('https://api.sendora.com.br/api/flows/flow-1/trigger', $requests);
    }

    public function test_honeypot_submission_does_not_call_api(): void
    {
        $GLOBALS['sendora_test_http_handler'] = static function (): never {
            throw new RuntimeException('Bot submission must not call API.');
        };

        $result = Sendora_Forms::handle_submission([
            'phone' => '11999999999',
            'website' => 'https://spam.example',
        ], '203.0.113.10');

        $this->assertTrue($result['ok']);
    }

    public function test_rate_limit_rejects_sixth_submission_in_ten_minutes(): void
    {
        $GLOBALS['sendora_test_http_handler'] = static fn (): array => [
            'response' => ['code' => 200],
            'body' => '{}',
        ];

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->assertTrue(Sendora_Forms::handle_submission(
                ['phone' => '11999999999', 'website' => ''],
                '203.0.113.10'
            )['ok']);
        }

        $result = Sendora_Forms::handle_submission(
            ['phone' => '11999999999', 'website' => ''],
            '203.0.113.10'
        );

        $this->assertFalse($result['ok']);
        $this->assertSame('Muitos envios. Tente novamente em alguns minutos.', $result['error']);
    }

    public function test_ajax_rejects_invalid_nonce_before_api_call(): void
    {
        $GLOBALS['sendora_test_nonce_valid'] = false;
        $GLOBALS['sendora_test_http_handler'] = static function (): never {
            throw new RuntimeException('Invalid nonce must not call API.');
        };
        $_POST = ['phone' => '11999999999'];

        try {
            Sendora_Forms::ajax_submit();
            $this->fail('Expected wp_send_json to end the request.');
        } catch (RuntimeException $exception) {
            $this->assertSame('sendora_test_json_complete', $exception->getMessage());
        }

        $this->assertFalse($GLOBALS['sendora_test_json_response']['ok']);
        $this->assertSame('Token do formulário inválido.', $GLOBALS['sendora_test_json_response']['error']);
    }

    public function test_ajax_accepts_nonce_from_previous_tick(): void
    {
        $GLOBALS['sendora_test_nonce_valid'] = 2;
        $GLOBALS['sendora_test_http_handler'] = static fn (): array => [
            'response' => ['code' => 200],
            'body' => '{}',
        ];
        $_POST = ['phone' => '11999999999', 'website' => ''];

        try {
            Sendora_Forms::ajax_submit();
            $this->fail('Expected wp_send_json to end the request.');
        } catch (RuntimeException $exception) {
            $this->assertSame('sendora_test_json_complete', $exception->getMessage());
        }

        $this->assertTrue($GLOBALS['sendora_test_json_response']['ok']);
    }
}
