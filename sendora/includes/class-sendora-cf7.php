<?php
/**
 * Contact Form 7 bridge.
 *
 * @package Sendora
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Sendora_CF7
{
    public function run(): void
    {
        add_action('wpcf7_mail_sent', [self::class, 'mail_sent']);
    }

    /**
     * @return array<int, array{id: string, title: string, tags: array<int, string>}>
     */
    public static function list_forms(): array
    {
        if (!class_exists('WPCF7_ContactForm')) {
            return [];
        }

        $forms = [];

        foreach (WPCF7_ContactForm::find(['post_status' => 'publish']) as $form) {
            $tags = [];

            foreach ($form->scan_form_tags() as $tag) {
                $name = sanitize_key((string) ($tag->name ?? ''));
                if ($name !== '') {
                    $tags[] = $name;
                }
            }

            $forms[] = [
                'id' => (string) $form->id(),
                'title' => sanitize_text_field((string) $form->title()),
                'tags' => array_values(array_unique($tags)),
            ];
        }

        return $forms;
    }

    public static function mail_sent(WPCF7_ContactForm $form): void
    {
        $settings = Sendora_Settings::get_settings();
        $mappings = is_array($settings['cf7_mappings'] ?? null)
            ? $settings['cf7_mappings']
            : [];
        $mapping = $mappings[(string) $form->id()] ?? null;

        if (!is_array($mapping)) {
            return;
        }

        $submission = WPCF7_Submission::get_instance();
        if ($submission === null) {
            self::log_failure('CF7 submission data was unavailable.');

            return;
        }

        $posted_data = $submission->get_posted_data();
        $phone = self::posted_value($posted_data, (string) ($mapping['phone'] ?? ''));
        if ($phone === '') {
            self::log_failure('Mapped CF7 phone field was empty.');

            return;
        }

        $phone = Sendora_Phone::normalize(
            $phone,
            (string) ($settings['default_cc'] ?? '55')
        );
        $fields = [];
        $name = sanitize_text_field(
            self::posted_value($posted_data, (string) ($mapping['name'] ?? ''))
        );
        $email = sanitize_email(
            self::posted_value($posted_data, (string) ($mapping['email'] ?? ''))
        );

        if ($name !== '') {
            $fields['name'] = $name;
        }
        $fields['phone'] = $phone;
        if ($email !== '') {
            $fields['email'] = $email;
        }

        $client = Sendora_Api_Client::from_options();
        $contact_result = $client->upsert_contact($fields);
        if (empty($contact_result['ok'])) {
            self::log_failure('CF7 contact upsert failed.', (int) ($contact_result['status'] ?? 0));

            return;
        }

        $flow_id = trim((string) ($settings['default_flow_id'] ?? ''));
        if ($flow_id !== '') {
            $flow_result = $client->trigger_flow($flow_id, $phone);
            if (empty($flow_result['ok'])) {
                self::log_failure('CF7 default flow trigger failed.', (int) ($flow_result['status'] ?? 0));

                return;
            }
        }

        Sendora_Logger::log('cf7', 'info', 'Contact Form 7 submission synced.', [
            'form_id' => (string) $form->id(),
            'flow_triggered' => $flow_id !== '',
        ]);
    }

    /**
     * @param array<string, mixed> $posted_data
     */
    private static function posted_value(array $posted_data, string $tag): string
    {
        if ($tag === '' || !array_key_exists($tag, $posted_data)) {
            return '';
        }

        $value = $posted_data[$tag];
        if (is_array($value)) {
            $value = reset($value);
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }

    private static function log_failure(string $message, int $status = 0): void
    {
        $context = $status > 0 ? ['status' => $status] : [];
        Sendora_Logger::log('cf7', 'error', $message, $context);
    }
}
