<?php

declare(strict_types=1);

abstract class SendoraApiClientTest extends SendoraPhoneTest
{
    protected function setUp(): void
    {
        $GLOBALS['sendora_test_options'] = [
            'sendora_settings' => [
                'api_base' => 'https://api.sendora.com.br',
                'api_key' => 'sk_test_key',
            ],
        ];
        $GLOBALS['sendora_test_http_handler'] = null;
    }

    public function test_upsert_posts_contacts_with_bearer(): void
    {
        $this->assertTrue(class_exists('Sendora_Api_Client'), 'Sendora_Api_Client must exist.');
        $GLOBALS['sendora_test_http_handler'] = function (string $url, array $arguments): array {
            $this->assertSame('https://api.sendora.com.br/api/contacts', $url);
            $this->assertSame('POST', $arguments['method']);
            $this->assertSame('Bearer sk_test_key', $arguments['headers']['Authorization']);
            $this->assertSame(
                ['name' => 'Ada', 'phone' => '5511999999999'],
                json_decode($arguments['body'], true)
            );

            return $this->response(201, ['id' => 'contact-1']);
        };

        $result = Sendora_Api_Client::from_options()->upsert_contact([
            'name' => 'Ada',
            'phone' => '5511999999999',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame(201, $result['status']);
        $this->assertSame(['id' => 'contact-1'], $result['data']);
        $this->assertNull($result['error']);
    }

    public function test_list_flows_uses_get(): void
    {
        $this->assertTrue(class_exists('Sendora_Api_Client'), 'Sendora_Api_Client must exist.');
        $GLOBALS['sendora_test_http_handler'] = function (string $url, array $arguments): array {
            $this->assertSame('https://api.sendora.com.br/api/flows', $url);
            $this->assertSame('GET', $arguments['method']);

            return $this->response(200, [['id' => 'flow-1']]);
        };

        $result = Sendora_Api_Client::from_options()->list_flows();

        $this->assertTrue($result['ok']);
        $this->assertSame([['id' => 'flow-1']], $result['data']);
    }

    public function test_trigger_flow_posts_phone_and_optional_message(): void
    {
        $this->assertTrue(class_exists('Sendora_Api_Client'), 'Sendora_Api_Client must exist.');
        $GLOBALS['sendora_test_http_handler'] = function (string $url, array $arguments): array {
            $this->assertSame('https://api.sendora.com.br/api/flows/flow-1/trigger', $url);
            $this->assertSame(
                ['phone' => '5511999999999', 'message' => 'Hello'],
                json_decode($arguments['body'], true)
            );

            return $this->response(202, ['queued' => true]);
        };

        $result = Sendora_Api_Client::from_options()
            ->trigger_flow('flow-1', '5511999999999', 'Hello');

        $this->assertTrue($result['ok']);
    }

    public function test_send_message_posts_payload(): void
    {
        $this->assertTrue(class_exists('Sendora_Api_Client'), 'Sendora_Api_Client must exist.');
        $payload = ['phone' => '5511999999999', 'message' => 'Hello'];
        $GLOBALS['sendora_test_http_handler'] = function (string $url, array $arguments) use ($payload): array {
            $this->assertSame('https://api.sendora.com.br/api/messages/send', $url);
            $this->assertSame($payload, json_decode($arguments['body'], true));

            return $this->response(200, ['sent' => true]);
        };

        $result = Sendora_Api_Client::from_options()->send_message($payload);

        $this->assertTrue($result['ok']);
    }

    public function test_request_maps_non_2xx_response_to_error(): void
    {
        $this->assertTrue(class_exists('Sendora_Api_Client'), 'Sendora_Api_Client must exist.');
        $GLOBALS['sendora_test_http_handler'] = fn (): array => $this->response(
            403,
            ['message' => 'Invalid API key']
        );

        $result = Sendora_Api_Client::from_options()->request('GET', '/api/flows');

        $this->assertFalse($result['ok']);
        $this->assertSame(403, $result['status']);
        $this->assertSame('Invalid API key', $result['error']);

        $logs = Sendora_Logger::list(1);
        $this->assertNotEmpty($logs);
        $this->assertSame('api', $logs[0]['source']);
        $this->assertSame('Invalid API key', $logs[0]['message']);
    }

    public function test_connection_reports_invalid_key(): void
    {
        $this->assertTrue(class_exists('Sendora_Api_Client'), 'Sendora_Api_Client must exist.');
        $GLOBALS['sendora_test_http_handler'] = fn (): array => $this->response(
            401,
            ['message' => 'Unauthorized']
        );

        $result = Sendora_Api_Client::from_options()->test_connection();

        $this->assertSame(['ok' => false, 'message' => 'Chave de API inválida.'], $result);
    }

    public function test_request_rejects_non_https_base_url(): void
    {
        $client = new Sendora_Api_Client('http://api.sendora.com.br', 'sk_test_key');
        $result = $client->request('GET', '/api/flows');

        $this->assertFalse($result['ok']);
        $this->assertSame('A URL da API da Sendora deve usar HTTPS.', $result['error']);
    }

    public function test_trigger_flow_rejects_unsafe_flow_id(): void
    {
        $result = Sendora_Api_Client::from_options()
            ->trigger_flow('../etc/passwd', '5511999999999');

        $this->assertFalse($result['ok']);
        $this->assertSame('ID de fluxo inválido.', $result['error']);
    }

    private function response(int $status, mixed $body): array
    {
        return [
            'response' => ['code' => $status],
            'body' => json_encode($body, JSON_THROW_ON_ERROR),
        ];
    }
}
