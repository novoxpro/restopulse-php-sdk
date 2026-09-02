<?php

declare(strict_types=1);

namespace Restopulse\PhpSdk\Api;

use Restopulse\PhpSdk\DTO\Request\EventDto;
use Restopulse\PhpSdk\DTO\Response\EventResponseDto;
use Restopulse\PhpSdk\Http\HttpClient;

/**
 * API для отправки событий в RestoPulse.
 */
final class EventsApi
{
    public function __construct(
        private readonly HttpClient $httpClient,
    ) {}

    /** Отправляет событие в RestoPulse. */
    public function send(EventDto $event): EventResponseDto
    {
        return EventResponseDto::fromResponseData(
            $this->httpClient->post('events', $event->toArray()),
        );
    }
}
