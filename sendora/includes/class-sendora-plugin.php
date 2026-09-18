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
    }

    private function __construct()
    {
    }
}
