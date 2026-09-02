<?php

declare(strict_types=1);

namespace Restopulse\PhpSdk\Api;

use Restopulse\PhpSdk\DTO\Response\BranchDto;
use Restopulse\PhpSdk\Http\HttpClient;

/**
 * API для работы с филиалами предприятия.
 */
final class BranchesApi
{
    public function __construct(
        private readonly HttpClient $httpClient,
    ) {}

    /**
     * Возвращает список филиалов, доступных API Key.
     *
     * @return list<BranchDto>
     */
    public function all(): array
    {
        return BranchDto::listFromResponseData($this->httpClient->get('branches'));
    }
}
