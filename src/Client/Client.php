<?php

declare(strict_types=1);

namespace Restopulse\PhpSdk\Client;

use Restopulse\PhpSdk\Api\BranchesApi;
use Restopulse\PhpSdk\Api\EventsApi;
use Restopulse\PhpSdk\Configuration\Configuration;
use Restopulse\PhpSdk\Http\HttpClient;

/**
 * Основная точка входа в SDK.
 *
 * Предоставляет доступ к разделам REST API и управляет взаимодействием
 * между компонентами SDK.
 */
final class Client
{
    private readonly HttpClient $httpClient;

    public function __construct(
        private readonly Configuration $configuration,
    ) {
        $this->httpClient = new HttpClient($configuration);
    }

    /** Возвращает API для работы с филиалами. */
    public function branches(): BranchesApi
    {
        return new BranchesApi($this->httpClient);
    }

    /** Возвращает API для работы с событиями. */
    public function events(): EventsApi
    {
        return new EventsApi($this->httpClient);
    }
}
