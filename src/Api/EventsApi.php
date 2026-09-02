<?php

declare(strict_types=1);

namespace Restopulse\PhpSdk\Api;

use Restopulse\PhpSdk\Http\HttpClient;

/**
 * API для отправки событий в RestoPulse.
 */
final class EventsApi
{
    public function __construct(
        private readonly HttpClient $httpClient,
    ) {}
}
