<?php

declare(strict_types=1);

final class Sendora_Test_WC_Order
{
    /** @var array<string, mixed> */
    public array $meta = [];

    /** @var array<int, string> */
    public array $notes = [];

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
        return '(11) 99999-9999';
    }

    public function get_billing_email(): string
    {
        return ' ada@example.com ';
    }

    public function get_order_number(): string
    {
        return (string) $this->id;
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

    public function test_run_registers_woo_and_retry_hooks(): void
    {
        (new Sendora_WooCommerce())->run();

        $this->assertArrayHasKey('woocommerce_checkout_order_processed', $GLOBALS['sendora_test_actions']);
        $this->assertArrayHasKey('woocommerce_payment_complete', $GLOBALS['sendora_test_actions']);
        $this->assertArrayHasKey('woocommerce_order_status_cancelled', $GLOBALS['sendora_test_actions']);
        $this->assertArrayHasKey('sendora_retry_woocommerce_sync', $GLOBALS['sendora_test_actions']);
    }

    public function test_created_order_upserts_contact_and_saves_sync_meta(): void
    {
        $order = new Sendora_Test_WC_Order();
        $GLOBALS['sendora_test_wc_orders'][42] = $order;
        $GLOBALS['sendora_test_options']['sendora_settings']['woo_created_mode'] = 'contact_only';
        $GLOBALS['sendora_test_http_handler'] = static fn (): array => [
            'response' => ['code' => 200],
            'body' => '{"data":{"id":"contact-42"}}',
        ];

        Sendora_WooCommerce::order_created(42);

        $this->assertSame('contact-42', $order->meta['_sendora_contact_id']);
        $this->assertArrayHasKey('_sendora_last_sync_at', $order->meta);
        $this->assertArrayNotHasKey('_sendora_last_error', $order->meta);
        $this->assertSame([], $GLOBALS['sendora_test_scheduled_events']);
    }

    public function test_api_failure_schedules_cron_retry_then_adds_note_after_last_attempt(): void
    {
        $order = new Sendora_Test_WC_Order();
        $GLOBALS['sendora_test_wc_orders'][42] = $order;
        $GLOBALS['sendora_test_options']['sendora_settings']['woo_created_mode'] = 'contact_only';
        $GLOBALS['sendora_test_http_handler'] = static fn (): array => [
            'response' => ['code' => 503],
            'body' => '{"message":"Temporarily unavailable"}',
        ];

        Sendora_WooCommerce::order_created(42);

        $this->assertSame('sendora_retry_woocommerce_sync', $GLOBALS['sendora_test_scheduled_events'][0]['hook']);
        $this->assertSame([42, 'created', 1], $GLOBALS['sendora_test_scheduled_events'][0]['args']);
        $this->assertSame('Temporarily unavailable', $order->meta['_sendora_last_error']);
        $this->assertSame([], $order->notes);

        Sendora_WooCommerce::retry_sync(42, 'created', 2);

        $this->assertCount(1, $GLOBALS['sendora_test_scheduled_events']);
        $this->assertCount(1, $order->notes);
        $this->assertStringContainsString('Temporarily unavailable', $order->notes[0]);
    }

    public function test_payment_complete_can_trigger_configured_flow(): void
    {
        $order = new Sendora_Test_WC_Order();
        $GLOBALS['sendora_test_wc_orders'][42] = $order;
        $GLOBALS['sendora_test_options']['sendora_settings'] = array_merge(
            Sendora_Settings::get_settings(),
            [
                'woo_on_paid' => true,
                'woo_paid_mode' => 'flow',
                'woo_paid_flow_id' => 'paid-flow',
            ]
        );
        $requests = [];
        $GLOBALS['sendora_test_http_handler'] = static function (
            string $url,
            array $arguments
        ) use (&$requests): array {
            $requests[] = [$url, json_decode((string) ($arguments['body'] ?? ''), true)];

            return ['response' => ['code' => 200], 'body' => '{}'];
        };

        Sendora_WooCommerce::payment_complete(42);

        $this->assertSame(
            'https://api.sendora.com.br/api/flows/paid-flow/trigger',
            $requests[1][0]
        );
        $this->assertSame(['phone' => '5511999999999'], $requests[1][1]);
    }
}
