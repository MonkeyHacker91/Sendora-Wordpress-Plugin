<?php

declare(strict_types=1);

abstract class SendoraLoggerTest extends SendoraSettingsTest
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['sendora_test_logs'] = [];
        $GLOBALS['sendora_test_log_id'] = 0;
    }

    public function test_log_and_list_persist_entries(): void
    {
        $this->assertTrue(class_exists('Sendora_Logger'), 'Sendora_Logger must exist.');

        Sendora_Logger::log('api', 'error', 'Request failed', ['status' => 403]);
        Sendora_Logger::log('woo', 'info', 'Synced order', ['order_id' => 42]);

        $logs = Sendora_Logger::list(10);

        $this->assertCount(2, $logs);
        $this->assertSame('woo', $logs[0]['source']);
        $this->assertSame('Synced order', $logs[0]['message']);
        $this->assertSame(['order_id' => 42], $logs[0]['context']);
        $this->assertSame('api', $logs[1]['source']);
    }

    public function test_clear_removes_all_entries(): void
    {
        $this->assertTrue(class_exists('Sendora_Logger'), 'Sendora_Logger must exist.');

        Sendora_Logger::log('api', 'error', 'One', []);
        Sendora_Logger::clear();

        $this->assertSame([], Sendora_Logger::list());
    }
}
