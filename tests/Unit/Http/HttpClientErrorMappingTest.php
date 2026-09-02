<?php

declare(strict_types=1);

namespace Restopulse\PhpSdk\Tests\Unit\Http;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Restopulse\PhpSdk\Configuration\Configuration;
use Restopulse\PhpSdk\Exceptions\ApiException;
use Restopulse\PhpSdk\Exceptions\NetworkException;
use Restopulse\PhpSdk\Exceptions\NotFoundException;
use Restopulse\PhpSdk\Exceptions\RestopulseException;
use Restopulse\PhpSdk\Exceptions\SerializationException;
use Restopulse\PhpSdk\Exceptions\UnauthorizedException;
use Restopulse\PhpSdk\Exceptions\ValidationException;
use Restopulse\PhpSdk\Http\HttpClient;

final class HttpClientErrorMappingTest extends TestCase
{
    /**
     * Проверяет преобразование HTTP-ошибок в исключения SDK.
     *
     * @param class-string<RestopulseException> $expectedException
     */
    #[DataProvider('httpErrorMappingProvider')]
    public function test_maps_http_errors_to_sdk_exceptions(
        int $statusCode,
        string $expectedException,
    ): void {
        $httpClient = $this->createHttpClient([
            new Response($statusCode, [], json_encode([
                'success' => false,
                'data' => null,
                'error' => [
                    'code' => 'TEST_ERROR',
                    'message' => 'Test error message.',
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $this->expectException($expectedException);

        $httpClient->get('branches');
    }

    /**
     * @return iterable<string, array{int, class-string<RestopulseException>}>
     */
    public static function httpErrorMappingProvider(): iterable
    {
        yield '401 unauthorized' => [401, UnauthorizedException::class];
        yield '404 not found' => [404, NotFoundException::class];
        yield '422 validation' => [422, ValidationException::class];
        yield '500 internal error' => [500, ApiException::class];
        yield '503 service unavailable' => [503, ApiException::class];
    }

    /**
     * Проверяет преобразование сетевых ошибок в NetworkException.
     */
    public function test_maps_network_errors_to_network_exception(): void
    {
        $configuration = new Configuration(
            apiKey: 'test-api-key',
            baseUrl: 'https://restopulse.ru',
            maxRetries: 0,
        );

        $mockHandler = new MockHandler([
            new ConnectException(
                'Connection timed out',
                new Request('GET', 'https://restopulse.ru/api/public/v1/branches'),
            ),
        ]);

        $guzzleClient = new GuzzleClient([
            'handler' => HandlerStack::create($mockHandler),
            'base_uri' => $configuration->getBaseUrl() . '/',
            'http_errors' => false,
        ]);

        $httpClient = new HttpClient($configuration, $guzzleClient);

        $this->expectException(NetworkException::class);

        $httpClient->get('branches');
    }

    /**
     * Проверяет преобразование некорректного JSON в SerializationException.
     */
    public function test_maps_invalid_json_to_serialization_exception(): void
    {
        $httpClient = $this->createHttpClient([
            new Response(200, [], 'not-json'),
        ]);

        $this->expectException(SerializationException::class);

        $httpClient->get('branches');
    }

    /**
     * @param list<Response|ConnectException> $responses
     */
    private function createHttpClient(array $responses): HttpClient
    {
        $configuration = new Configuration(
            apiKey: 'test-api-key',
            baseUrl: 'https://restopulse.ru',
            maxRetries: 0,
        );

        $mockHandler = new MockHandler($responses);

        $guzzleClient = new GuzzleClient([
            'handler' => HandlerStack::create($mockHandler),
            'base_uri' => $configuration->getBaseUrl() . '/',
            'http_errors' => false,
        ]);

        return new HttpClient($configuration, $guzzleClient);
    }
}
