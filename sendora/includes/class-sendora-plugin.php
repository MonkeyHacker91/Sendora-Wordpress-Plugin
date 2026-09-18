<?php

declare(strict_types=1);

final class Sendora_Plugin
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function run(): void
    {
        (new Sendora_Settings())->run();
        (new Sendora_Forms())->run();
        (new Sendora_Widget())->run();
        if (defined('WPCF7_VERSION') && class_exists('Sendora_CF7')) {
            (new Sendora_CF7())->run();
        }
    }

    private function __construct()
    {
    }
}
