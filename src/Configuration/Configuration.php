<?php

declare(strict_types=1);

namespace Restopulse\PhpSdk\Configuration;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class Configuration
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl = 'https://restopulse.ru',
        private readonly ?LoggerInterface $logger = null,
    ) {}

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getLogger(): LoggerInterface
    {
        return $this->logger ?? new NullLogger();
    }
}
