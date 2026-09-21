<?php

declare(strict_types=1);

final class Sendora_Test_WC_Order
{
    /** @var array<string, mixed> */
    public array $meta = [];

    /** @var array<int, string> */
    public array $notes = [];

    public string $status = 'pending';

    public string $phone = '(11) 99999-9999';

    public function __construct(private int $id = 42)
    {
    }

    public function get_id(): int
    {
        return $this->id;
    }

    public function get_billing_first_name(): string
    {
        return ' Ada ';
    }

    public function get_billing_last_name(): string
    {
        return ' <b>Lovelace</b> ';
    }

    public function get_billing_phone(): string
    {
        return $this->phone;
    }

    public function get_billing_email(): string
    {
        return ' ada@example.com ';
    }

    public function get_order_number(): string
    {
        return (string) $this->id;
    }

    public function get_status(): string
    {
        return $this->status;
    }

    public function get_total(): string
    {
        return '199.90';
    }

    public function get_formatted_order_total(): string
    {
        return 'R$&nbsp;199,90';
    }

    public function get_date_created(): object
    {
        return new class {
            public function date_i18n(string $format): string
            {
                return '18/09/2026';
            }
        };
    }

    public function get_payment_method_title(): string
    {
        return 'Pix';
    }

    public function get_formatted_shipping_address(): string
    {
        return "Rua A, 100\nSão Paulo";
    }

    public function get_items(): array
    {
        return [
            new class {
                public function get_name(): string
                {
                    return 'Camiseta Sendora';
                }
            },
        ];
    }

    public function get_view_order_url(): string
    {
        return 'https://example.com/minha-conta/view-order/42/';
    }

    public function get_meta(string $key, bool $single = true): mixed
    {
        return $this->meta[$key] ?? '';
    }

    public function update_meta_data(string $key, mixed $value): void
    {
        $this->meta[$key] = $value;
    }

    public function delete_meta_data(string $key): void
    {
        unset($this->meta[$key]);
    }

    public function add_order_note(string $note): void
    {
        $this->notes[] = $note;
    }

    public function save(): void
    {
    }
}

abstract class SendoraWooPayloadTest extends SendoraWidgetTest
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['sendora_test_wc_orders'] = [];
        $GLOBALS['sendora_test_scheduled_events'] = [];
        delete_transient('sendora_templates_cache');
    }

    /** @return array<string, mixed> */
    private function enabled_event(string $event, string $template_id = 'tpl-1'): array
    {
        $settings = Sendora_Settings::get_settings();
        $events = $settings['woo_events'];
        $events[$event] = [
            'enabled' => true,
            'channel' => 'evolution',
            'template_id' => $template_id,
        ];
        $settings['woo_events'] = $events;

        return $settings;
    }

    public function test_maps_woo_billing_fields_to_contact_payload(): void
    {
        $this->assertTrue(
            class_exists('Sendora_WooCommerce'),
            'Sendora_WooCommerce must exist when WooCommerce is active.'
        );

        $this->assertSame([
            'name' => 'Ada Lovelace',
            'phone' => '5511999999999',
            'email' => 'ada@example.com',
        ], Sendora_WooCommerce::contact_payload(
            new Sendora_Test_WC_Order(),
            '55'
        ));
    }

    public function test_run_registers_woo_and_process_hooks(): void
    {
        (new Sendora_WooCommerce())->run();

        $this->assertArrayHasKey('woocommerce_checkout_order_processed', $GLOBALS['sendora_test_actions']);
        $this->assertArrayHasKey('woocommerce_payment_complete', $GLOBALS['sendora_test_actions']);
        $this->assertArrayHasKey('woocommerce_order_status_cancelled', $GLOBALS['sendora_test_actions']);
        $this->assertArrayHasKey('sendora_process_woo_event', $GLOBALS['sendora_test_actions']);
    }

    public function test_queue_schedules_async_process_when_event_enabled(): void
    {
        $GLOBALS['sendora_test_options']['sendora_settings'] = $this->enabled_event('order_created');

        Sendora_WooCommerce::order_created(42);

        $this->assertSame('sendora_process_woo_event', $GLOBALS['sendora_test_scheduled_events'][0]['hook']);
        $this->assertSame([42, 'order_created', 0], $GLOBALS['sendora_test_scheduled_events'][0]['args']);
    }

    public function test_process_event_upserts_contact_resolves_template_and_sends(): void
    {
        $order = new Sendora_Test_WC_Order();
        $GLOBALS['sendora_test_wc_orders'][42] = $order;
        $GLOBALS['sendora_test_options']['sendora_settings'] = $this->enabled_event('order_created', 'tpl-pedido');

        $requests = [];
        $GLOBALS['sendora_test_http_handler'] = static function (
            string $url,
            array $arguments
        ) use (&$requests): array {
            $requests[] = [$url, json_decode((string) ($arguments['body'] ?? ''), true)];

            if (str_contains($url, '/api/templates')) {
                return [
                    'response' => ['code' => 200],
                    'body' => wp_json_encode([
                        'ok' => true,
                        'data' => [[
                            'id' => 'tpl-pedido',
                            'name' => 'Pedido recebido',
                            'content' => 'Olá {{nome}}! Pedido #{{pedido}} — {{valor_pedido}}',
                            'type' => 'text',
                        ]],
                    ]),
                ];
            }

            if (str_contains($url, '/api/contacts')) {
                return [
                    'response' => ['code' => 200],
                    'body' => '{"data":{"id":"contact-42"}}',
                ];
            }

            return ['response' => ['code' => 200], 'body' => '{"ok":true}'];
        };

        Sendora_WooCommerce::process_event(42, 'order_created', 0);

        $this->assertSame('contact-42', $order->meta['_sendora_contact_id']);
        $this->assertArrayHasKey('_sendora_last_sync_at', $order->meta);
        $this->assertSame(
            'https://api.sendora.com.br/api/messages/send',
            $requests[2][0]
        );
        $this->assertSame('5511999999999', $requests[2][1]['phone']);
        $this->assertStringContainsString('Ada Lovelace', (string) $requests[2][1]['text']);
        $this->assertStringContainsString('#42', (string) $requests[2][1]['text']);
    }

    public function test_invalid_phone_does_not_call_api(): void
    {
        $order = new Sendora_Test_WC_Order();
        $order->phone = '';
        $GLOBALS['sendora_test_wc_orders'][42] = $order;
        $GLOBALS['sendora_test_options']['sendora_settings'] = $this->enabled_event('payment_approved');
        $called = false;
        $GLOBALS['sendora_test_http_handler'] = static function () use (&$called): array {
            $called = true;

            return ['response' => ['code' => 200], 'body' => '{}'];
        };

        Sendora_WooCommerce::process_event(42, 'payment_approved', 0);

        $this->assertFalse($called);
        $this->assertSame('telefone inválido', $order->meta['_sendora_last_error']);
    }

    public function test_api_failure_schedules_retry_then_adds_note_after_last_attempt(): void
    {
        $order = new Sendora_Test_WC_Order();
        $GLOBALS['sendora_test_wc_orders'][42] = $order;
        $GLOBALS['sendora_test_options']['sendora_settings'] = $this->enabled_event('order_created', 'tpl-1');
        $GLOBALS['sendora_test_http_handler'] = static function (string $url): array {
            if (str_contains($url, '/api/templates')) {
                return [
                    'response' => ['code' => 200],
                    'body' => wp_json_encode([
                        'data' => [[
                            'id' => 'tpl-1',
                            'name' => 'Pedido',
                            'content' => 'Oi {{nome}}',
                            'type' => 'text',
                        ]],
                    ]),
                ];
            }

            return [
                'response' => ['code' => 503],
                'body' => '{"message":"Temporarily unavailable"}',
            ];
        };

        Sendora_WooCommerce::process_event(42, 'order_created', 0);

        $this->assertSame('sendora_process_woo_event', $GLOBALS['sendora_test_scheduled_events'][0]['hook']);
        $this->assertSame([42, 'order_created', 1], $GLOBALS['sendora_test_scheduled_events'][0]['args']);
        $this->assertSame('Temporarily unavailable', $order->meta['_sendora_last_error']);
        $this->assertSame([], $order->notes);

        Sendora_WooCommerce::process_event(42, 'order_created', 2);

        $this->assertCount(1, $GLOBALS['sendora_test_scheduled_events']);
        $this->assertCount(1, $order->notes);
        $this->assertStringContainsString('Temporarily unavailable', $order->notes[0]);
    }

    public function test_template_vars_resolve_order_placeholders(): void
    {
        $text = Sendora_Template_Vars::resolve(
            'Oi {{primeiro_nome}} pedido {{pedido}} total {{valor_pedido}} rastreio {{rastreio}}',
            [
                'name' => 'Ada Lovelace',
                'first_name' => 'Ada',
                'order_number' => '5821',
                'order_total' => 'R$ 10,00',
                'tracking' => 'BR123',
            ]
        );

        $this->assertSame('Oi Ada pedido 5821 total R$ 10,00 rastreio BR123', $text);
    }

    public function test_event_definitions_include_abandoned_cart(): void
    {
        $defs = Sendora_WooCommerce::event_definitions();
        $this->assertArrayHasKey('cart_abandoned', $defs);
        $this->assertTrue(!empty($defs['cart_abandoned']['abandoned']));
    }

    public function test_apply_crm_fields_adds_funnel_stage_tags(): void
    {
        $payload = Sendora_WooCommerce::apply_crm_fields(
            ['name' => 'Ada', 'phone' => '5511999999999'],
            [
                'funnel_id' => 'fun-1',
                'stage' => 'pago',
                'tags' => ['woo', 'pago'],
            ]
        );

        $this->assertSame('fun-1', $payload['funnel_id']);
        $this->assertSame('pago', $payload['stage']);
        $this->assertSame(['woo', 'pago'], $payload['tags']);
    }

    public function test_process_event_sends_crm_fields_on_contact_upsert(): void
    {
        $order = new Sendora_Test_WC_Order();
        $GLOBALS['sendora_test_wc_orders'][42] = $order;
        $settings = $this->enabled_event('payment_approved', 'tpl-1');
        $settings['woo_events']['payment_approved']['funnel_id'] = 'fun-1';
        $settings['woo_events']['payment_approved']['stage'] = 'pago';
        $settings['woo_events']['payment_approved']['tags'] = ['woo', 'pago'];
        $GLOBALS['sendora_test_options']['sendora_settings'] = $settings;
        delete_transient('sendora_templates_cache');

        $bodies = [];
        $GLOBALS['sendora_test_http_handler'] = static function (
            string $url,
            array $arguments
        ) use (&$bodies): array {
            if (str_contains($url, '/api/templates')) {
                return [
                    'response' => ['code' => 200],
                    'body' => wp_json_encode([
                        'data' => [[
                            'id' => 'tpl-1',
                            'name' => 'Pago',
                            'content' => 'Oi {{nome}}',
                            'type' => 'text',
                        ]],
                    ]),
                ];
            }
            if (str_contains($url, '/api/contacts')) {
                $bodies[] = json_decode((string) ($arguments['body'] ?? ''), true);
            }

            return ['response' => ['code' => 200], 'body' => '{"data":{"id":"c1"}}'];
        };

        Sendora_WooCommerce::process_event(42, 'payment_approved', 0);

        $this->assertNotEmpty($bodies);
        $this->assertSame('fun-1', $bodies[0]['funnel_id']);
        $this->assertSame('pago', $bodies[0]['stage']);
        $this->assertSame(['woo', 'pago'], $bodies[0]['tags']);
    }

    public function test_abandoned_cart_process_sends_template(): void
    {
        $GLOBALS['sendora_test_options']['sendora_settings'] = $this->enabled_event('cart_abandoned', 'tpl-cart');
        $GLOBALS['sendora_test_options']['sendora_settings']['woo_events']['cart_abandoned']['delay_minutes'] = 30;
        delete_transient('sendora_templates_cache');

        $key = 'p_' . hash('sha256', '5511999999999');
        update_option('sendora_abandoned_checkouts', [
            $key => [
                'phone' => '5511999999999',
                'name' => 'Ada',
                'email' => 'ada@example.com',
                'product' => 'Camiseta',
                'total' => 'R$ 50',
                'captured_at' => time() - 100,
                'send_at' => time(),
                'recovered' => false,
                'sent' => false,
            ],
        ], false);

        $urls = [];
        $GLOBALS['sendora_test_http_handler'] = static function (string $url) use (&$urls): array {
            $urls[] = $url;
            if (str_contains($url, '/api/templates')) {
                return [
                    'response' => ['code' => 200],
                    'body' => wp_json_encode([
                        'data' => [[
                            'id' => 'tpl-cart',
                            'name' => 'Carrinho',
                            'content' => 'Volte {{nome}} — {{produto}}',
                            'type' => 'text',
                        ]],
                    ]),
                ];
            }

            return ['response' => ['code' => 200], 'body' => '{}'];
        };

        // Avoid wc_get_orders looking for recent orders.
        if (!function_exists('wc_get_orders')) {
            // stubbed below in bootstrap ideally
        }

        Sendora_Abandoned_Cart::process($key);

        $this->assertContains('https://api.sendora.com.br/api/messages/send', $urls);
        $store = get_option('sendora_abandoned_checkouts', []);
        $this->assertTrue(!empty($store[$key]['sent']));
    }
}
