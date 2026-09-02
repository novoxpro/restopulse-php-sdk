<?php

declare(strict_types=1);

namespace Restopulse\PhpSdk\Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Restopulse\PhpSdk\Http\HttpClientLogger;
use Restopulse\PhpSdk\Tests\Support\TestLogger;

final class HttpClientLoggerTest extends TestCase
{
    /**
     * Проверяет маскирование чувствительных ключей в контексте лога.
     */
    public function test_sanitize_redacts_sensitive_keys(): void
    {
        $logger = new TestLogger();
        $httpLogger = new HttpClientLogger($logger);

        $httpLogger->logRequest('POST', 'https://restopulse.ru/api/public/v1/events', [
            'X-API-Key' => 'secret-api-key-value',
            'eventType' => 'order.created',
        ]);

        $this->assertCount(1, $logger->records);
        $context = $logger->records[0]['context'];

        $this->assertSame('[redacted]', $context['requestBody']['X-API-Key']);
        $this->assertSame('order.created', $context['requestBody']['eventType']);
        $this->assertStringNotContainsString('secret-api-key-value', json_encode($context, JSON_THROW_ON_ERROR));
    }
}
