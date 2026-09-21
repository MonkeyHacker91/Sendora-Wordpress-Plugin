<?php
/**
 * WooCommerce abandoned checkout capture and delayed send.
 *
 * @package Sendora
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Sendora_Abandoned_Cart
{
    public const EVENT_KEY = 'cart_abandoned';
    private const PROCESS_HOOK = 'sendora_process_abandoned_cart';
    private const OPTION_KEY = 'sendora_abandoned_checkouts';

    public function run(): void
    {
        add_action('woocommerce_checkout_update_order_review', [self::class, 'capture_from_checkout']);
        add_action('woocommerce_checkout_order_processed', [self::class, 'mark_recovered_by_order']);
        add_action('woocommerce_store_api_checkout_order_processed', [self::class, 'mark_recovered_from_object']);
        add_action(self::PROCESS_HOOK, [self::class, 'process'], 10, 1);
    }

    public static function capture_from_checkout(string $posted_data): void
    {
        $settings = Sendora_Settings::get_settings();
        $config = class_exists('Sendora_WooCommerce')
            ? Sendora_WooCommerce::event_config($settings, self::EVENT_KEY)
            : ['enabled' => false, 'template_id' => '', 'channel' => 'whatsapp', 'funnel_id' => '', 'stage' => '', 'tags' => []];

        if (
            empty($config['enabled'])
            || !Sendora_WooCommerce::config_has_action($config)
        ) {
            return;
        }

        parse_str($posted_data, $fields);
        if (!is_array($fields)) {
            return;
        }

        $phone_raw = trim((string) ($fields['billing_phone'] ?? ''));
        if ($phone_raw === '') {
            return;
        }

        $phone = Sendora_Phone::normalize(
            $phone_raw,
            (string) ($settings['default_cc'] ?? '55')
        );
        if ($phone === '') {
            return;
        }

        $first = sanitize_text_field((string) ($fields['billing_first_name'] ?? ''));
        $last = sanitize_text_field((string) ($fields['billing_last_name'] ?? ''));
        $name = trim($first . ' ' . $last);
        $email = sanitize_email((string) ($fields['billing_email'] ?? ''));

        $product = '';
        $total = '';
        if (function_exists('WC') && WC()->cart) {
            $total = wp_strip_all_tags((string) WC()->cart->get_total());
            foreach (WC()->cart->get_cart() as $item) {
                if (!empty($item['data']) && is_object($item['data']) && method_exists($item['data'], 'get_name')) {
                    $product = sanitize_text_field((string) $item['data']->get_name());
                    break;
                }
            }
        }

        $delay = self::delay_minutes($settings);
        $send_at = time() + ($delay * MINUTE_IN_SECONDS);
        $key = self::entry_key($phone);

        $store = self::load_store();
        $store[$key] = [
            'phone' => $phone,
            'name' => $name,
            'email' => $email,
            'product' => $product,
            'total' => $total,
            'captured_at' => time(),
            'send_at' => $send_at,
            'recovered' => false,
            'sent' => false,
        ];
        self::save_store($store);

        self::schedule($key, $send_at);
    }

    public static function mark_recovered_from_object(object $order): void
    {
        if (!method_exists($order, 'get_id')) {
            return;
        }

        self::mark_recovered_by_order((int) $order->get_id());
    }

    public static function mark_recovered_by_order(int $order_id): void
    {
        $order = wc_get_order($order_id);
        if (!is_object($order) || !method_exists($order, 'get_billing_phone')) {
            return;
        }

        $settings = Sendora_Settings::get_settings();
        $phone = Sendora_Phone::normalize(
            (string) $order->get_billing_phone(),
            (string) ($settings['default_cc'] ?? '55')
        );
        if ($phone === '') {
            return;
        }

        $key = self::entry_key($phone);
        $store = self::load_store();
        if (!isset($store[$key])) {
            return;
        }

        $store[$key]['recovered'] = true;
        self::save_store($store);
    }

    public static function process(string $entry_key): void
    {
        $entry_key = sanitize_text_field($entry_key);
        $store = self::load_store();
        $entry = is_array($store[$entry_key] ?? null) ? $store[$entry_key] : null;
        if ($entry === null) {
            return;
        }

        if (!empty($entry['recovered']) || !empty($entry['sent'])) {
            return;
        }

        $settings = Sendora_Settings::get_settings();
        $config = Sendora_WooCommerce::event_config($settings, self::EVENT_KEY);
        if (empty($config['enabled']) || !Sendora_WooCommerce::config_has_action($config)) {
            return;
        }

        $phone = (string) ($entry['phone'] ?? '');
        if ($phone === '') {
            return;
        }

        // If an order was placed after capture, skip.
        if (self::has_recent_order($phone, (int) ($entry['captured_at'] ?? 0))) {
            $store[$entry_key]['recovered'] = true;
            self::save_store($store);

            return;
        }

        $contact = ['phone' => $phone];
        if ((string) ($entry['name'] ?? '') !== '') {
            $contact['name'] = (string) $entry['name'];
        } else {
            $contact['name'] = __('Cliente carrinho', 'sendora');
        }
        if ((string) ($entry['email'] ?? '') !== '') {
            $contact['email'] = (string) $entry['email'];
        }

        $contact = Sendora_WooCommerce::apply_crm_fields($contact, $config);

        if (class_exists('Sendora_Automations')) {
            Sendora_Automations::dispatch('woo.cart_abandoned', [
                'contact' => $contact,
                'phone' => $phone,
                'name' => (string) ($contact['name'] ?? ''),
                'product' => (string) ($entry['product'] ?? ''),
                'order_total' => (string) ($entry['total'] ?? ''),
                'order_total_raw' => (string) ($entry['total'] ?? ''),
                'vars' => [
                    'name' => (string) ($contact['name'] ?? ''),
                    'phone' => $phone,
                    'product' => (string) ($entry['product'] ?? ''),
                    'order_total' => (string) ($entry['total'] ?? ''),
                ],
            ]);
        }

        if ($config['template_id'] === '') {
            $client = Sendora_Api_Client::from_options();
            $upsert = $client->upsert_contact($contact);
            if (empty($upsert['ok'])) {
                Sendora_Logger::log('woo', 'error', sprintf(
                    'Carrinho abandonado → Falha CRM — %s',
                    (string) ($upsert['error'] ?? '')
                ), ['event' => self::EVENT_KEY]);

                return;
            }
            $store[$entry_key]['sent'] = true;
            self::save_store($store);
            Sendora_Logger::log('woo', 'info', 'Carrinho abandonado → Contato atualizado no CRM (sem mensagem).', [
                'event' => self::EVENT_KEY,
            ]);

            return;
        }

        $result = Sendora_Outbound::upsert_and_send_template(
            $contact,
            $config['template_id'],
            [
                'name' => (string) ($entry['name'] ?? ''),
                'first_name' => explode(' ', (string) ($entry['name'] ?? ''))[0] ?? '',
                'phone' => $phone,
                'product' => (string) ($entry['product'] ?? ''),
                'order_total' => (string) ($entry['total'] ?? ''),
            ],
            [
                'provider' => $config['channel'],
                'instance_id' => $config['instance_id'],
                'template_language' => $config['template_language'],
                'body_vars' => $config['body_vars'],
            ]
        );

        if (empty($result['ok'])) {
            Sendora_Logger::log('woo', 'error', sprintf(
                'Carrinho abandonado → Falha ao enviar — %s',
                (string) ($result['error'] ?? '')
            ), [
                'event' => self::EVENT_KEY,
                'phone' => substr($phone, 0, 4) . '…',
            ]);

            return;
        }

        $store[$entry_key]['sent'] = true;
        self::save_store($store);

        Sendora_Logger::log('woo', 'info', sprintf(
            'Carrinho abandonado → Mensagem: "%s" → Sendora: enviado',
            (string) ($result['template_name'] ?? '')
        ), [
            'event' => self::EVENT_KEY,
        ]);
    }

    /**
     * @param array<string, mixed> $settings
     */
    public static function delay_minutes(array $settings): int
    {
        $events = is_array($settings['woo_events'] ?? null) ? $settings['woo_events'] : [];
        $row = is_array($events[self::EVENT_KEY] ?? null) ? $events[self::EVENT_KEY] : [];
        $delay = (int) ($row['delay_minutes'] ?? 60);

        return max(5, min(10080, $delay > 0 ? $delay : 60));
    }

    private static function schedule(string $entry_key, int $timestamp): void
    {
        $args = [$entry_key];

        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions(self::PROCESS_HOOK, $args, 'sendora');
        }
        if (function_exists('as_schedule_single_action')) {
            as_schedule_single_action($timestamp, self::PROCESS_HOOK, $args, 'sendora');

            return;
        }

        wp_clear_scheduled_hook(self::PROCESS_HOOK, $args);
        wp_schedule_single_event($timestamp, self::PROCESS_HOOK, $args);
    }

    private static function entry_key(string $phone): string
    {
        return 'p_' . hash('sha256', $phone);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function load_store(): array
    {
        $raw = get_option(self::OPTION_KEY, []);

        return is_array($raw) ? $raw : [];
    }

    /**
     * @param array<string, array<string, mixed>> $store
     */
    private static function save_store(array $store): void
    {
        // Keep store bounded.
        if (count($store) > 500) {
            uasort($store, static function (array $a, array $b): int {
                return ((int) ($b['captured_at'] ?? 0)) <=> ((int) ($a['captured_at'] ?? 0));
            });
            $store = array_slice($store, 0, 500, true);
        }

        update_option(self::OPTION_KEY, $store, false);
    }

    private static function has_recent_order(string $phone, int $since): bool
    {
        if (!function_exists('wc_get_orders')) {
            return false;
        }

        $orders = wc_get_orders([
            'limit' => 5,
            'orderby' => 'date',
            'order' => 'DESC',
            'date_created' => '>=' . gmdate('Y-m-d H:i:s', max(0, $since - 60)),
            'status' => ['pending', 'processing', 'on-hold', 'completed'],
        ]);

        if (!is_array($orders)) {
            return false;
        }

        $settings = Sendora_Settings::get_settings();
        $cc = (string) ($settings['default_cc'] ?? '55');

        foreach ($orders as $order) {
            if (!is_object($order) || !method_exists($order, 'get_billing_phone')) {
                continue;
            }
            $normalized = Sendora_Phone::normalize((string) $order->get_billing_phone(), $cc);
            if ($normalized === $phone) {
                return true;
            }
        }

        return false;
    }
}
