<?php

declare(strict_types=1);

namespace Restopulse\PhpSdk\Tests\Unit\Http;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Restopulse\PhpSdk\Configuration\Configuration;
use Restopulse\PhpSdk\Http\HttpClient;
use Restopulse\PhpSdk\Version;

final class HttpClientTest extends TestCase
{
    /**
     * Проверяет GET-запрос к Public API.
     */
    public function test_get_sends_request_with_expected_options(): void
    {
        [$httpClient, $requestHolder] = $this->createHttpClient([
            new Response(200, [], json_encode([
                'success' => true,
                'data' => [],
                'error' => null,
            ], JSON_THROW_ON_ERROR)),
        ]);

        $response = $httpClient->get('branches');

        $this->assertSame([], $response);

        $this->assertNotNull($requestHolder->request);
        $request = $requestHolder->request;
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('https://restopulse.ru/api/public/v1/branches', (string) $request->getUri());
        $this->assertSame('test-api-key', $request->getHeaderLine('X-API-Key'));
        $this->assertSame(
            sprintf('restopulse-php-sdk/%s', Version::SDK_VERSION),
            $request->getHeaderLine('User-Agent'),
        );
        $this->assertSame('application/json', $request->getHeaderLine('Accept'));
    }

    /**
     * Проверяет POST-запрос к Public API с JSON-телом.
     */
    public function test_post_sends_request_with_json_body(): void
    {
        [$httpClient, $requestHolder] = $this->createHttpClient([
            new Response(201, [], json_encode([
                'success' => true,
                'data' => ['id' => 12345],
                'error' => null,
            ], JSON_THROW_ON_ERROR)),
        ]);

        $payload = [
            'branchIds' => [47],
            'eventType' => 'order.created',
        ];

        $response = $httpClient->post('events', $payload);

        $this->assertSame(12345, $response['id']);

        $this->assertNotNull($requestHolder->request);
        $request = $requestHolder->request;
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://restopulse.ru/api/public/v1/events', (string) $request->getUri());
        $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));
        $this->assertJsonStringEqualsJsonString(
            json_encode($payload, JSON_THROW_ON_ERROR),
            (string) $request->getBody(),
        );
    }

    /**
     * Проверяет применение таймаутов из конфигурации.
     */
    public function test_uses_timeouts_from_configuration(): void
    {
        $configuration = new Configuration(
            apiKey: 'test-api-key',
            baseUrl: 'https://restopulse.ru',
            requestTimeout: 45.0,
            connectTimeout: 12.5,
            maxExecutionTime: 45.0,
        );

        $history = [];
        $mockHandler = new MockHandler([
            new Response(200, [], '{"success":true,"data":[],"error":null}'),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $handlerStack->push(Middleware::history($history));

        $guzzleClient = new GuzzleClient([
            'handler' => $handlerStack,
            'base_uri' => $configuration->getBaseUrl() . '/',
            'timeout' => $configuration->getRequestTimeout(),
            'connect_timeout' => $configuration->getConnectTimeout(),
            'http_errors' => false,
        ]);

        $httpClient = new HttpClient($configuration, $guzzleClient);
        $httpClient->get('branches');

        $this->assertSame(45.0, $history[0]['options']['timeout']);
        $this->assertSame(12.5, $history[0]['options']['connect_timeout']);
    }

    /**
     * @param list<Response> $responses
     *
     * @return array{0: HttpClient, 1: object{request: RequestInterface|null}}
     */
    private function createHttpClient(array $responses): array
    {
        $requestHolder = new class {
            public ?RequestInterface $request = null;
        };

        $mockHandler = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mockHandler);
        $handlerStack->push(Middleware::mapRequest(
            static function (RequestInterface $capturedRequest) use ($requestHolder): RequestInterface {
                $requestHolder->request = $capturedRequest;

                return $capturedRequest;
            },
        ));

        $configuration = new Configuration(
            apiKey: 'test-api-key',
            baseUrl: 'https://restopulse.ru',
        );

        $guzzleClient = new GuzzleClient([
            'handler' => $handlerStack,
            'base_uri' => $configuration->getBaseUrl() . '/',
            'timeout' => $configuration->getRequestTimeout(),
            'connect_timeout' => $configuration->getConnectTimeout(),
            'http_errors' => false,
        ]);

        return [new HttpClient($configuration, $guzzleClient), $requestHolder];
    }
}
