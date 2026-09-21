<?php
/**
 * WooCommerce order events → Sendora message templates.
 *
 * @package Sendora
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Sendora_WooCommerce
{
    private const PROCESS_HOOK = 'sendora_process_woo_event';
    private const MAX_RETRIES = 2;

    public const EVENT_ORDER_CREATED = 'order_created';
    public const EVENT_PAYMENT_APPROVED = 'payment_approved';
    public const EVENT_ORDER_PROCESSING = 'order_processing';
    public const EVENT_ORDER_COMPLETED = 'order_completed';
    public const EVENT_ORDER_CANCELLED = 'order_cancelled';
    public const EVENT_ORDER_REFUNDED = 'order_refunded';
    public const EVENT_ORDER_SHIPPED = 'order_shipped';
    public const EVENT_ORDER_DELIVERED = 'order_delivered';

    /**
     * @return array<string, array{label: string, description: string, icon: string, tracking?: bool}>
     */
    public static function event_definitions(): array
    {
        return [
            self::EVENT_ORDER_CREATED => [
                'label' => __('Pedido criado', 'sendora'),
                'description' => __('Quando um novo pedido é criado.', 'sendora'),
                'icon' => 'cart',
            ],
            Sendora_Abandoned_Cart::EVENT_KEY => [
                'label' => __('Carrinho abandonado', 'sendora'),
                'description' => __('Quando o cliente preenche o checkout e não finaliza a compra.', 'sendora'),
                'icon' => 'bag',
                'abandoned' => true,
            ],
            self::EVENT_PAYMENT_APPROVED => [
                'label' => __('Pagamento aprovado', 'sendora'),
                'description' => __('Envie uma mensagem quando o pagamento for confirmado.', 'sendora'),
                'icon' => 'card',
            ],
            self::EVENT_ORDER_PROCESSING => [
                'label' => __('Pedido processando', 'sendora'),
                'description' => __('Quando o pedido passa para processando.', 'sendora'),
                'icon' => 'gear',
            ],
            self::EVENT_ORDER_COMPLETED => [
                'label' => __('Pedido concluído', 'sendora'),
                'description' => __('Quando o pedido é marcado como concluído.', 'sendora'),
                'icon' => 'check',
            ],
            self::EVENT_ORDER_CANCELLED => [
                'label' => __('Pedido cancelado', 'sendora'),
                'description' => __('Avise o cliente quando o pedido for cancelado.', 'sendora'),
                'icon' => 'cancel',
            ],
            self::EVENT_ORDER_REFUNDED => [
                'label' => __('Pedido reembolsado', 'sendora'),
                'description' => __('Quando um reembolso é registrado no pedido.', 'sendora'),
                'icon' => 'refund',
            ],
            self::EVENT_ORDER_SHIPPED => [
                'label' => __('Pedido enviado', 'sendora'),
                'description' => __('Quando houver código de rastreio ou status de envio.', 'sendora'),
                'icon' => 'truck',
                'tracking' => true,
            ],
            self::EVENT_ORDER_DELIVERED => [
                'label' => __('Pedido entregue', 'sendora'),
                'description' => __('Quando o pedido for marcado como entregue.', 'sendora'),
                'icon' => 'box',
                'tracking' => true,
            ],
        ];
    }

    public function run(): void
    {
        add_action('woocommerce_checkout_order_processed', [self::class, 'on_order_created']);
        add_action('woocommerce_store_api_checkout_order_processed', [self::class, 'on_order_created_from_object']);
        add_action('woocommerce_payment_complete', [self::class, 'on_payment_complete']);
        add_action('woocommerce_order_status_processing', [self::class, 'on_processing']);
        add_action('woocommerce_order_status_completed', [self::class, 'on_completed']);
        add_action('woocommerce_order_status_cancelled', [self::class, 'on_cancelled']);
        add_action('woocommerce_order_status_refunded', [self::class, 'on_refunded']);
        add_action('woocommerce_order_status_shipped', [self::class, 'on_shipped']);
        add_action('woocommerce_order_status_delivered', [self::class, 'on_delivered']);
        add_action('woocommerce_order_status_changed', [self::class, 'on_status_changed'], 10, 3);
        add_action(self::PROCESS_HOOK, [self::class, 'process_event'], 10, 3);
        // Back-compat with older scheduled retries.
        add_action('sendora_retry_woocommerce_sync', [self::class, 'legacy_retry'], 10, 3);
    }

    public static function on_order_created_from_object(object $order): void
    {
        if (!method_exists($order, 'get_id')) {
            return;
        }

        self::queue_event((int) $order->get_id(), self::EVENT_ORDER_CREATED);
    }

    public static function on_order_created(int $order_id): void
    {
        self::queue_event($order_id, self::EVENT_ORDER_CREATED);
    }

    public static function on_payment_complete(int $order_id): void
    {
        self::queue_event($order_id, self::EVENT_PAYMENT_APPROVED);
    }

    public static function on_processing(int $order_id): void
    {
        self::queue_event($order_id, self::EVENT_ORDER_PROCESSING);
    }

    public static function on_completed(int $order_id): void
    {
        self::queue_event($order_id, self::EVENT_ORDER_COMPLETED);
    }

    public static function on_cancelled(int $order_id): void
    {
        self::queue_event($order_id, self::EVENT_ORDER_CANCELLED);
    }

    public static function on_refunded(int $order_id): void
    {
        self::queue_event($order_id, self::EVENT_ORDER_REFUNDED);
    }

    public static function on_shipped(int $order_id): void
    {
        self::queue_event($order_id, self::EVENT_ORDER_SHIPPED);
    }

    public static function on_delivered(int $order_id): void
    {
        self::queue_event($order_id, self::EVENT_ORDER_DELIVERED);
    }

    /**
     * Catch alternate shipping statuses (e.g. "shipping") not covered by dedicated hooks.
     */
    public static function on_status_changed(int $order_id, string $from, string $to): void
    {
        unset($from);

        $normalized = strtolower(str_replace('wc-', '', $to));
        // Dedicated hooks already cover shipped/delivered — only map aliases here.
        if ($normalized === 'shipping') {
            self::queue_event($order_id, self::EVENT_ORDER_SHIPPED);
        }
    }

    /**
     * @deprecated Kept for Action Scheduler jobs queued by older plugin versions.
     */
    public static function legacy_retry(int $order_id, string $event, int $attempt): void
    {
        $map = [
            'created' => self::EVENT_ORDER_CREATED,
            'paid' => self::EVENT_PAYMENT_APPROVED,
            'cancelled' => self::EVENT_ORDER_CANCELLED,
        ];
        $mapped = $map[$event] ?? $event;
        if (!isset(self::event_definitions()[$mapped])) {
            return;
        }

        self::process_event($order_id, $mapped, max(1, $attempt));
    }

    /**
     * Public aliases used by older tests / callers.
     */
    public static function order_created(int $order_id): void
    {
        self::on_order_created($order_id);
    }

    public static function payment_complete(int $order_id): void
    {
        self::on_payment_complete($order_id);
    }

    public static function order_cancelled(int $order_id): void
    {
        self::on_cancelled($order_id);
    }

    public static function retry_sync(int $order_id, string $event, int $attempt): void
    {
        self::legacy_retry($order_id, $event, $attempt);
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

    public static function queue_event(int $order_id, string $event_key): void
    {
        $defs = self::event_definitions();
        if (!isset($defs[$event_key]) || !empty($defs[$event_key]['abandoned'])) {
            return;
        }

        // Automations run independently of the Woo card toggle.
        if (class_exists('Sendora_Automations') && function_exists('wc_get_order')) {
            $order = wc_get_order($order_id);
            if (is_object($order)) {
                Sendora_Automations::dispatch(
                    'woo.' . $event_key,
                    Sendora_Automations::context_from_order($order)
                );
            }
        }

        $settings = Sendora_Settings::get_settings();
        $config = self::event_config($settings, $event_key);
        if (empty($config['enabled']) || !self::config_has_action($config)) {
            return;
        }

        $args = [$order_id, $event_key, 0];

        if (function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action(self::PROCESS_HOOK, $args, 'sendora');

            return;
        }

        wp_schedule_single_event(time() + 5, self::PROCESS_HOOK, $args);
    }

    /**
     * @param array{enabled?: bool, template_id?: string, funnel_id?: string, stage?: string, tags?: array<int, string>} $config
     */
    public static function config_has_action(array $config): bool
    {
        if (trim((string) ($config['template_id'] ?? '')) !== '') {
            return true;
        }
        if (
            trim((string) ($config['funnel_id'] ?? '')) !== ''
            && trim((string) ($config['stage'] ?? '')) !== ''
        ) {
            return true;
        }

        return !empty($config['tags']) && is_array($config['tags']);
    }

    public static function process_event(int $order_id, string $event_key, int $attempt = 0): void
    {
        if (!isset(self::event_definitions()[$event_key])) {
            return;
        }

        $settings = Sendora_Settings::get_settings();
        $config = self::event_config($settings, $event_key);
        if (empty($config['enabled']) || !self::config_has_action($config)) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!is_object($order)) {
            self::log_line($order_id, $event_key, 'error', __('Pedido indisponível.', 'sendora'));

            return;
        }

        $defs = self::event_definitions();
        if (!empty($defs[$event_key]['tracking']) && !self::order_has_tracking($order) && !self::is_tracking_status($order)) {
            if ($event_key === self::EVENT_ORDER_SHIPPED) {
                self::log_line(
                    $order_id,
                    $event_key,
                    'info',
                    __('Envio ignorado: sem código de rastreio no pedido.', 'sendora')
                );

                return;
            }
        }

        $default_cc = (string) ($settings['default_cc'] ?? '55');
        $payload = self::contact_payload($order, $default_cc);
        if (empty($payload['phone'])) {
            self::log_line(
                $order_id,
                $event_key,
                'error',
                __('Falha ao enviar', 'sendora') . ' — ' . __('Motivo: telefone inválido', 'sendora')
            );
            if (method_exists($order, 'update_meta_data')) {
                $order->update_meta_data('_sendora_last_error', 'telefone inválido');
                $order->save();
            }

            return;
        }

        if (empty($payload['name'])) {
            $payload['name'] = sprintf(
                /* translators: %s: order number */
                __('Cliente #%s', 'sendora'),
                method_exists($order, 'get_order_number') ? (string) $order->get_order_number() : (string) $order_id
            );
        }

        $payload = self::apply_crm_fields($payload, $config);

        $client = Sendora_Api_Client::from_options();
        $contact_result = $client->upsert_contact($payload);
        if (empty($contact_result['ok'])) {
            self::handle_failure(
                $order,
                $event_key,
                $attempt,
                (string) ($contact_result['error'] ?? __('Falha ao sincronizar contato.', 'sendora')),
                (int) ($contact_result['status'] ?? 0)
            );

            return;
        }

        $contact_id = self::contact_id($contact_result['data'] ?? null);
        if ($contact_id !== '' && method_exists($order, 'update_meta_data')) {
            $order->update_meta_data('_sendora_contact_id', $contact_id);
        }

        $crm_bits = [];
        if ($config['funnel_id'] !== '' && $config['stage'] !== '') {
            $crm_bits[] = sprintf('CRM: %s/%s', $config['funnel_id'], $config['stage']);
        }
        if ($config['tags'] !== []) {
            $crm_bits[] = 'tags: ' . implode(', ', $config['tags']);
        }

        if ($config['template_id'] === '') {
            if (method_exists($order, 'update_meta_data')) {
                $order->update_meta_data('_sendora_last_sync_at', current_time('mysql', true));
                $order->delete_meta_data('_sendora_last_error');
                $order->save();
            }
            self::log_line(
                $order_id,
                $event_key,
                'info',
                ($crm_bits !== [] ? implode(' · ', $crm_bits) . ' · ' : '')
                . __('Contato atualizado no CRM (sem mensagem).', 'sendora'),
                [
                    'funnel_id' => $config['funnel_id'],
                    'stage' => $config['stage'],
                    'tags' => $config['tags'],
                ]
            );

            return;
        }

        $ctx = self::build_context($order, $payload);
        $send = Sendora_Outbound::upsert_and_send_template(
            $payload,
            $config['template_id'],
            $ctx,
            [
                'provider' => $config['channel'],
                'instance_id' => $config['instance_id'],
                'template_language' => $config['template_language'],
                'body_vars' => $config['body_vars'],
                'skip_upsert' => true,
            ]
        );

        // Contact already upserted above — Outbound upserts again (idempotent). On failure after CRM sync, still retry.
        if (empty($send['ok'])) {
            self::handle_failure(
                $order,
                $event_key,
                $attempt,
                (string) ($send['error'] ?? __('Falha ao enviar mensagem.', 'sendora')),
                (int) ($send['status'] ?? 0),
                (string) ($send['template_name'] ?? $config['template_id']),
                $payload['phone']
            );

            return;
        }

        if (method_exists($order, 'update_meta_data')) {
            $order->update_meta_data('_sendora_last_sync_at', current_time('mysql', true));
            $order->delete_meta_data('_sendora_last_error');
            $order->save();
        }

        $template_name = (string) ($send['template_name'] ?? $config['template_id']);
        $provider_label = ($config['channel'] === Sendora_Outbound::PROVIDER_META)
            ? 'Meta Oficial'
            : 'WhatsApp';
        $suffix = $crm_bits !== [] ? ' · ' . implode(' · ', $crm_bits) : '';
        self::log_line(
            $order_id,
            $event_key,
            'info',
            sprintf(
                /* translators: 1: template name, 2: channel label, 3: phone */
                __('Mensagem: "%1$s" → %2$s: %3$s → Sendora: enviado', 'sendora'),
                $template_name,
                $provider_label,
                self::mask_phone($payload['phone'])
            ) . $suffix,
            [
                'template_id' => $config['template_id'],
                'channel' => $config['channel'],
                'instance_id' => $config['instance_id'],
                'funnel_id' => $config['funnel_id'],
                'stage' => $config['stage'],
                'tags' => $config['tags'],
            ]
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @param array{funnel_id: string, stage: string, tags: array<int, string>} $config
     * @return array<string, mixed>
     */
    public static function apply_crm_fields(array $payload, array $config): array
    {
        if ($config['funnel_id'] !== '' && $config['stage'] !== '') {
            $payload['funnel_id'] = $config['funnel_id'];
            $payload['stage'] = $config['stage'];
        }
        if ($config['tags'] !== []) {
            $payload['tags'] = array_values($config['tags']);
        }

        return $payload;
    }

    /**
     * @return array{enabled: bool, channel: string, instance_id: string, template_id: string, template_language: string, body_vars: array<int, string>, delay_minutes: int, funnel_id: string, stage: string, tags: array<int, string>}
     */
    public static function event_config(array $settings, string $event_key): array
    {
        $events = is_array($settings['woo_events'] ?? null) ? $settings['woo_events'] : [];
        $row = is_array($events[$event_key] ?? null) ? $events[$event_key] : [];
        $delay = (int) ($row['delay_minutes'] ?? 60);
        $tags = is_array($row['tags'] ?? null)
            ? Sendora_Settings::sanitize_tag_list($row['tags'])
            : Sendora_Settings::sanitize_tag_list((string) ($row['tags'] ?? ''));
        $channel_fields = Sendora_Settings::sanitize_message_channel_fields($row);

        return array_merge(
            $channel_fields,
            [
                'enabled' => !empty($row['enabled']),
                'delay_minutes' => max(5, min(10080, $delay > 0 ? $delay : 60)),
                'funnel_id' => Sendora_Settings::sanitize_id_field((string) ($row['funnel_id'] ?? '')),
                'stage' => Sendora_Settings::sanitize_id_field((string) ($row['stage'] ?? '')),
                'tags' => $tags,
            ]
        );
    }

    /**
     * @return array{id: string, name: string, content: string, type: string}|null
     */
    public static function find_template(string $template_id, array $settings): ?array
    {
        $template_id = sanitize_text_field($template_id);
        if ($template_id === '') {
            return null;
        }

        foreach (Sendora_Settings::load_templates_public($settings) as $template) {
            if (($template['id'] ?? '') === $template_id) {
                return $template;
            }
        }

        // Cache miss — fetch once without relying only on transient.
        $result = Sendora_Api_Client::from_options()->list_templates();
        if (empty($result['ok']) || !is_array($result['data'])) {
            return null;
        }

        $items = isset($result['data']['data']) && is_array($result['data']['data'])
            ? $result['data']['data']
            : $result['data'];

        foreach ($items as $row) {
            if (!is_array($row) || (string) ($row['id'] ?? '') !== $template_id) {
                continue;
            }

            return [
                'id' => sanitize_text_field((string) $row['id']),
                'name' => sanitize_text_field((string) ($row['name'] ?? $row['id'])),
                'content' => (string) ($row['content'] ?? ''),
                'type' => sanitize_text_field((string) ($row['type'] ?? 'text')),
            ];
        }

        return null;
    }

    /**
     * @param array<string, string> $contact
     * @return array<string, string>
     */
    public static function build_context(object $order, array $contact): array
    {
        $first = sanitize_text_field((string) $order->get_billing_first_name());
        $name = (string) ($contact['name'] ?? trim($first . ' ' . (string) $order->get_billing_last_name()));
        $phone = (string) ($contact['phone'] ?? '');
        $status = method_exists($order, 'get_status')
            ? sanitize_text_field((string) $order->get_status())
            : '';
        $total = '';
        if (method_exists($order, 'get_formatted_order_total')) {
            $total = wp_strip_all_tags((string) $order->get_formatted_order_total());
        } elseif (method_exists($order, 'get_total')) {
            $total = (string) $order->get_total();
        }

        $date = '';
        if (method_exists($order, 'get_date_created')) {
            $created = $order->get_date_created();
            if (is_object($created) && method_exists($created, 'date_i18n')) {
                $date = (string) $created->date_i18n('d/m/Y');
            } elseif (is_object($created) && method_exists($created, 'format')) {
                $date = (string) $created->format('d/m/Y');
            }
        }

        $payment = method_exists($order, 'get_payment_method_title')
            ? sanitize_text_field((string) $order->get_payment_method_title())
            : '';

        $shipping = '';
        if (method_exists($order, 'get_formatted_shipping_address')) {
            $shipping = wp_strip_all_tags((string) $order->get_formatted_shipping_address());
        }
        if ($shipping === '' && method_exists($order, 'get_formatted_billing_address')) {
            $shipping = wp_strip_all_tags((string) $order->get_formatted_billing_address());
        }

        $product = '';
        if (method_exists($order, 'get_items')) {
            $items = $order->get_items();
            if (is_array($items) || $items instanceof Traversable) {
                foreach ($items as $item) {
                    if (is_object($item) && method_exists($item, 'get_name')) {
                        $product = sanitize_text_field((string) $item->get_name());
                        break;
                    }
                }
            }
        }

        $link = '';
        if (method_exists($order, 'get_view_order_url')) {
            $link = (string) $order->get_view_order_url();
        } elseif (function_exists('wc_get_endpoint_url') && method_exists($order, 'get_id')) {
            $link = wc_get_endpoint_url('view-order', (string) $order->get_id(), wc_get_page_permalink('myaccount'));
        }

        $store = function_exists('get_bloginfo') ? (string) get_bloginfo('name') : '';

        return [
            'name' => $name,
            'first_name' => $first,
            'phone' => $phone,
            'company' => $store,
            'store_name' => $store,
            'order_id' => method_exists($order, 'get_id') ? (string) $order->get_id() : '',
            'order_number' => method_exists($order, 'get_order_number')
                ? (string) $order->get_order_number()
                : '',
            'order_total' => $total,
            'order_status' => $status,
            'order_date' => $date,
            'payment_method' => $payment,
            'shipping_address' => $shipping,
            'product' => $product,
            'tracking' => self::tracking_code($order),
            'order_link' => $link,
            'coupon' => '',
        ];
    }

    public static function order_has_tracking(object $order): bool
    {
        return self::tracking_code($order) !== '';
    }

    public static function tracking_code(object $order): string
    {
        if (!method_exists($order, 'get_meta')) {
            return '';
        }

        $keys = [
            '_tracking_number',
            'tracking_number',
            '_correios_tracking_code',
            'correios_tracking_code',
            'melhorenvio_tracking',
            '_melhorenvio_tracking_code',
            '_ywot_tracking_code',
            '_sendora_tracking_code',
        ];

        foreach ($keys as $key) {
            $value = $order->get_meta($key, true);
            if (is_string($value) && trim($value) !== '') {
                return sanitize_text_field(trim($value));
            }
        }

        $items = $order->get_meta('_wc_shipment_tracking_items', true);
        if (is_array($items) && $items !== []) {
            $first = $items[0] ?? null;
            if (is_array($first)) {
                foreach (['tracking_number', 'tracking_id', 'number'] as $field) {
                    if (!empty($first[$field]) && is_scalar($first[$field])) {
                        return sanitize_text_field((string) $first[$field]);
                    }
                }
            }
        }

        return '';
    }

    private static function is_tracking_status(object $order): bool
    {
        if (!method_exists($order, 'get_status')) {
            return false;
        }

        $status = strtolower(str_replace('wc-', '', (string) $order->get_status()));

        return in_array($status, ['shipped', 'shipping', 'delivered'], true);
    }

    private static function handle_failure(
        object $order,
        string $event_key,
        int $attempt,
        string $error,
        int $status = 0,
        string $template_name = '',
        string $phone = ''
    ): void {
        $safe_error = wp_strip_all_tags($error);
        if (method_exists($order, 'update_meta_data')) {
            $order->update_meta_data('_sendora_last_error', $safe_error);
            $order->save();
        }

        $detail = __('Falha ao enviar', 'sendora') . ' — ' . $safe_error;
        if ($template_name !== '') {
            $detail = sprintf(
                /* translators: 1: template name, 2: error */
                __('Mensagem: "%1$s" → Falha ao enviar — %2$s', 'sendora'),
                $template_name,
                $safe_error
            );
        }

        self::log_line(
            (int) $order->get_id(),
            $event_key,
            'error',
            $detail,
            array_filter([
                'status' => $status > 0 ? $status : null,
                'phone' => $phone !== '' ? self::mask_phone($phone) : null,
            ])
        );

        if ($attempt < self::MAX_RETRIES) {
            self::schedule_retry((int) $order->get_id(), $event_key, $attempt + 1);

            return;
        }

        if (method_exists($order, 'add_order_note')) {
            $order->add_order_note(
                sprintf(
                    /* translators: %s: sanitized Sendora error */
                    __('Falha no envio Sendora após novas tentativas: %s', 'sendora'),
                    $safe_error
                )
            );
        }
    }

    private static function schedule_retry(int $order_id, string $event_key, int $attempt): void
    {
        $args = [$order_id, $event_key, $attempt];

        if (function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action(self::PROCESS_HOOK, $args, 'sendora');

            return;
        }

        wp_schedule_single_event(time() + (60 * $attempt), self::PROCESS_HOOK, $args);
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

    /**
     * @param array<string, mixed> $context
     */
    private static function log_line(
        int $order_id,
        string $event_key,
        string $level,
        string $message,
        array $context = []
    ): void {
        $label = self::event_definitions()[$event_key]['label'] ?? $event_key;
        Sendora_Logger::log(
            'woo',
            $level,
            sprintf('Pedido #%d · %s → %s', $order_id, $label, $message),
            array_merge(['order_id' => $order_id, 'event' => $event_key], $context)
        );
    }

    private static function mask_phone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) < 4) {
            return '+•••';
        }

        return '+' . substr($digits, 0, 2) . '…' . substr($digits, -4);
    }
}
