<?php

declare(strict_types=1);

abstract class SendoraCf7Test extends SendoraFormsMappingTest
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['sendora_test_cf7_forms'] = [];
        $GLOBALS['sendora_test_cf7_posted_data'] = [];
    }

    public function test_run_registers_mail_sent_hook(): void
    {
        $this->assertTrue(class_exists('Sendora_CF7'), 'Sendora_CF7 must exist when CF7 is active.');

        (new Sendora_CF7())->run();

        $this->assertArrayHasKey('wpcf7_mail_sent', $GLOBALS['sendora_test_actions']);
    }

    public function test_plugin_bootstrap_runs_cf7_bridge_when_available(): void
    {
        Sendora_Plugin::instance()->run();

        $this->assertArrayHasKey('wpcf7_mail_sent', $GLOBALS['sendora_test_actions']);
    }

    public function test_lists_cf7_forms_and_their_named_tags(): void
    {
        $GLOBALS['sendora_test_cf7_forms'] = [
            new WPCF7_ContactForm(17, 'Lead form', [
                new WPCF7_FormTag('your-name'),
                new WPCF7_FormTag('your-email'),
                new WPCF7_FormTag(''),
            ]),
        ];

        $this->assertSame([
            [
                'id' => '17',
                'title' => 'Lead form',
                'tags' => ['your-name', 'your-email'],
            ],
        ], Sendora_CF7::list_forms());
    }

    public function test_mail_sent_upserts_mapped_contact_and_triggers_default_flow(): void
    {
        $GLOBALS['sendora_test_options']['sendora_settings']['cf7_mappings'] = [
            '17' => [
                'form_id' => '17',
                'name' => 'your-name',
                'phone' => 'your-phone',
                'email' => 'your-email',
            ],
        ];
        $GLOBALS['sendora_test_options']['sendora_settings']['default_flow_id'] = 'flow-cf7';
        $GLOBALS['sendora_test_cf7_posted_data'] = [
            'your-name' => ' <b>Ada</b> ',
            'your-phone' => '(11) 99999-9999',
            'your-email' => ' ada@example.com ',
        ];
        $requests = [];
        $GLOBALS['sendora_test_http_handler'] = function (string $url, array $arguments) use (&$requests): array {
            $requests[] = [$url, json_decode((string) ($arguments['body'] ?? ''), true)];

            return [
                'response' => ['code' => 200],
                'body' => '{}',
            ];
        };

        Sendora_CF7::mail_sent(new WPCF7_ContactForm(17, 'Lead form'));

        $this->assertSame([
            [
                'https://api.sendora.com.br/api/contacts',
                ['name' => 'Ada', 'phone' => '5511999999999', 'email' => 'ada@example.com'],
            ],
            [
                'https://api.sendora.com.br/api/flows/flow-cf7/trigger',
                ['phone' => '5511999999999'],
            ],
        ], $requests);
        $this->assertSame('cf7', Sendora_Logger::list(1)[0]['source']);
    }

    public function test_mail_sent_ignores_unmapped_form(): void
    {
        $GLOBALS['sendora_test_http_handler'] = static function (): never {
            throw new RuntimeException('Unmapped CF7 forms must not call the API.');
        };

        Sendora_CF7::mail_sent(new WPCF7_ContactForm(99, 'Unmapped'));

        $this->assertSame([], Sendora_Logger::list());
    }
}
