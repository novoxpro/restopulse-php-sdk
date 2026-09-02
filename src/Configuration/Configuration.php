<?php

declare(strict_types=1);

namespace Restopulse\PhpSdk\Configuration;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Restopulse\PhpSdk\Exceptions\ConfigurationException;

/**
 * Конфигурация SDK.
 *
 * Содержит параметры подключения к REST API RestoPulse:
 * API Key, базовый URL, таймауты, логгер и настройки повторных попыток.
 */
final class Configuration
{
    /** Базовый адрес сервера RestoPulse без версии API и пути. */
    public const DEFAULT_BASE_URL = 'https://restopulse.ru';

    /** Максимальное время выполнения HTTP-запроса (секунды). */
    public const DEFAULT_REQUEST_TIMEOUT = 30.0;

    /** Максимальное время ожидания установки соединения (секунды). */
    public const DEFAULT_CONNECT_TIMEOUT = 10.0;

    /** Максимальное количество повторных попыток после первой неуспешной отправки. */
    public const DEFAULT_MAX_RETRIES = 0;

    /** Базовый интервал между повторными попытками (миллисекунды). */
    public const DEFAULT_RETRY_DELAY_MS = 1000;

    /** Общее время исполнения операции, включая ожидание между повторами (секунды). */
    public const DEFAULT_MAX_EXECUTION_TIME = 60.0;

    private readonly string $apiKey;

    private readonly string $baseUrl;

    private readonly float $requestTimeout;

    private readonly float $connectTimeout;

    private readonly ?LoggerInterface $logger;

    private readonly int $maxRetries;

    private readonly int $retryDelayMs;

    private readonly float $maxExecutionTime;

    /**
     * @param string $apiKey Ключ API интеграции
     * @param string|null $baseUrl Базовый адрес сервера RestoPulse
     * @param float|null $requestTimeout Таймаут HTTP-запроса (секунды)
     * @param float|null $connectTimeout Таймаут установки соединения (секунды)
     * @param LoggerInterface|null $logger PSR-3 логгер
     * @param int|null $maxRetries Максимальное количество повторных попыток
     * @param int|null $retryDelayMs Интервал между повторными попытками (миллисекунды)
     * @param float|null $maxExecutionTime Общее время исполнения операции (секунды)
     */
    public function __construct(
        string $apiKey,
        ?string $baseUrl = null,
        ?float $requestTimeout = null,
        ?float $connectTimeout = null,
        ?LoggerInterface $logger = null,
        ?int $maxRetries = null,
        ?int $retryDelayMs = null,
        ?float $maxExecutionTime = null,
    ) {
        $this->apiKey = $this->normalizeApiKey($apiKey);
        $this->baseUrl = $this->normalizeBaseUrl($baseUrl ?? self::DEFAULT_BASE_URL);
        $this->requestTimeout = $requestTimeout ?? self::DEFAULT_REQUEST_TIMEOUT;
        $this->connectTimeout = $connectTimeout ?? self::DEFAULT_CONNECT_TIMEOUT;
        $this->logger = $logger;
        $this->maxRetries = $maxRetries ?? self::DEFAULT_MAX_RETRIES;
        $this->retryDelayMs = $retryDelayMs ?? self::DEFAULT_RETRY_DELAY_MS;
        $this->maxExecutionTime = $maxExecutionTime ?? self::DEFAULT_MAX_EXECUTION_TIME;

        $this->validate();
    }

    /** Возвращает ключ API интеграции. */
    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    /** Возвращает базовый адрес сервера RestoPulse. */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /** Возвращает таймаут HTTP-запроса (секунды). */
    public function getRequestTimeout(): float
    {
        return $this->requestTimeout;
    }

    /** Возвращает таймаут установки соединения (секунды). */
    public function getConnectTimeout(): float
    {
        return $this->connectTimeout;
    }

    /** Возвращает PSR-3 логгер или NullLogger, если логгер не задан. */
    public function getLogger(): LoggerInterface
    {
        return $this->logger ?? new NullLogger();
    }

    /** Возвращает максимальное количество повторных попыток. */
    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    /** Возвращает интервал между повторными попытками (миллисекунды). */
    public function getRetryDelayMs(): int
    {
        return $this->retryDelayMs;
    }

    /** Возвращает общее время исполнения операции (секунды). */
    public function getMaxExecutionTime(): float
    {
        return $this->maxExecutionTime;
    }

    /** Нормализует ключ API: удаляет пробелы по краям. */
    private function normalizeApiKey(string $apiKey): string
    {
        return trim($apiKey);
    }

    /** Нормализует базовый URL: удаляет пробелы и завершающий слэш. */
    private function normalizeBaseUrl(string $baseUrl): string
    {
        return rtrim(trim($baseUrl), '/');
    }

    /** Проверяет корректность параметров конфигурации. */
    private function validate(): void
    {
        if ($this->apiKey === '') {
            throw new ConfigurationException('API key must not be empty.');
        }

        if (!filter_var($this->baseUrl, FILTER_VALIDATE_URL)) {
            throw new ConfigurationException('Base URL must be a valid URL.');
        }

        $scheme = parse_url($this->baseUrl, PHP_URL_SCHEME);

        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new ConfigurationException('Base URL must use the HTTP or HTTPS scheme.');
        }

        if ($this->requestTimeout <= 0) {
            throw new ConfigurationException('Request timeout must be greater than zero.');
        }

        if ($this->connectTimeout <= 0) {
            throw new ConfigurationException('Connect timeout must be greater than zero.');
        }

        if ($this->connectTimeout > $this->requestTimeout) {
            throw new ConfigurationException('Connect timeout must not exceed request timeout.');
        }

        if ($this->maxRetries < 0) {
            throw new ConfigurationException('Max retries must be zero or greater.');
        }

        if ($this->retryDelayMs < 0) {
            throw new ConfigurationException('Retry delay must be zero or greater.');
        }

        if ($this->maxRetries > 0 && $this->retryDelayMs === 0) {
            throw new ConfigurationException(
                'Retry delay must be greater than zero when max retries are enabled.'
            );
        }

        if ($this->maxExecutionTime <= 0) {
            throw new ConfigurationException('Max execution time must be greater than zero.');
        }

        if ($this->maxExecutionTime < $this->requestTimeout) {
            throw new ConfigurationException(
                'Max execution time must be greater than or equal to request timeout.'
            );
        }
    }
}
