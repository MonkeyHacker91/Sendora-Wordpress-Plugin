<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

abstract class SendoraPhoneTest extends TestCase
{
    public function test_normalize_strips_and_adds_cc(): void
    {
        $this->assertTrue(class_exists('Sendora_Phone'), 'Sendora_Phone must exist.');
        $this->assertSame('5511999999999', Sendora_Phone::normalize('(11) 99999-9999'));
    }

    public function test_normalize_keeps_existing_country_code(): void
    {
        $this->assertTrue(class_exists('Sendora_Phone'), 'Sendora_Phone must exist.');
        $this->assertSame('5511999999999', Sendora_Phone::normalize('+55 (11) 99999-9999'));
    }

    public function test_normalize_supports_a_custom_country_code(): void
    {
        $this->assertTrue(class_exists('Sendora_Phone'), 'Sendora_Phone must exist.');
        $this->assertSame('14155552671', Sendora_Phone::normalize('(415) 555-2671', '1'));
    }
}
