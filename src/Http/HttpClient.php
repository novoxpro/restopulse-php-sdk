<?php

declare(strict_types=1);

namespace Restopulse\PhpSdk\Http;

use GuzzleHttp\Client as GuzzleClient;
use Restopulse\PhpSdk\Configuration\Configuration;

/**
 * Внутренний HTTP-клиент SDK.
 *
 * Отвечает за выполнение HTTP-запросов к REST API RestoPulse.
 */
final class HttpClient
{
    private readonly GuzzleClient $client;

    public function __construct(Configuration $configuration)
    {
        $this->client = new GuzzleClient([
            'base_uri' => rtrim($configuration->getBaseUrl(), '/') . '/',
        ]);
    }
}
