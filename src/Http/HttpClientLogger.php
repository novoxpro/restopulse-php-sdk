<?php

declare(strict_types=1);

namespace Restopulse\PhpSdk\Http;

use Psr\Log\LoggerInterface;
use Restopulse\PhpSdk\Exceptions\RestopulseException;

/**
 * Логирование HTTP-запросов SDK через PSR-3.
 */
final class HttpClientLogger
{
    /**
     * Создает логгер HTTP-запросов SDK.
     */
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Логирует исходящий HTTP-запрос.
     *
     * @param array<string, mixed>|null $requestBody Тело запроса.
     */
    public function logRequest(string $method, string $url, ?array $requestBody): void
    {
        $this->logger->debug('RestoPulse API request.', $this->sanitize([
            'method' => $method,
            'url' => $url,
            'requestBody' => $requestBody,
        ]));
    }

    /**
     * Логирует успешный HTTP-ответ.
     *
     * @param string $responseBody Тело ответа.
     * @param float $executionTime Время выполнения попытки (секунды).
     */
    public function logSuccess(
        string $method,
        string $url,
        int $responseStatus,
        string $responseBody,
        float $executionTime,
    ): void {
        $this->logger->info('RestoPulse API request completed.', $this->sanitize([
            'method' => $method,
            'url' => $url,
            'responseStatus' => $responseStatus,
            'responseBody' => $responseBody,
            'executionTime' => $this->formatExecutionTime($executionTime),
        ]));
    }

    /**
     * Логирует повторную попытку HTTP-запроса.
     *
     * @param int $attempt Номер завершившейся неуспешной попытки.
     * @param array<string, mixed>|null $requestBody Тело запроса.
     */
    public function logRetry(
        string $method,
        string $url,
        int $attempt,
        string $error,
        ?array $requestBody = null,
    ): void {
        $this->logger->warning('RestoPulse API request retry.', $this->sanitize([
            'method' => $method,
            'url' => $url,
            'attempt' => $attempt,
            'error' => $error,
            'requestBody' => $requestBody,
        ]));
    }

    /**
     * Логирует неуспешный HTTP-запрос.
     *
     * @param float $executionTime Время выполнения попытки (секунды).
     * @param array<string, mixed>|null $requestBody Тело запроса.
     */
    public function logError(
        string $method,
        string $url,
        float $executionTime,
        string $error,
        ?int $responseStatus = null,
        ?string $responseBody = null,
        ?array $requestBody = null,
    ): void {
        $context = [
            'method' => $method,
            'url' => $url,
            'executionTime' => $this->formatExecutionTime($executionTime),
            'error' => $error,
            'requestBody' => $requestBody,
        ];

        if ($responseStatus !== null) {
            $context['responseStatus'] = $responseStatus;
        }

        if ($responseBody !== null) {
            $context['responseBody'] = $responseBody;
        }

        $this->logger->error('RestoPulse API request failed.', $this->sanitize($context));
    }

    /**
     * Логирует исключение SDK как ошибку HTTP-запроса.
     *
     * @param array<string, mixed>|null $requestBody Тело запроса.
     */
    public function logException(
        string $method,
        string $url,
        float $executionTime,
        RestopulseException $exception,
        ?int $responseStatus = null,
        ?string $responseBody = null,
        ?array $requestBody = null,
    ): void {
        $this->logError(
            method: $method,
            url: $url,
            executionTime: $executionTime,
            error: $exception->getMessage(),
            responseStatus: $responseStatus,
            responseBody: $responseBody,
            requestBody: $requestBody,
        );
    }

    /**
     * Маскирует чувствительные значения в контексте лога.
     *
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function sanitize(array $context): array
    {
        $sanitized = [];

        foreach ($context as $key => $value) {
            if ($this->isApiKeyField((string) $key)) {
                $sanitized[$key] = '[redacted]';
                continue;
            }

            $sanitized[$key] = $this->sanitizeValue($value);
        }

        return $sanitized;
    }

    /**
     * Рекурсивно маскирует чувствительные значения во вложенных массивах.
     */
    private function sanitizeValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $sanitized = [];

        foreach ($value as $key => $item) {
            if (is_string($key) && $this->isApiKeyField($key)) {
                $sanitized[$key] = '[redacted]';
                continue;
            }

            $sanitized[$key] = $this->sanitizeValue($item);
        }

        return $sanitized;
    }

    /**
     * Проверяет, является ли ключ полем API Key.
     */
    private function isApiKeyField(string $key): bool
    {
        return strtolower(str_replace('_', '-', $key)) === 'x-api-key';
    }

    /**
     * Форматирует время выполнения для лога.
     */
    private function formatExecutionTime(float $executionTime): float
    {
        return round($executionTime, 3);
    }
}
