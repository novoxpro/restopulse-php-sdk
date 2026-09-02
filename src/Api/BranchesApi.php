<?php

declare(strict_types=1);

namespace Restopulse\PhpSdk\Api;

use Restopulse\PhpSdk\Http\HttpClient;

/**
 * API для работы с филиалами предприятия.
 */
final class BranchesApi
{
    public function __construct(
        private readonly HttpClient $httpClient,
    ) {}
}
