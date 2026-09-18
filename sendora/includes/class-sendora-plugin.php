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
        /**
         * Future plugin modules register their WordPress hooks here.
         */
    }

    private function __construct()
    {
    }
}
