<?php

declare(strict_types=1);

namespace Restopulse\PhpSdk\Tests\Integration;

use DateTimeImmutable;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Restopulse\PhpSdk\Api\EventsApi;
use Restopulse\PhpSdk\Client\Client;
use Restopulse\PhpSdk\Configuration\Configuration;
use Restopulse\PhpSdk\DTO\Request\EventDto;
use Restopulse\PhpSdk\DTO\Response\EventResponseDto;
use Restopulse\PhpSdk\Http\HttpClient;

final class EventsApiTest extends TestCase
{
    /**
     * Проверяет отправку события через EventsApi.
     */
    public function test_send_returns_event_response_dto(): void
    {
        [$httpClient, $requestHolder] = $this->createHttpClient([
            new Response(201, [], json_encode([
                'success' => true,
                'data' => ['id' => 12345],
                'error' => null,
            ], JSON_THROW_ON_ERROR)),
        ]);

        $event = $this->createEventDto();
        $response = (new EventsApi($httpClient))->send($event);

        $this->assertInstanceOf(EventResponseDto::class, $response);
        $this->assertSame(12345, $response->getId());

        $this->assertNotNull($requestHolder->request);
        $request = $requestHolder->request;
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://restopulse.ru/api/public/v1/events', (string) $request->getUri());
        $this->assertJsonStringEqualsJsonString(
            json_encode($event->toArray(), JSON_THROW_ON_ERROR),
            (string) $request->getBody(),
        );
    }

    /**
     * Проверяет отправку события через Client.
     */
    public function test_client_events_send_returns_event_response_dto(): void
    {
        $configuration = new Configuration(
            apiKey: 'test-api-key',
            baseUrl: 'https://restopulse.ru',
        );

        [$httpClient] = $this->createHttpClient([
            new Response(201, [], json_encode([
                'success' => true,
                'data' => ['id' => 99],
                'error' => null,
            ], JSON_THROW_ON_ERROR)),
        ]);

        $client = new Client($configuration, $httpClient);
        $response = $client->events()->send($this->createEventDto());

        $this->assertSame(99, $response->getId());
    }

    private function createEventDto(): EventDto
    {
        return new EventDto(
            branchIds: [47],
            eventType: 'order.created',
            externalId: 'ORDER-123456',
            eventDate: new DateTimeImmutable('2026-07-03T14:30:00Z'),
            title: 'Новый заказ',
            preview: 'Заказ создан.',
            message: 'Создан заказ №123456.',
            idempotencyKey: '550e8400-e29b-41d4-a716-446655440000',
        );
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

        $configuration = new Configuration(
            apiKey: 'test-api-key',
            baseUrl: 'https://restopulse.ru',
        );

        $mockHandler = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mockHandler);
        $handlerStack->push(Middleware::mapRequest(
            static function (RequestInterface $capturedRequest) use ($requestHolder): RequestInterface {
                $requestHolder->request = $capturedRequest;

                return $capturedRequest;
            },
        ));

        $guzzleClient = new GuzzleClient([
            'handler' => $handlerStack,
            'base_uri' => $configuration->getBaseUrl() . '/',
            'http_errors' => false,
        ]);

        return [new HttpClient($configuration, $guzzleClient), $requestHolder];
    }
}
