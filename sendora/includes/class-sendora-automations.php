<?php
/**
 * Hybrid automations: WP trigger + conditions + delay → Sendora actions.
 *
 * @package Sendora
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Sendora_Automations
{
    public const PROCESS_HOOK = 'sendora_process_automation';

    /**
     * @return array<string, array{label: string, group: string}>
     */
    public static function trigger_catalog(): array
    {
        $triggers = [
            'form.native' => [
                'label' => __('Formulário Sendora enviado', 'sendora'),
                'group' => 'forms',
            ],
            'form.cf7' => [
                'label' => __('Contact Form 7 enviado', 'sendora'),
                'group' => 'forms',
            ],
        ];

        if (class_exists('Sendora_WooCommerce')) {
            foreach (Sendora_WooCommerce::event_definitions() as $key => $meta) {
                $triggers['woo.' . $key] = [
                    'label' => (string) $meta['label'],
                    'group' => 'woo',
                ];
            }
        }

        return $triggers;
    }

    /**
     * @return array<string, string>
     */
    public static function condition_fields(): array
    {
        return [
            'has_phone' => __('Tem telefone', 'sendora'),
            'order_total' => __('Valor do pedido', 'sendora'),
            'order_status' => __('Status do pedido', 'sendora'),
            'payment_method' => __('Método de pagamento', 'sendora'),
            'product' => __('Produto (texto)', 'sendora'),
            'cf7_form_id' => __('ID do formulário CF7', 'sendora'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function condition_ops(): array
    {
        return [
            'eq' => __('igual a', 'sendora'),
            'neq' => __('diferente de', 'sendora'),
            'gte' => __('≥', 'sendora'),
            'lte' => __('≤', 'sendora'),
            'contains' => __('contém', 'sendora'),
            'empty' => __('vazio', 'sendora'),
            'not_empty' => __('não vazio', 'sendora'),
        ];
    }

    public static function register_hooks(): void
    {
        add_action(self::PROCESS_HOOK, [self::class, 'process_queued'], 10, 3);
    }

    /**
     * Triggers use dotted keys (form.native, woo.order_created); WP sanitize_key strips dots.
     */
    public static function sanitize_trigger(string $trigger): string
    {
        $trigger = strtolower(trim($trigger));

        return preg_replace('/[^a-z0-9_.\-]/', '', $trigger) ?? '';
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function dispatch(string $trigger, array $context = []): void
    {
        $trigger = self::sanitize_trigger($trigger);
        if ($trigger === '' || !isset(self::trigger_catalog()[$trigger])) {
            return;
        }

        $settings = Sendora_Settings::get_settings();
        $rules = is_array($settings['automations'] ?? null) ? $settings['automations'] : [];

        foreach ($rules as $rule) {
            if (!is_array($rule) || empty($rule['enabled'])) {
                continue;
            }
            if ((string) ($rule['trigger'] ?? '') !== $trigger) {
                continue;
            }
            if (!self::conditions_match($rule['conditions'] ?? [], $context)) {
                continue;
            }

            $rule_id = (string) ($rule['id'] ?? '');
            if ($rule_id === '') {
                continue;
            }

            $delay = max(0, min(10080, (int) ($rule['delay_minutes'] ?? 0)));
            $args = [$rule_id, $trigger, $context];

            if ($delay > 0) {
                if (function_exists('as_schedule_single_action')) {
                    as_schedule_single_action(time() + ($delay * 60), self::PROCESS_HOOK, $args, 'sendora');
                } else {
                    wp_schedule_single_event(time() + ($delay * 60), self::PROCESS_HOOK, $args);
                }
                Sendora_Logger::log('automation', 'info', sprintf(
                    'Automação "%s" agendada em %d min.',
                    (string) ($rule['name'] ?? $rule_id),
                    $delay
                ), ['trigger' => $trigger, 'rule_id' => $rule_id]);

                continue;
            }

            self::run_rule($rule, $context);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function process_queued(string $rule_id, string $trigger, array $context = []): void
    {
        $settings = Sendora_Settings::get_settings();
        $rules = is_array($settings['automations'] ?? null) ? $settings['automations'] : [];
        foreach ($rules as $rule) {
            if (!is_array($rule) || (string) ($rule['id'] ?? '') !== $rule_id) {
                continue;
            }
            if (empty($rule['enabled'])) {
                return;
            }
            if ((string) ($rule['trigger'] ?? '') !== $trigger) {
                return;
            }
            if (!self::conditions_match($rule['conditions'] ?? [], $context)) {
                return;
            }
            self::run_rule($rule, $context);

            return;
        }
    }

    /**
     * @param array<string, mixed> $rule
     * @param array<string, mixed> $context
     */
    public static function run_rule(array $rule, array $context): void
    {
        $actions = is_array($rule['actions'] ?? null) ? $rule['actions'] : [];
        $contact = is_array($context['contact'] ?? null) ? $context['contact'] : [];
        $phone = Sendora_Phone::normalize(
            (string) ($contact['phone'] ?? $context['phone'] ?? ''),
            (string) (Sendora_Settings::get_settings()['default_cc'] ?? '55')
        );

        if ($phone === '') {
            Sendora_Logger::log('automation', 'error', sprintf(
                'Automação "%s" — telefone inválido.',
                (string) ($rule['name'] ?? '')
            ), ['rule_id' => (string) ($rule['id'] ?? '')]);

            return;
        }

        $contact['phone'] = $phone;
        if (empty($contact['name']) && !empty($context['name'])) {
            $contact['name'] = (string) $context['name'];
        }

        $funnel_id = Sendora_Settings::sanitize_id_field((string) ($actions['funnel_id'] ?? ''));
        $stage = Sendora_Settings::sanitize_id_field((string) ($actions['stage'] ?? ''));
        $tags = Sendora_Settings::sanitize_tag_list($actions['tags'] ?? []);
        if ($funnel_id !== '' && $stage !== '') {
            $contact['funnel_id'] = $funnel_id;
            $contact['stage'] = $stage;
        }
        if ($tags !== []) {
            $contact['tags'] = $tags;
        }

        $client = Sendora_Api_Client::from_options();
        $upsert = $client->upsert_contact($contact);
        if (empty($upsert['ok'])) {
            Sendora_Logger::log('automation', 'error', sprintf(
                'Automação "%s" — falha CRM: %s',
                (string) ($rule['name'] ?? ''),
                (string) ($upsert['error'] ?? '')
            ), ['rule_id' => (string) ($rule['id'] ?? '')]);

            return;
        }

        $template_id = Sendora_Settings::sanitize_id_field((string) ($actions['template_id'] ?? ''));
        $flow_id = Sendora_Settings::sanitize_id_field((string) ($actions['flow_id'] ?? ''));
        $did_action = false;

        if ($template_id !== '') {
            $vars = is_array($context['vars'] ?? null) ? $context['vars'] : [];
            $vars = array_merge($vars, [
                'name' => (string) ($contact['name'] ?? ''),
                'phone' => $phone,
            ]);
            $send = Sendora_Outbound::upsert_and_send_template(
                $contact,
                $template_id,
                $vars,
                [
                    'provider' => (string) ($actions['channel'] ?? Sendora_Outbound::PROVIDER_EVOLUTION),
                    'instance_id' => (string) ($actions['instance_id'] ?? ''),
                    'template_language' => (string) ($actions['template_language'] ?? 'pt_BR'),
                    'body_vars' => $actions['body_vars'] ?? [],
                    'skip_upsert' => true,
                ]
            );
            if (empty($send['ok'])) {
                Sendora_Logger::log('automation', 'error', sprintf(
                    'Automação "%s" — falha mensagem: %s',
                    (string) ($rule['name'] ?? ''),
                    (string) ($send['error'] ?? '')
                ), ['rule_id' => (string) ($rule['id'] ?? '')]);

                return;
            }
            $did_action = true;
        }

        if ($flow_id !== '') {
            $flow = $client->trigger_flow($flow_id, $phone);
            if (empty($flow['ok'])) {
                Sendora_Logger::log('automation', 'error', sprintf(
                    'Automação "%s" — falha flow: %s',
                    (string) ($rule['name'] ?? ''),
                    (string) ($flow['error'] ?? '')
                ), ['rule_id' => (string) ($rule['id'] ?? ''), 'flow_id' => $flow_id]);

                return;
            }
            $did_action = true;
        }

        if (!$did_action && $funnel_id === '' && $tags === []) {
            Sendora_Logger::log('automation', 'info', sprintf(
                'Automação "%s" — contato atualizado (sem ação).',
                (string) ($rule['name'] ?? '')
            ), ['rule_id' => (string) ($rule['id'] ?? '')]);

            return;
        }

        Sendora_Logger::log('automation', 'info', sprintf(
            'Automação "%s" → executada.',
            (string) ($rule['name'] ?? '')
        ), [
            'rule_id' => (string) ($rule['id'] ?? ''),
            'trigger' => (string) ($rule['trigger'] ?? ''),
            'template_id' => $template_id,
            'flow_id' => $flow_id,
        ]);
    }

    /**
     * @param mixed $conditions
     * @param array<string, mixed> $context
     */
    public static function conditions_match(mixed $conditions, array $context): bool
    {
        if (!is_array($conditions) || $conditions === []) {
            return true;
        }

        foreach ($conditions as $row) {
            if (!is_array($row)) {
                continue;
            }
            $field = sanitize_key((string) ($row['field'] ?? ''));
            $op = sanitize_key((string) ($row['op'] ?? 'eq'));
            $expected = (string) ($row['value'] ?? '');
            if ($field === '' || !isset(self::condition_fields()[$field])) {
                return false;
            }
            $actual = self::context_value($field, $context);
            if (!self::compare($actual, $op, $expected)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function context_value(string $field, array $context): string
    {
        return match ($field) {
            'has_phone' => trim((string) ($context['phone'] ?? $context['contact']['phone'] ?? '')) !== '' ? '1' : '',
            'order_total' => (string) ($context['order_total_raw'] ?? $context['order_total'] ?? ''),
            'order_status' => (string) ($context['order_status'] ?? ''),
            'payment_method' => (string) ($context['payment_method'] ?? ''),
            'product' => (string) ($context['product'] ?? ''),
            'cf7_form_id' => (string) ($context['cf7_form_id'] ?? ''),
            default => (string) ($context[$field] ?? ''),
        };
    }

    private static function compare(string $actual, string $op, string $expected): bool
    {
        return match ($op) {
            'empty' => trim($actual) === '',
            'not_empty' => trim($actual) !== '',
            'contains' => $expected !== '' && str_contains(mb_strtolower($actual), mb_strtolower($expected)),
            'neq' => $actual !== $expected,
            'gte' => (float) preg_replace('/[^\d.,-]/', '', str_replace(',', '.', $actual)) >= (float) $expected,
            'lte' => (float) preg_replace('/[^\d.,-]/', '', str_replace(',', '.', $actual)) <= (float) $expected,
            default => $actual === $expected,
        };
    }

    /**
     * @param mixed $input
     * @return array<int, array<string, mixed>>
     */
    public static function sanitize_rules(mixed $input): array
    {
        if (!is_array($input)) {
            return [];
        }

        $catalog = self::trigger_catalog();
        $out = [];

        foreach ($input as $row) {
            if (!is_array($row)) {
                continue;
            }

            $id = sanitize_key((string) ($row['id'] ?? ''));
            if ($id === '') {
                $id = 'auto_' . substr(md5(wp_json_encode($row) . microtime(true)), 0, 10);
            }

            $trigger = self::sanitize_trigger((string) ($row['trigger'] ?? ''));
            if ($trigger === '' || !isset($catalog[$trigger])) {
                continue;
            }

            $name_raw = sanitize_text_field((string) ($row['name'] ?? ''));

            $enabled = !empty($row['enabled']) && (
                $row['enabled'] === true
                || $row['enabled'] === 1
                || $row['enabled'] === '1'
                || $row['enabled'] === 'on'
            );

            $actions_in = is_array($row['actions'] ?? null) ? $row['actions'] : [];
            $channel_fields = Sendora_Settings::sanitize_message_channel_fields($actions_in);
            $flow_id = Sendora_Settings::sanitize_id_field((string) ($actions_in['flow_id'] ?? ''));
            $funnel_id = Sendora_Settings::sanitize_id_field((string) ($actions_in['funnel_id'] ?? ''));
            $stage = Sendora_Settings::sanitize_id_field((string) ($actions_in['stage'] ?? ''));
            $tags = Sendora_Settings::sanitize_tag_list($actions_in['tags'] ?? []);
            $conditions = self::sanitize_conditions($row['conditions'] ?? []);

            $has_action = $channel_fields['template_id'] !== ''
                || $flow_id !== ''
                || ($funnel_id !== '' && $stage !== '')
                || $tags !== [];

            // Drop the trailing "Nova automação" blank card and other empty drafts.
            if (!$enabled && !$has_action && $name_raw === '' && $conditions === []) {
                continue;
            }

            if ($enabled && !$has_action) {
                $enabled = false;
            }

            $name = $name_raw !== '' ? $name_raw : (string) $catalog[$trigger]['label'];

            $out[] = [
                'id' => $id,
                'name' => $name,
                'enabled' => $enabled,
                'trigger' => $trigger,
                'conditions' => $conditions,
                'delay_minutes' => max(0, min(10080, (int) ($row['delay_minutes'] ?? 0))),
                'actions' => array_merge($channel_fields, [
                    'flow_id' => $flow_id,
                    'funnel_id' => $funnel_id,
                    'stage' => $stage,
                    'tags' => $tags,
                ]),
            ];
        }

        return array_values($out);
    }

    /**
     * @param mixed $input
     * @return array<int, array{field: string, op: string, value: string}>
     */
    public static function sanitize_conditions(mixed $input): array
    {
        if (!is_array($input)) {
            return [];
        }

        $fields = self::condition_fields();
        $ops = self::condition_ops();
        $out = [];

        foreach ($input as $row) {
            if (!is_array($row)) {
                continue;
            }
            $field = sanitize_key((string) ($row['field'] ?? ''));
            $op = sanitize_key((string) ($row['op'] ?? 'eq'));
            if ($field === '' || !isset($fields[$field]) || !isset($ops[$op])) {
                continue;
            }
            $out[] = [
                'field' => $field,
                'op' => $op,
                'value' => sanitize_text_field((string) ($row['value'] ?? '')),
            ];
        }

        return $out;
    }

    /**
     * Build context from a Woo order for automations.
     *
     * @param object $order
     * @return array<string, mixed>
     */
    public static function context_from_order(object $order): array
    {
        $settings = Sendora_Settings::get_settings();
        $cc = (string) ($settings['default_cc'] ?? '55');
        $contact = class_exists('Sendora_WooCommerce')
            ? Sendora_WooCommerce::contact_payload($order, $cc)
            : [];

        $total = method_exists($order, 'get_total') ? (string) $order->get_total() : '';
        $status = method_exists($order, 'get_status') ? (string) $order->get_status() : '';
        $payment = method_exists($order, 'get_payment_method_title')
            ? (string) $order->get_payment_method_title()
            : '';

        $product = '';
        if (method_exists($order, 'get_items')) {
            $names = [];
            foreach ($order->get_items() as $item) {
                if (is_object($item) && method_exists($item, 'get_name')) {
                    $names[] = (string) $item->get_name();
                }
            }
            $product = implode(', ', $names);
        }

        $vars = class_exists('Sendora_WooCommerce')
            ? Sendora_WooCommerce::build_context($order, $contact)
            : [];

        return [
            'contact' => $contact,
            'phone' => (string) ($contact['phone'] ?? ''),
            'name' => (string) ($contact['name'] ?? ''),
            'order_id' => method_exists($order, 'get_id') ? (int) $order->get_id() : 0,
            'order_total' => $total,
            'order_total_raw' => $total,
            'order_status' => $status,
            'payment_method' => $payment,
            'product' => $product,
            'vars' => $vars,
        ];
    }
}
