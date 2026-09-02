<?php

declare(strict_types=1);

namespace Restopulse\PhpSdk\Tests\Unit\Configuration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Restopulse\PhpSdk\Configuration\Configuration;
use Restopulse\PhpSdk\Exceptions\ConfigurationException;

final class ConfigurationTest extends TestCase
{
    /**
     * Проверяет создание конфигурации со значениями по умолчанию.
     */
    public function test_creates_configuration_with_default_values(): void
    {
        $configuration = new Configuration('test-api-key');

        $this->assertSame('test-api-key', $configuration->getApiKey());
        $this->assertSame(Configuration::DEFAULT_BASE_URL, $configuration->getBaseUrl());
        $this->assertSame(Configuration::DEFAULT_REQUEST_TIMEOUT, $configuration->getRequestTimeout());
        $this->assertSame(Configuration::DEFAULT_CONNECT_TIMEOUT, $configuration->getConnectTimeout());
        $this->assertSame(Configuration::DEFAULT_MAX_RETRIES, $configuration->getMaxRetries());
        $this->assertSame(Configuration::DEFAULT_RETRY_DELAY_MS, $configuration->getRetryDelayMs());
        $this->assertSame(Configuration::DEFAULT_MAX_EXECUTION_TIME, $configuration->getMaxExecutionTime());
        $this->assertInstanceOf(NullLogger::class, $configuration->getLogger());
    }

    /**
     * Проверяет создание конфигурации с явно переданными значениями.
     */
    public function test_creates_configuration_with_custom_values(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $configuration = new Configuration(
            apiKey: ' custom-key ',
            baseUrl: 'https://api.example.com/',
            requestTimeout: 45.0,
            connectTimeout: 15.0,
            logger: $logger,
            maxRetries: 3,
            retryDelayMs: 500,
            maxExecutionTime: 120.0,
        );

        $this->assertSame('custom-key', $configuration->getApiKey());
        $this->assertSame('https://api.example.com', $configuration->getBaseUrl());
        $this->assertSame(45.0, $configuration->getRequestTimeout());
        $this->assertSame(15.0, $configuration->getConnectTimeout());
        $this->assertSame($logger, $configuration->getLogger());
        $this->assertSame(3, $configuration->getMaxRetries());
        $this->assertSame(500, $configuration->getRetryDelayMs());
        $this->assertSame(120.0, $configuration->getMaxExecutionTime());
    }

    /**
     * Проверяет, что пустой API Key вызывает исключение.
     */
    public function test_throws_when_api_key_is_empty(): void
    {
        $this->expectException(ConfigurationException::class);

        new Configuration('');
    }

    /**
     * Проверяет, что API Key из пробелов вызывает исключение.
     */
    public function test_throws_when_api_key_contains_only_whitespace(): void
    {
        $this->expectException(ConfigurationException::class);

        new Configuration('   ');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidBaseUrlProvider(): iterable
    {
        yield 'empty string' => [''];
        yield 'invalid url' => ['not-a-url'];
        yield 'ftp scheme' => ['ftp://restopulse.ru'];
    }

    /**
     * Проверяет, что некорректный Base URL вызывает исключение.
     */
    #[DataProvider('invalidBaseUrlProvider')]
    public function test_throws_when_base_url_is_invalid(string $baseUrl): void
    {
        $this->expectException(ConfigurationException::class);

        new Configuration('test-api-key', baseUrl: $baseUrl);
    }

    /**
     * Проверяет, что неположительный request timeout вызывает исключение.
     */
    public function test_throws_when_request_timeout_is_not_positive(): void
    {
        $this->expectException(ConfigurationException::class);

        new Configuration('test-api-key', requestTimeout: 0.0);
    }

    /**
     * Проверяет, что неположительный connect timeout вызывает исключение.
     */
    public function test_throws_when_connect_timeout_is_not_positive(): void
    {
        $this->expectException(ConfigurationException::class);

        new Configuration('test-api-key', connectTimeout: -1.0);
    }

    /**
     * Проверяет, что connect timeout больше request timeout вызывает исключение.
     */
    public function test_throws_when_connect_timeout_exceeds_request_timeout(): void
    {
        $this->expectException(ConfigurationException::class);

        new Configuration(
            apiKey: 'test-api-key',
            requestTimeout: 5.0,
            connectTimeout: 10.0,
        );
    }

    /**
     * Проверяет, что отрицательный max retries вызывает исключение.
     */
    public function test_throws_when_max_retries_is_negative(): void
    {
        $this->expectException(ConfigurationException::class);

        new Configuration('test-api-key', maxRetries: -1);
    }

    /**
     * Проверяет, что отрицательный retry delay вызывает исключение.
     */
    public function test_throws_when_retry_delay_is_negative(): void
    {
        $this->expectException(ConfigurationException::class);

        new Configuration('test-api-key', retryDelayMs: -100);
    }

    /**
     * Проверяет, что нулевой retry delay при max retries > 0 вызывает исключение.
     */
    public function test_throws_when_retry_delay_is_zero_with_retries_enabled(): void
    {
        $this->expectException(ConfigurationException::class);

        new Configuration(
            apiKey: 'test-api-key',
            maxRetries: 2,
            retryDelayMs: 0,
        );
    }

    /**
     * Проверяет, что неположительный max execution time вызывает исключение.
     */
    public function test_throws_when_max_execution_time_is_not_positive(): void
    {
        $this->expectException(ConfigurationException::class);

        new Configuration('test-api-key', maxExecutionTime: 0.0);
    }

    /**
     * Проверяет, что max execution time меньше request timeout вызывает исключение.
     */
    public function test_throws_when_max_execution_time_is_less_than_request_timeout(): void
    {
        $this->expectException(ConfigurationException::class);

        new Configuration(
            apiKey: 'test-api-key',
            requestTimeout: 30.0,
            maxExecutionTime: 10.0,
        );
    }
}
