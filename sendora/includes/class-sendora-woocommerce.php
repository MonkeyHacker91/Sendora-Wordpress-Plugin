<?php
/**
 * WooCommerce order sync to Sendora.
 *
 * @package Sendora
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Sendora_WooCommerce
{
    private const RETRY_HOOK = 'sendora_retry_woocommerce_sync';
    private const MAX_RETRIES = 2;

    public function run(): void
    {
        add_action('woocommerce_checkout_order_processed', [self::class, 'order_created']);
        add_action('woocommerce_store_api_checkout_order_processed', [self::class, 'order_created_from_object']);
        add_action('woocommerce_payment_complete', [self::class, 'payment_complete']);
        add_action('woocommerce_order_status_cancelled', [self::class, 'order_cancelled']);
        add_action(self::RETRY_HOOK, [self::class, 'retry_sync'], 10, 3);
    }

    /**
     * Block / Store API checkout passes a WC_Order instance.
     */
    public static function order_created_from_object(object $order): void
    {
        if (!method_exists($order, 'get_id')) {
            return;
        }

        self::order_created((int) $order->get_id());
    }

    /**
     * @return array<string, string>
     */
    public static function contact_payload(object $order, string $default_cc = '55'): array
    {
        $first_name = sanitize_text_field((string) $order->get_billing_first_name());
        $last_name = sanitize_text_field((string) $order->get_billing_last_name());
        $name = trim($first_name . ' ' . $last_name);
        $phone = Sendora_Phone::normalize(
            (string) $order->get_billing_phone(),
            $default_cc
        );
        $email = sanitize_email((string) $order->get_billing_email());
        $payload = [];

        if ($name !== '') {
            $payload['name'] = $name;
        }
        if ($phone !== '') {
            $payload['phone'] = $phone;
        }
        if ($email !== '') {
            $payload['email'] = $email;
        }

        return $payload;
    }

    public static function order_created(int $order_id): void
    {
        $settings = Sendora_Settings::get_settings();
        if (self::created_mode($settings) === 'off') {
            return;
        }

        self::sync_order($order_id, 'created', 0, $settings);
    }

    public static function payment_complete(int $order_id): void
    {
        $settings = Sendora_Settings::get_settings();
        if (empty($settings['woo_on_paid']) || ($settings['woo_paid_mode'] ?? 'off') === 'off') {
            return;
        }

        self::sync_order($order_id, 'paid', 0, $settings);
    }

    public static function order_cancelled(int $order_id): void
    {
        $settings = Sendora_Settings::get_settings();
        if (empty($settings['woo_on_cancelled'])) {
            return;
        }

        self::sync_order($order_id, 'cancelled', 0, $settings);
    }

    public static function retry_sync(int $order_id, string $event, int $attempt): void
    {
        if (!in_array($event, ['created', 'paid', 'cancelled'], true)) {
            return;
        }

        self::sync_order(
            $order_id,
            $event,
            max(1, $attempt),
            Sendora_Settings::get_settings()
        );
    }

    /**
     * @param array<string, mixed> $settings
     */
    private static function sync_order(
        int $order_id,
        string $event,
        int $attempt,
        array $settings
    ): void {
        $order = wc_get_order($order_id);
        if (!is_object($order)) {
            self::log_failure('WooCommerce order was unavailable.', $order_id, 0);

            return;
        }

        $payload = self::contact_payload(
            $order,
            (string) ($settings['default_cc'] ?? '55')
        );
        if (empty($payload['phone'])) {
            self::handle_failure(
                $order,
                $event,
                $attempt,
                'Billing phone is required for Sendora sync.'
            );

            return;
        }

        $client = Sendora_Api_Client::from_options();
        $contact_result = $client->upsert_contact($payload);
        if (empty($contact_result['ok'])) {
            self::handle_failure(
                $order,
                $event,
                $attempt,
                (string) ($contact_result['error'] ?? 'Contact sync failed.'),
                (int) ($contact_result['status'] ?? 0)
            );

            return;
        }

        $contact_id = self::contact_id($contact_result['data'] ?? null);
        if ($contact_id !== '') {
            $order->update_meta_data('_sendora_contact_id', $contact_id);
        }

        $action_result = self::run_event_action($client, $order, $event, $settings, $payload['phone']);
        if ($action_result !== null && empty($action_result['ok'])) {
            self::handle_failure(
                $order,
                $event,
                $attempt,
                (string) ($action_result['error'] ?? 'Sendora order action failed.'),
                (int) ($action_result['status'] ?? 0)
            );

            return;
        }

        $order->update_meta_data('_sendora_last_sync_at', current_time('mysql', true));
        $order->delete_meta_data('_sendora_last_error');
        $order->save();

        Sendora_Logger::log('woo', 'info', 'WooCommerce order synced.', [
            'order_id' => $order_id,
            'event' => $event,
        ]);
    }

    /**
     * @param array<string, mixed> $settings
     * @return array{ok: bool, status: int, data: mixed, error: ?string}|null
     */
    private static function run_event_action(
        Sendora_Api_Client $client,
        object $order,
        string $event,
        array $settings,
        string $phone
    ): ?array {
        if ($event === 'created' && self::created_mode($settings) === 'contact_and_flow') {
            $flow_id = trim((string) ($settings['default_flow_id'] ?? ''));

            return $flow_id === '' ? null : $client->trigger_flow($flow_id, $phone);
        }

        if ($event !== 'paid') {
            return null;
        }

        $mode = (string) ($settings['woo_paid_mode'] ?? 'off');
        if ($mode === 'flow') {
            $flow_id = trim((string) ($settings['woo_paid_flow_id'] ?? ''));

            return $flow_id === '' ? null : $client->trigger_flow($flow_id, $phone);
        }

        if ($mode === 'message') {
            return $client->send_message([
                'phone' => $phone,
                'message' => sprintf(
                    /* translators: %s: número do pedido WooCommerce. */
                    __('Pagamento recebido do pedido #%s.', 'sendora'),
                    (string) $order->get_order_number()
                ),
            ]);
        }

        return null;
    }

    private static function handle_failure(
        object $order,
        string $event,
        int $attempt,
        string $error,
        int $status = 0
    ): void {
        $safe_error = wp_strip_all_tags($error);
        $order->update_meta_data('_sendora_last_error', $safe_error);
        $order->save();
        self::log_failure($safe_error, (int) $order->get_id(), $status);

        if ($attempt < self::MAX_RETRIES) {
            self::schedule_retry((int) $order->get_id(), $event, $attempt + 1);

            return;
        }

        $order->add_order_note(
            sprintf(
                /* translators: %s: mensagem de erro sanitizada da API Sendora. */
                __('Falha na sincronização Sendora após novas tentativas: %s', 'sendora'),
                $safe_error
            )
        );
    }

    private static function schedule_retry(int $order_id, string $event, int $attempt): void
    {
        $args = [$order_id, $event, $attempt];

        if (function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action(self::RETRY_HOOK, $args, 'sendora');

            return;
        }

        wp_schedule_single_event(time() + (60 * $attempt), self::RETRY_HOOK, $args);
    }

    /**
     * @param array<string, mixed> $settings
     */
    private static function created_mode(array $settings): string
    {
        $mode = (string) ($settings['woo_created_mode'] ?? '');
        if (in_array($mode, ['contact_only', 'contact_and_flow', 'off'], true)) {
            return $mode;
        }

        return !empty($settings['woo_on_created']) ? 'contact_only' : 'off';
    }

    private static function contact_id(mixed $data): string
    {
        if (!is_array($data)) {
            return '';
        }

        foreach ([
            $data['id'] ?? null,
            $data['data']['id'] ?? null,
            $data['contact']['id'] ?? null,
        ] as $id) {
            if (is_scalar($id) && trim((string) $id) !== '') {
                return sanitize_text_field((string) $id);
            }
        }

        return '';
    }

    private static function log_failure(string $message, int $order_id, int $status): void
    {
        $context = ['order_id' => $order_id];
        if ($status > 0) {
            $context['status'] = $status;
        }

        Sendora_Logger::log('woo', 'error', $message, $context);
    }
}
