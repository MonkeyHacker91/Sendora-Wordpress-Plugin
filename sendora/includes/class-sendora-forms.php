<?php
/**
 * Public lead form shortcode and AJAX handler.
 *
 * @package Sendora
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Sendora_Forms
{
    private const NONCE_ACTION = 'sendora_form_submit';
    private const RATE_LIMIT = 5;
    private const RATE_WINDOW = 600;

    public function run(): void
    {
        add_shortcode('sendora_form', [self::class, 'render_shortcode']);
        add_action('wp_ajax_sendora_form_submit', [self::class, 'ajax_submit']);
        add_action('wp_ajax_nopriv_sendora_form_submit', [self::class, 'ajax_submit']);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function render_shortcode(array $attributes = []): string
    {
        self::enqueue_assets();

        $settings = Sendora_Settings::get_settings();
        $iso = (string) ($settings['phone_country'] ?? 'BR');
        $country = Sendora_Phone::country($iso);
        $nonce = esc_attr(wp_create_nonce(self::NONCE_ACTION));

        return '<form class="sendora-form" method="post" action="' . esc_url(admin_url('admin-ajax.php')) . '"'
            . ' data-sendora-phone-country="' . esc_attr($iso) . '">'
            . '<input type="hidden" name="action" value="sendora_form_submit">'
            . '<input type="hidden" name="sendora_nonce" value="' . $nonce . '">'
            . '<div class="sendora-form__field"><label>'
            . esc_html__('Nome', 'sendora')
            . '<input type="text" name="name" autocomplete="name"></label></div>'
            . '<div class="sendora-form__field sendora-form__field--phone"><label>'
            . esc_html__('Telefone', 'sendora')
            . '<span class="sendora-form__phone">'
            . '<span class="sendora-form__ddi">+' . esc_html($country['cc']) . '</span>'
            . '<input type="tel" name="phone" autocomplete="tel" required'
            . ' inputmode="tel"'
            . ' placeholder="' . esc_attr($country['placeholder']) . '"'
            . ' data-sendora-phone-mask="' . esc_attr($country['mask']) . '"'
            . ' data-sendora-phone-cc="' . esc_attr($country['cc']) . '">'
            . '</span></label></div>'
            . '<div class="sendora-form__field"><label>'
            . esc_html__('E-mail', 'sendora')
            . '<input type="email" name="email" autocomplete="email"></label></div>'
            . '<div class="sendora-form__field"><label>'
            . esc_html__('Mensagem', 'sendora')
            . '<textarea name="message" rows="4"></textarea></label></div>'
            . '<div class="sendora-form__honeypot" aria-hidden="true">'
            . '<label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>'
            . '</div>'
            . '<button type="submit">' . esc_html__('Enviar', 'sendora') . '</button>'
            . '<div class="sendora-form__status" role="status" aria-live="polite"></div>'
            . '</form>';
    }

    public static function ajax_submit(): void
    {
        if (check_ajax_referer(self::NONCE_ACTION, 'sendora_nonce', false) === false) {
            wp_send_json([
                'ok' => false,
                'error' => __('Token do formulário inválido.', 'sendora'),
            ], 403);
        }

        // Nonce verified above via check_ajax_referer.
        $input = isset($_POST) && is_array($_POST) ? wp_unslash($_POST) : [];
        $ip = isset($_SERVER['REMOTE_ADDR'])
            ? sanitize_text_field(wp_unslash((string) $_SERVER['REMOTE_ADDR']))
            : '';
        $result = self::handle_submission($input, $ip);

        wp_send_json($result, !empty($result['ok']) ? 200 : 400);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, message?: string, error?: string}
     */
    public static function handle_submission(array $input, string $ip): array
    {
        if (trim((string) ($input['website'] ?? '')) !== '') {
            return [
                'ok' => true,
                'message' => __('Obrigado. Sua mensagem foi enviada.', 'sendora'),
            ];
        }

        if (!self::consume_rate_limit($ip)) {
            self::log_failure('Submission rate limit exceeded.');

            return [
                'ok' => false,
                'error' => __('Muitos envios. Tente novamente em alguns minutos.', 'sendora'),
            ];
        }

        $mapped = self::map_submission($input);
        if (!$mapped['ok']) {
            self::log_failure((string) $mapped['error']);

            return ['ok' => false, 'error' => (string) $mapped['error']];
        }

        $client = Sendora_Api_Client::from_options();
        $contact_result = $client->upsert_contact($mapped['fields']);
        if (empty($contact_result['ok'])) {
            self::log_failure('Contact upsert failed.', (int) ($contact_result['status'] ?? 0));

            return [
                'ok' => false,
                'error' => __('Não foi possível enviar o formulário. Tente novamente.', 'sendora'),
            ];
        }

        if (class_exists('Sendora_Automations')) {
            Sendora_Automations::dispatch('form.native', [
                'contact' => $mapped['fields'],
                'phone' => (string) ($mapped['fields']['phone'] ?? ''),
                'name' => (string) ($mapped['fields']['name'] ?? ''),
                'vars' => [
                    'name' => (string) ($mapped['fields']['name'] ?? ''),
                    'phone' => (string) ($mapped['fields']['phone'] ?? ''),
                ],
            ]);
        }

        $settings = Sendora_Settings::get_settings();
        $native = is_array($settings['native_form'] ?? null) ? $settings['native_form'] : [];
        $template_id = trim((string) ($native['template_id'] ?? ''));
        $message_enabled = !empty($native['enabled']) && $template_id !== '';

        if ($message_enabled) {
            $send = Sendora_Outbound::upsert_and_send_template(
                $mapped['fields'],
                $template_id,
                [
                    'name' => (string) ($mapped['fields']['name'] ?? ''),
                    'phone' => $mapped['fields']['phone'],
                ],
                [
                    'provider' => (string) ($native['channel'] ?? Sendora_Outbound::PROVIDER_EVOLUTION),
                    'instance_id' => (string) ($native['instance_id'] ?? ''),
                    'template_language' => (string) ($native['template_language'] ?? 'pt_BR'),
                    'body_vars' => $native['body_vars'] ?? [],
                    'skip_upsert' => true,
                ]
            );
            if (empty($send['ok'])) {
                self::log_failure((string) ($send['error'] ?? 'Template send failed.'), (int) ($send['status'] ?? 0));

                return [
                    'ok' => false,
                    'error' => __('Contato salvo, mas a mensagem não pôde ser enviada.', 'sendora'),
                ];
            }

            Sendora_Logger::log('form', 'info', sprintf(
                'Formulário → Mensagem: "%s" → Sendora: enviado',
                (string) ($send['template_name'] ?? '')
            ), ['template_id' => $template_id]);

            return [
                'ok' => true,
                'message' => __('Obrigado. Sua mensagem foi enviada.', 'sendora'),
            ];
        }

        $flow_id = trim((string) ($settings['default_flow_id'] ?? ''));
        if ($flow_id !== '') {
            $flow_result = $client->trigger_flow(
                $flow_id,
                $mapped['fields']['phone'],
                $mapped['message']
            );

            if (empty($flow_result['ok'])) {
                self::log_failure('Default flow trigger failed.', (int) ($flow_result['status'] ?? 0));

                return [
                    'ok' => false,
                    'error' => __('Contato salvo, mas a automação não pôde ser iniciada.', 'sendora'),
                ];
            }
        }

        Sendora_Logger::log('form', 'info', 'Form submission synced.', [
            'flow_triggered' => $flow_id !== '',
            'message_sent' => false,
        ]);

        return [
            'ok' => true,
            'message' => __('Obrigado. Sua mensagem foi enviada.', 'sendora'),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, fields?: array<string, string>, message?: string, error?: string}
     */
    public static function map_submission(array $input): array
    {
        $phone = trim((string) ($input['phone'] ?? ''));

        if ($phone === '') {
            return ['ok' => false, 'error' => __('O telefone é obrigatório.', 'sendora')];
        }

        $settings = Sendora_Settings::get_settings();
        $phone = Sendora_Phone::normalize(
            $phone,
            (string) ($settings['default_cc'] ?? '55')
        );
        $fields = [];
        $name = sanitize_text_field((string) ($input['name'] ?? ''));
        $email = sanitize_email((string) ($input['email'] ?? ''));

        if ($name !== '') {
            $fields['name'] = $name;
        }
        $fields['phone'] = $phone;
        if ($email !== '') {
            $fields['email'] = $email;
        }

        return [
            'ok' => true,
            'fields' => $fields,
            'message' => sanitize_textarea_field((string) ($input['message'] ?? '')),
        ];
    }

    private static function enqueue_assets(): void
    {
        wp_enqueue_style(
            'sendora-form',
            plugins_url('public/css/form.css', SENDORA_PLUGIN_FILE),
            [],
            SENDORA_VERSION
        );
        wp_enqueue_script(
            'sendora-form',
            plugins_url('public/js/form.js', SENDORA_PLUGIN_FILE),
            [],
            SENDORA_VERSION,
            true
        );
        wp_localize_script(
            'sendora-form',
            'SendoraForm',
            [
                'i18n' => [
                    'error' => __('Não foi possível enviar o formulário.', 'sendora'),
                    'success' => __('Obrigado. Sua mensagem foi enviada.', 'sendora'),
                ],
                'phone' => [
                    'country' => (string) (Sendora_Settings::get_settings()['phone_country'] ?? 'BR'),
                    'cc' => (string) (Sendora_Settings::get_settings()['default_cc'] ?? '55'),
                    'mask' => Sendora_Phone::country(
                        (string) (Sendora_Settings::get_settings()['phone_country'] ?? 'BR')
                    )['mask'],
                ],
            ]
        );
    }

    private static function consume_rate_limit(string $ip): bool
    {
        $key = 'sendora_form_' . hash('sha256', $ip !== '' ? $ip : 'unknown');
        $attempts = (int) get_transient($key);

        if ($attempts >= self::RATE_LIMIT) {
            return false;
        }

        set_transient($key, $attempts + 1, self::RATE_WINDOW);

        return true;
    }

    private static function log_failure(string $message, int $status = 0): void
    {
        $context = $status > 0 ? ['status' => $status] : [];
        Sendora_Logger::log('form', 'error', $message, $context);
    }
}
