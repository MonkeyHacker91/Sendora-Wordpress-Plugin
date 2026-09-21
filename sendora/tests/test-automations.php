<?php

declare(strict_types=1);

abstract class SendoraAutomationsTest extends SendoraWooPayloadTest
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['sendora_test_scheduled_events'] = [];
        $existing = is_array($GLOBALS['sendora_test_options']['sendora_settings'] ?? null)
            ? $GLOBALS['sendora_test_options']['sendora_settings']
            : [];
        $GLOBALS['sendora_test_options']['sendora_settings'] = array_merge($existing, [
            'automations' => [],
        ]);
        if (($GLOBALS['sendora_test_options']['sendora_settings']['default_cc'] ?? '') === '') {
            $GLOBALS['sendora_test_options']['sendora_settings']['default_cc'] = '55';
        }
    }

    public function test_sanitize_drops_blank_draft_cards(): void
    {
        $rules = Sendora_Automations::sanitize_rules([
            [
                'id' => 'auto_new_abc',
                'name' => '',
                'enabled' => '0',
                'trigger' => 'form.native',
                'delay_minutes' => 0,
                'actions' => [],
            ],
            [
                'id' => 'auto_keep',
                'name' => 'Boas-vindas',
                'enabled' => '1',
                'trigger' => 'form.native',
                'delay_minutes' => 5,
                'actions' => [
                    'channel' => 'evolution',
                    'template_id' => 'tpl-1',
                ],
            ],
        ]);

        $this->assertCount(1, $rules);
        $this->assertSame('auto_keep', $rules[0]['id']);
        $this->assertTrue($rules[0]['enabled']);
        $this->assertSame(5, $rules[0]['delay_minutes']);
        $this->assertSame('tpl-1', $rules[0]['actions']['template_id']);
    }

    public function test_conditions_match_order_total_gte(): void
    {
        $ok = Sendora_Automations::conditions_match(
            [['field' => 'order_total', 'op' => 'gte', 'value' => '100']],
            ['order_total_raw' => '199.90']
        );
        $fail = Sendora_Automations::conditions_match(
            [['field' => 'order_total', 'op' => 'gte', 'value' => '500']],
            ['order_total_raw' => '199.90']
        );

        $this->assertTrue($ok);
        $this->assertFalse($fail);
    }

    public function test_dispatch_schedules_delay_and_runs_immediate(): void
    {
        $GLOBALS['sendora_test_options']['sendora_settings']['automations'] = [
            [
                'id' => 'auto_delay',
                'name' => 'Com delay',
                'enabled' => true,
                'trigger' => 'form.native',
                'delay_minutes' => 10,
                'conditions' => [],
                'actions' => ['template_id' => 'tpl-delay', 'channel' => 'evolution'],
            ],
            [
                'id' => 'auto_now',
                'name' => 'Imediata',
                'enabled' => true,
                'trigger' => 'form.native',
                'delay_minutes' => 0,
                'conditions' => [],
                'actions' => [
                    'channel' => 'evolution',
                    'template_id' => 'tpl-now',
                    'tags' => ['auto'],
                ],
            ],
        ];

        $urls = [];
        $GLOBALS['sendora_test_http_handler'] = static function (string $url) use (&$urls): array {
            $urls[] = $url;
            if (str_contains($url, '/api/templates')) {
                return [
                    'response' => ['code' => 200],
                    'body' => wp_json_encode([
                        'ok' => true,
                        'data' => [[
                            'id' => 'tpl-now',
                            'name' => 'Agora',
                            'content' => 'Olá {{nome}}',
                            'type' => 'text',
                        ]],
                    ]),
                ];
            }
            if (str_contains($url, '/api/contacts')) {
                return [
                    'response' => ['code' => 200],
                    'body' => wp_json_encode(['ok' => true, 'id' => 'c1']),
                ];
            }
            if (str_contains($url, '/api/messages')) {
                return [
                    'response' => ['code' => 200],
                    'body' => wp_json_encode(['ok' => true]),
                ];
            }

            return [
                'response' => ['code' => 404],
                'body' => '{}',
            ];
        };

        Sendora_Automations::dispatch('form.native', [
            'phone' => '11999999999',
            'name' => 'Ada',
            'contact' => ['phone' => '11999999999', 'name' => 'Ada'],
            'vars' => ['name' => 'Ada'],
        ]);

        $this->assertNotEmpty($GLOBALS['sendora_test_scheduled_events']);
        $scheduled = $GLOBALS['sendora_test_scheduled_events'][0];
        $this->assertSame(Sendora_Automations::PROCESS_HOOK, $scheduled['hook']);
        $this->assertSame('auto_delay', $scheduled['args'][0]);
        $this->assertTrue(
            count(array_filter($urls, static fn (string $u): bool => str_contains($u, '/api/messages'))) >= 1
        );
    }

    public function test_partial_sanitize_automations(): void
    {
        $GLOBALS['sendora_test_options']['sendora_settings'] = [
            'api_base' => 'https://api.sendora.com.br',
            'api_key' => 'sk_keep',
            'automations' => [],
        ];

        $saved = Sendora_Settings::sanitize([
            '_partial' => 'automations',
            'automations' => [
                [
                    'id' => 'auto_a',
                    'name' => 'CF7 VIP',
                    'enabled' => '1',
                    'trigger' => 'form.cf7',
                    'conditions' => [
                        ['field' => 'cf7_form_id', 'op' => 'eq', 'value' => '12'],
                    ],
                    'delay_minutes' => 0,
                    'actions' => [
                        'flow_id' => 'flow-1',
                        'tags' => 'vip, lead',
                    ],
                ],
            ],
        ]);

        $this->assertSame('sk_keep', $saved['api_key']);
        $this->assertCount(1, $saved['automations']);
        $this->assertSame('form.cf7', $saved['automations'][0]['trigger']);
        $this->assertSame('flow-1', $saved['automations'][0]['actions']['flow_id']);
        $this->assertSame(['vip', 'lead'], $saved['automations'][0]['actions']['tags']);
        $this->assertSame('cf7_form_id', $saved['automations'][0]['conditions'][0]['field']);
    }

    public function test_trigger_catalog_includes_woo_and_forms(): void
    {
        $catalog = Sendora_Automations::trigger_catalog();
        $this->assertArrayHasKey('form.native', $catalog);
        $this->assertArrayHasKey('form.cf7', $catalog);
        $this->assertArrayHasKey('woo.cart_abandoned', $catalog);
        $this->assertArrayHasKey('woo.order_created', $catalog);
    }
}
