<?php

declare(strict_types=1);

namespace Restopulse\PhpSdk\Tests\Unit\Http;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Restopulse\PhpSdk\Configuration\Configuration;
use Restopulse\PhpSdk\Exceptions\ValidationException;
use Restopulse\PhpSdk\Http\HttpClient;
use Restopulse\PhpSdk\Tests\Support\TestLogger;

final class HttpClientLoggingTest extends TestCase
{
    private const API_KEY = 'super-secret-api-key-12345';

    /**
     * Проверяет debug-лог исходящего запроса.
     */
    public function test_logs_request_at_debug_level(): void
    {
        [$httpClient, $logger] = $this->createHttpClient([
            new Response(200, [], json_encode([
                'success' => true,
                'data' => [],
                'error' => null,
            ], JSON_THROW_ON_ERROR)),
        ]);

        $httpClient->post('events', [
            'eventType' => 'order.created',
        ]);

        $debugRecords = $logger->recordsByLevel('debug');
        $this->assertCount(1, $debugRecords);
        $this->assertSame('RestoPulse API request.', $debugRecords[0]['message']);
        $this->assertSame('POST', $debugRecords[0]['context']['method']);
        $this->assertSame('https://restopulse.ru/api/public/v1/events', $debugRecords[0]['context']['url']);
        $this->assertSame(['eventType' => 'order.created'], $debugRecords[0]['context']['requestBody']);
    }

    /**
     * Проверяет info-лог успешного ответа.
     */
    public function test_logs_successful_response_at_info_level(): void
    {
        $responseBody = json_encode([
            'success' => true,
            'data' => ['id' => 42],
            'error' => null,
        ], JSON_THROW_ON_ERROR);

        [$httpClient, $logger] = $this->createHttpClient([
            new Response(201, [], $responseBody),
        ]);

        $httpClient->post('events', ['eventType' => 'order.created']);

        $infoRecords = $logger->recordsByLevel('info');
        $this->assertCount(1, $infoRecords);
        $this->assertSame('RestoPulse API request completed.', $infoRecords[0]['message']);
        $this->assertSame('POST', $infoRecords[0]['context']['method']);
        $this->assertSame('https://restopulse.ru/api/public/v1/events', $infoRecords[0]['context']['url']);
        $this->assertSame(201, $infoRecords[0]['context']['responseStatus']);
        $this->assertSame($responseBody, $infoRecords[0]['context']['responseBody']);
        $this->assertArrayHasKey('executionTime', $infoRecords[0]['context']);
    }

    /**
     * Проверяет error-лог при HTTP-ошибке API.
     */
    public function test_logs_http_error_at_error_level(): void
    {
        $responseBody = json_encode([
            'success' => false,
            'data' => null,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'Validation failed.',
            ],
        ], JSON_THROW_ON_ERROR);

        [$httpClient, $logger] = $this->createHttpClient([
            new Response(422, [], $responseBody),
        ]);

        try {
            $httpClient->post('events', ['eventType' => 'order.created']);
        } catch (ValidationException) {
        }

        $errorRecords = $logger->recordsByLevel('error');
        $this->assertCount(1, $errorRecords);
        $this->assertSame('RestoPulse API request failed.', $errorRecords[0]['message']);
        $this->assertSame('POST', $errorRecords[0]['context']['method']);
        $this->assertSame(422, $errorRecords[0]['context']['responseStatus']);
        $this->assertSame($responseBody, $errorRecords[0]['context']['responseBody']);
        $this->assertSame('Validation failed.', $errorRecords[0]['context']['error']);
        $this->assertArrayHasKey('executionTime', $errorRecords[0]['context']);
    }

    /**
     * Проверяет error-лог при сетевой ошибке.
     */
    public function test_logs_network_error_at_error_level(): void
    {
        [$httpClient, $logger] = $this->createHttpClient([
            new ConnectException(
                'Connection timed out',
                new Request('GET', 'https://restopulse.ru/api/public/v1/branches'),
            ),
        ]);

        try {
            $httpClient->get('branches');
        } catch (\Restopulse\PhpSdk\Exceptions\NetworkException) {
        }

        $errorRecords = $logger->recordsByLevel('error');
        $this->assertCount(1, $errorRecords);
        $this->assertSame('Connection timed out', $errorRecords[0]['context']['error']);
        $this->assertArrayNotHasKey('responseStatus', $errorRecords[0]['context']);
    }

    /**
     * Проверяет warning-лог при повторной попытке.
     */
    public function test_logs_retry_at_warning_level(): void
    {
        [$httpClient, $logger] = $this->createHttpClient(
            responses: [
                new Response(500, [], json_encode([
                    'success' => false,
                    'data' => null,
                    'error' => ['code' => 'INTERNAL_ERROR', 'message' => 'Server error.'],
                ], JSON_THROW_ON_ERROR)),
                new Response(200, [], json_encode([
                    'success' => true,
                    'data' => [],
                    'error' => null,
                ], JSON_THROW_ON_ERROR)),
            ],
            maxRetries: 1,
            sleeper: static function (int $milliseconds): void {},
        );

        $httpClient->get('branches');

        $warningRecords = $logger->recordsByLevel('warning');
        $this->assertCount(1, $warningRecords);
        $this->assertSame('RestoPulse API request retry.', $warningRecords[0]['message']);
        $this->assertSame('GET', $warningRecords[0]['context']['method']);
        $this->assertSame(1, $warningRecords[0]['context']['attempt']);
        $this->assertSame('Server error.', $warningRecords[0]['context']['error']);
    }

    /**
     * Проверяет, что API Key не попадает в логи открытым текстом.
     */
    public function test_does_not_log_api_key_in_plain_text(): void
    {
        [$httpClient, $logger] = $this->createHttpClient(
            responses: [
                new Response(500, [], json_encode([
                    'success' => false,
                    'data' => null,
                    'error' => ['code' => 'INTERNAL_ERROR', 'message' => 'Server error.'],
                ], JSON_THROW_ON_ERROR)),
                new Response(200, [], json_encode([
                    'success' => true,
                    'data' => [],
                    'error' => null,
                ], JSON_THROW_ON_ERROR)),
            ],
            maxRetries: 1,
            sleeper: static function (int $milliseconds): void {},
        );

        $httpClient->post('events', [
            'X-API-Key' => self::API_KEY,
            'eventType' => 'order.created',
        ]);

        $serializedLogs = json_encode($logger->records, JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString(self::API_KEY, $serializedLogs);
    }

    /**
     * @param list<Response|ConnectException> $responses
     *
     * @return array{0: HttpClient, 1: TestLogger}
     */
    private function createHttpClient(
        array $responses,
        int $maxRetries = 0,
        ?callable $sleeper = null,
    ): array {
        $logger = new TestLogger();
        $configuration = new Configuration(
            apiKey: self::API_KEY,
            baseUrl: 'https://restopulse.ru',
            logger: $logger,
            maxRetries: $maxRetries,
            retryDelayMs: 100,
        );

        $mockHandler = new MockHandler($responses);

        $guzzleClient = new GuzzleClient([
            'handler' => HandlerStack::create($mockHandler),
            'base_uri' => $configuration->getBaseUrl() . '/',
            'http_errors' => false,
        ]);

        return [new HttpClient($configuration, $guzzleClient, $sleeper), $logger];
    }
}
