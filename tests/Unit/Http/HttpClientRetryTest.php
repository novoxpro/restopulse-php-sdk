<?php

declare(strict_types=1);

namespace Restopulse\PhpSdk\Tests\Unit\Http;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Restopulse\PhpSdk\Configuration\Configuration;
use Restopulse\PhpSdk\Exceptions\ApiException;
use Restopulse\PhpSdk\Exceptions\NetworkException;
use Restopulse\PhpSdk\Exceptions\UnauthorizedException;
use Restopulse\PhpSdk\Exceptions\ValidationException;
use Restopulse\PhpSdk\Http\HttpClient;

final class HttpClientRetryTest extends TestCase
{
    /**
     * Проверяет, что при maxRetries = 0 повторные попытки не выполняются.
     */
    public function test_does_not_retry_when_max_retries_is_zero(): void
    {
        $history = [];
        $httpClient = $this->createHttpClient(
            configuration: new Configuration(
                apiKey: 'test-api-key',
                baseUrl: 'https://restopulse.ru',
                maxRetries: 0,
                retryDelayMs: 100,
            ),
            responses: [
                $this->createErrorResponse(500),
            ],
            history: $history,
        );

        $this->expectException(ApiException::class);

        $httpClient->get('branches');

        $this->assertCount(1, $history);
    }

    /**
     * Проверяет одну повторную попытку после ошибки 5xx.
     */
    public function test_retries_once_after_server_error(): void
    {
        $history = [];
        $httpClient = $this->createHttpClient(
            configuration: new Configuration(
                apiKey: 'test-api-key',
                baseUrl: 'https://restopulse.ru',
                maxRetries: 1,
                retryDelayMs: 100,
            ),
            responses: [
                $this->createErrorResponse(500),
                $this->createSuccessResponse([]),
            ],
            history: $history,
        );

        $response = $httpClient->get('branches');

        $this->assertSame([], $response);
        $this->assertCount(2, $history);
    }

    /**
     * Проверяет несколько повторных попыток после серии ошибок 5xx.
     */
    public function test_retries_multiple_times_after_server_errors(): void
    {
        $history = [];
        $httpClient = $this->createHttpClient(
            configuration: new Configuration(
                apiKey: 'test-api-key',
                baseUrl: 'https://restopulse.ru',
                maxRetries: 2,
                retryDelayMs: 100,
            ),
            responses: [
                $this->createErrorResponse(503),
                $this->createErrorResponse(502),
                $this->createSuccessResponse(['id' => 1]),
            ],
            history: $history,
        );

        $response = $httpClient->get('branches');

        $this->assertSame(['id' => 1], $response);
        $this->assertCount(3, $history);
    }

    /**
     * Проверяет экспоненциальный backoff между повторными попытками.
     */
    public function test_uses_exponential_backoff_between_retries(): void
    {
        $sleptMs = [];
        $httpClient = $this->createHttpClient(
            configuration: new Configuration(
                apiKey: 'test-api-key',
                baseUrl: 'https://restopulse.ru',
                maxRetries: 3,
                retryDelayMs: 100,
            ),
            responses: [
                $this->createErrorResponse(500),
                $this->createErrorResponse(500),
                $this->createErrorResponse(500),
                $this->createSuccessResponse([]),
            ],
            sleeper: static function (int $milliseconds) use (&$sleptMs): void {
                $sleptMs[] = $milliseconds;
            },
        );

        $httpClient->get('branches');

        $this->assertSame([100, 200, 400], $sleptMs);
    }

    /**
     * Проверяет повторную попытку после таймаута соединения.
     */
    public function test_retries_after_timeout(): void
    {
        $history = [];
        $httpClient = $this->createHttpClient(
            configuration: new Configuration(
                apiKey: 'test-api-key',
                baseUrl: 'https://restopulse.ru',
                maxRetries: 1,
                retryDelayMs: 100,
            ),
            responses: [
                new ConnectException(
                    'Connection timed out',
                    new Request('GET', 'https://restopulse.ru/api/public/v1/branches'),
                ),
                $this->createSuccessResponse([]),
            ],
            history: $history,
        );

        $response = $httpClient->get('branches');

        $this->assertSame([], $response);
        $this->assertCount(2, $history);
    }

    /**
     * Проверяет, что повтор не выполняется при превышении MaxExecutionTime.
     */
    public function test_does_not_retry_when_max_execution_time_is_exceeded(): void
    {
        $history = [];
        $timeCall = 0;
        $httpClient = $this->createHttpClient(
            configuration: new Configuration(
                apiKey: 'test-api-key',
                baseUrl: 'https://restopulse.ru',
                requestTimeout: 5.0,
                connectTimeout: 5.0,
                maxRetries: 3,
                retryDelayMs: 100,
                maxExecutionTime: 10.0,
            ),
            responses: [
                $this->createErrorResponse(500),
                $this->createSuccessResponse([]),
            ],
            history: $history,
            timeProvider: static function () use (&$timeCall): float {
                ++$timeCall;

                return $timeCall === 1 ? 0.0 : 11.0;
            },
        );

        $this->expectException(ApiException::class);

        $httpClient->get('branches');

        $this->assertCount(1, $history);
    }

    /**
     * Проверяет, что ошибки 5xx повторяются до исчерпания лимита попыток.
     */
    public function test_throws_after_exhausting_retries_for_server_error(): void
    {
        $history = [];
        $httpClient = $this->createHttpClient(
            configuration: new Configuration(
                apiKey: 'test-api-key',
                baseUrl: 'https://restopulse.ru',
                maxRetries: 1,
                retryDelayMs: 100,
            ),
            responses: [
                $this->createErrorResponse(500),
                $this->createErrorResponse(500),
            ],
            history: $history,
        );

        $this->expectException(ApiException::class);

        $httpClient->get('branches');

        $this->assertCount(2, $history);
    }

    /**
     * Проверяет, что клиентские HTTP-ошибки не повторяются.
     *
     * @param class-string<\Throwable> $expectedException
     */
    #[DataProvider('nonRetriableHttpErrorProvider')]
    public function test_does_not_retry_client_errors(
        int $statusCode,
        string $expectedException,
    ): void {
        $history = [];
        $httpClient = $this->createHttpClient(
            configuration: new Configuration(
                apiKey: 'test-api-key',
                baseUrl: 'https://restopulse.ru',
                maxRetries: 3,
                retryDelayMs: 100,
            ),
            responses: [
                $this->createErrorResponse($statusCode),
            ],
            history: $history,
        );

        $this->expectException($expectedException);

        $httpClient->get('branches');

        $this->assertCount(1, $history);
    }

    /**
     * @return iterable<string, array{int, class-string<\Throwable>}>
     */
    public static function nonRetriableHttpErrorProvider(): iterable
    {
        yield '401 unauthorized' => [401, UnauthorizedException::class];
        yield '404 not found' => [404, \Restopulse\PhpSdk\Exceptions\NotFoundException::class];
        yield '422 validation' => [422, ValidationException::class];
        yield '400 bad request' => [400, ApiException::class];
    }

    /**
     * @param list<Response|ConnectException> $responses
     * @param list<array<string, mixed>>|null $history
     */
    private function createHttpClient(
        Configuration $configuration,
        array $responses,
        ?array &$history = null,
        ?callable $sleeper = null,
        ?callable $timeProvider = null,
    ): HttpClient {
        $mockHandler = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mockHandler);

        if ($history !== null) {
            $handlerStack->push(Middleware::history($history));
        }

        $guzzleClient = new GuzzleClient([
            'handler' => $handlerStack,
            'base_uri' => $configuration->getBaseUrl() . '/',
            'http_errors' => false,
        ]);

        return new HttpClient($configuration, $guzzleClient, $sleeper, $timeProvider);
    }

    /**
     * @param array<string, mixed>|list<mixed> $data
     */
    private function createSuccessResponse(array $data): Response
    {
        return new Response(200, [], json_encode([
            'success' => true,
            'data' => $data,
            'error' => null,
        ], JSON_THROW_ON_ERROR));
    }

    private function createErrorResponse(int $statusCode): Response
    {
        return new Response($statusCode, [], json_encode([
            'success' => false,
            'data' => null,
            'error' => [
                'code' => 'TEST_ERROR',
                'message' => 'Test error message.',
            ],
        ], JSON_THROW_ON_ERROR));
    }
}
