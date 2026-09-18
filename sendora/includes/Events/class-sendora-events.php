<?php
/**
 * Internal event bus — maps WP events to Sendora Public API actions.
 *
 * No invent endpoints: lead/order events become contacts + optional flow/message.
 *
 * @package Sendora
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Sendora_Events
{
    public const QUEUE_HOOK = 'sendora_process_event';

    /**
     * @param array<string, mixed> $payload
     * @return array{ok: bool, status: int, error: ?string, queued?: bool}
     */
    public static function dispatch(string $event, array $payload = [], bool $async = false): array
    {
        $event = sanitize_key($event);
        if ($event === '') {
            return ['ok' => false, 'status' => 0, 'error' => 'Invalid event.'];
        }

        if ($async && self::can_queue()) {
            as_enqueue_async_action(
                self::QUEUE_HOOK,
                [$event, $payload],
                'sendora'
            );

            return ['ok' => true, 'status' => 0, 'error' => null, 'queued' => true];
        }

        return self::process($event, $payload);
    }

    public static function register_hooks(): void
    {
        add_action(self::QUEUE_HOOK, [self::class, 'process_queued'], 10, 2);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function process_queued(string $event, array $payload = []): void
    {
        self::process($event, $payload);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{ok: bool, status: int, error: ?string}
     */
    public static function process(string $event, array $payload): array
    {
        $started = microtime(true);
        $contact = is_array($payload['contact'] ?? null) ? $payload['contact'] : [];
        $phone = Sendora_Phone::normalize(
            (string) ($contact['phone'] ?? $payload['phone'] ?? ''),
            (string) (Sendora_Settings::get_settings()['default_cc'] ?? '55')
        );

        if ($phone === '' && self::event_needs_phone($event)) {
            $result = ['ok' => false, 'status' => 0, 'error' => 'Phone is required.'];
            self::log_event($event, $result, $payload, $started);

            return $result;
        }

        $client = Sendora_Api_Client::from_options();
        $fields = [];
        if ($phone !== '') {
            $fields['phone'] = $phone;
        }
        $name = sanitize_text_field((string) ($contact['name'] ?? ''));
        $email = sanitize_email((string) ($contact['email'] ?? ''));
        if ($name !== '') {
            $fields['name'] = $name;
        }
        if ($email !== '') {
            $fields['email'] = $email;
        }

        if ($fields !== [] && isset($fields['phone'])) {
            $upsert = $client->upsert_contact($fields);
            if (empty($upsert['ok'])) {
                $result = [
                    'ok' => false,
                    'status' => (int) ($upsert['status'] ?? 0),
                    'error' => (string) ($upsert['error'] ?? 'Contact upsert failed.'),
                ];
                self::log_event($event, $result, $payload, $started);

                return $result;
            }
        }

        $flow_id = sanitize_text_field((string) ($payload['flow_id'] ?? ''));
        if ($flow_id !== '' && $phone !== '') {
            $message = isset($payload['message']) ? (string) $payload['message'] : null;
            $flow = $client->trigger_flow($flow_id, $phone, $message);
            if (empty($flow['ok'])) {
                $result = [
                    'ok' => false,
                    'status' => (int) ($flow['status'] ?? 0),
                    'error' => (string) ($flow['error'] ?? 'Flow trigger failed.'),
                ];
                self::log_event($event, $result, $payload, $started);

                return $result;
            }
        }

        $send_message = (string) ($payload['send_message'] ?? '');
        if ($send_message !== '' && $phone !== '') {
            $msg = $client->send_message([
                'phone' => $phone,
                'message' => $send_message,
            ]);
            if (empty($msg['ok'])) {
                $result = [
                    'ok' => false,
                    'status' => (int) ($msg['status'] ?? 0),
                    'error' => (string) ($msg['error'] ?? 'Message send failed.'),
                ];
                self::log_event($event, $result, $payload, $started);

                return $result;
            }
        }

        $result = ['ok' => true, 'status' => 200, 'error' => null];
        self::log_event($event, $result, $payload, $started);

        return $result;
    }

    private static function event_needs_phone(string $event): bool
    {
        return !in_array($event, ['page_view', 'whatsapp_clicked'], true);
    }

    private static function can_queue(): bool
    {
        return function_exists('as_enqueue_async_action');
    }

    /**
     * @param array{ok: bool, status: int, error: ?string} $result
     * @param array<string, mixed> $payload
     */
    private static function log_event(string $event, array $result, array $payload, float $started): void
    {
        if (!class_exists('Sendora_Logger')) {
            return;
        }

        $level = !empty($result['ok']) ? 'info' : 'error';
        $message = !empty($result['ok'])
            ? sprintf('Event %s processed.', $event)
            : sprintf('Event %s failed: %s', $event, (string) ($result['error'] ?? 'error'));

        $context = [
            'event' => $event,
            'status' => (int) ($result['status'] ?? 0),
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
        ];
        if (isset($payload['source'])) {
            $context['source'] = sanitize_key((string) $payload['source']);
        }
        if (isset($payload['order_id'])) {
            $context['order_id'] = absint($payload['order_id']);
        }

        Sendora_Logger::log('event', $level, $message, $context);
    }
}
