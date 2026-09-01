<?php

declare(strict_types=1);

namespace Restopulse\PhpSdk\Api;

use Restopulse\PhpSdk\Http\HttpClient;

final class EventsApi
{
    public function __construct(
        private readonly HttpClient $httpClient,
    ) {}
}
