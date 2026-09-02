<?php

declare(strict_types=1);

namespace Restopulse\PhpSdk\Http;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Restopulse\PhpSdk\Configuration\Configuration;
use Restopulse\PhpSdk\Exceptions\ApiException;
use Restopulse\PhpSdk\Exceptions\NetworkException;
use Restopulse\PhpSdk\Exceptions\NotFoundException;
use Restopulse\PhpSdk\Exceptions\RestopulseException;
use Restopulse\PhpSdk\Exceptions\SerializationException;
use Restopulse\PhpSdk\Exceptions\UnauthorizedException;
use Restopulse\PhpSdk\Exceptions\ValidationException;
use Restopulse\PhpSdk\Version;

/**
 * Внутренний HTTP-клиент SDK.
 *
 * Отвечает за выполнение HTTP-запросов к REST API RestoPulse.
 */
final class HttpClient
{
    /** Префикс Public API. */
    private const API_PREFIX = '/api/public/v1';

    /** Конфигурация SDK. */
    private readonly Configuration $configuration;

    /** Экземпляр Guzzle HTTP-клиента. */
    private readonly GuzzleClient $client;

    /** Логгер HTTP-запросов. */
    private readonly HttpClientLogger $logger;

    /** @var (callable(int): void)|null */
    private $sleeper;

    /** @var (callable(): float)|null */
    private $timeProvider;

    /**
     * Создает HTTP-клиент SDK.
     *
     * @param Configuration $configuration Параметры подключения к API.
     * @param GuzzleClient|null $guzzleClient Опциональный Guzzle-клиент (для тестов).
     * @param (callable(int): void)|null $sleeper Опциональная функция ожидания между попытками (для тестов).
     * @param (callable(): float)|null $timeProvider Опциональный источник времени (для тестов).
     */
    public function __construct(
        Configuration $configuration,
        ?GuzzleClient $guzzleClient = null,
        ?callable $sleeper = null,
        ?callable $timeProvider = null,
    ) {
        $this->configuration = $configuration;
        $this->sleeper = $sleeper;
        $this->timeProvider = $timeProvider;
        $this->logger = new HttpClientLogger($configuration->getLogger());
        $this->client = $guzzleClient ?? new GuzzleClient([
            'base_uri' => $configuration->getBaseUrl() . '/',
            'timeout' => $configuration->getRequestTimeout(),
            'connect_timeout' => $configuration->getConnectTimeout(),
            'http_errors' => false,
        ]);
    }

    /**
     * Выполняет GET-запрос к Public API.
     *
     * @param string $path Относительный путь ресурса без префикса `/api/public/v1`.
     *
     * @return mixed Значение поля `data` из успешного ответа API.
     *
     * @throws NetworkException При сетевой ошибке или таймауте.
     * @throws SerializationException При некорректном JSON в ответе.
     * @throws UnauthorizedException При HTTP 401.
     * @throws NotFoundException При HTTP 404.
     * @throws ValidationException При HTTP 422.
     * @throws ApiException При прочих HTTP-ошибках.
     */
    public function get(string $path): mixed
    {
        return $this->request('GET', $path);
    }

    /**
     * Выполняет POST-запрос к Public API.
     *
     * @param string $path Относительный путь ресурса без префикса `/api/public/v1`.
     * @param array<string, mixed> $body Тело запроса, сериализуемое в JSON.
     *
     * @return mixed Значение поля `data` из успешного ответа API.
     *
     * @throws NetworkException При сетевой ошибке или таймауте.
     * @throws SerializationException При некорректном JSON в ответе.
     * @throws UnauthorizedException При HTTP 401.
     * @throws NotFoundException При HTTP 404.
     * @throws ValidationException При HTTP 422.
     * @throws ApiException При прочих HTTP-ошибках.
     */
    public function post(string $path, array $body): mixed
    {
        return $this->request('POST', $path, $body);
    }

    /**
     * Выполняет HTTP-запрос к Public API с учетом настроек повторных попыток.
     *
     * @param string $method HTTP-метод (`GET`, `POST` и т.д.).
     * @param string $path Относительный путь ресурса.
     * @param array<string, mixed>|null $body Тело запроса для методов с телом.
     *
     * @return mixed Значение поля `data` из успешного ответа API.
     *
     * @throws NetworkException При сетевой ошибке или таймауте.
     * @throws SerializationException При некорректном JSON в ответе.
     * @throws RestopulseException При HTTP-ошибке API.
     */
    private function request(string $method, string $path, ?array $body = null): mixed
    {
        $startedAt = $this->now();
        $maxAttempts = $this->configuration->getMaxRetries() + 1;
        $attempt = 0;
        $lastException = null;
        $url = $this->buildFullUrl($path);

        while ($attempt < $maxAttempts) {
            ++$attempt;

            try {
                return $this->executeOnce($method, $path, $url, $body);
            } catch (RestopulseException $exception) {
                $lastException = $exception;

                if (!$this->shouldRetry($exception) || $attempt >= $maxAttempts) {
                    throw $exception;
                }

                if ($this->hasExceededMaxExecutionTime($startedAt)) {
                    throw $exception;
                }

                $this->logger->logRetry(
                    method: $method,
                    url: $url,
                    attempt: $attempt,
                    error: $exception->getMessage(),
                    requestBody: $body,
                );

                $this->sleep($this->calculateRetryDelayMs($attempt));
            }
        }

        throw $lastException ?? new NetworkException('API request failed.');
    }

    /**
     * Выполняет одну попытку HTTP-запроса без повторов.
     *
     * @param array<string, mixed>|null $body
     */
    private function executeOnce(string $method, string $path, string $url, ?array $body): mixed
    {
        $attemptStartedAt = $this->now();

        $this->logger->logRequest($method, $url, $body);

        $options = [
            'headers' => $this->buildHeaders(),
        ];

        if ($body !== null) {
            $options['json'] = $body;
        }

        try {
            $response = $this->client->request(
                $method,
                $this->buildUri($path),
                $options,
            );
        } catch (GuzzleException $exception) {
            $networkException = new NetworkException($exception->getMessage(), 0, $exception);

            $this->logger->logException(
                method: $method,
                url: $url,
                executionTime: $this->now() - $attemptStartedAt,
                exception: $networkException,
                requestBody: $body,
            );

            throw $networkException;
        }

        $statusCode = $response->getStatusCode();
        $responseBody = (string) $response->getBody();
        $executionTime = $this->now() - $attemptStartedAt;

        try {
            $result = $this->handleResponse($statusCode, $responseBody);
        } catch (RestopulseException $exception) {
            $this->logger->logException(
                method: $method,
                url: $url,
                executionTime: $executionTime,
                exception: $exception,
                responseStatus: $statusCode,
                responseBody: $responseBody,
                requestBody: $body,
            );

            throw $exception;
        }

        $this->logger->logSuccess(
            method: $method,
            url: $url,
            responseStatus: $statusCode,
            responseBody: $responseBody,
            executionTime: $executionTime,
        );

        return $result;
    }

    /**
     * Определяет, можно ли повторить запрос после ошибки.
     */
    private function shouldRetry(RestopulseException $exception): bool
    {
        if ($exception instanceof NetworkException) {
            return true;
        }

        return $exception instanceof ApiException && $exception->getCode() >= 500;
    }

    /**
     * Рассчитывает задержку перед повторной попыткой: RetryDelay × 2^(attempt - 1).
     */
    private function calculateRetryDelayMs(int $attempt): int
    {
        return (int) ($this->configuration->getRetryDelayMs() * (2 ** ($attempt - 1)));
    }

    /**
     * Проверяет превышение общего времени исполнения операции.
     */
    private function hasExceededMaxExecutionTime(float $startedAt): bool
    {
        return ($this->now() - $startedAt) >= $this->configuration->getMaxExecutionTime();
    }

    /**
     * Выполняет паузу перед следующей попыткой.
     */
    private function sleep(int $milliseconds): void
    {
        if ($this->sleeper !== null) {
            ($this->sleeper)($milliseconds);

            return;
        }

        usleep($milliseconds * 1000);
    }

    /** Возвращает текущее время в секундах с дробной частью. */
    private function now(): float
    {
        if ($this->timeProvider !== null) {
            return ($this->timeProvider)();
        }

        return microtime(true);
    }

    /**
     * Формирует обязательные заголовки запроса к Public API.
     *
     * @return array<string, string>
     */
    private function buildHeaders(): array
    {
        return [
            'X-API-Key' => $this->configuration->getApiKey(),
            'User-Agent' => sprintf('restopulse-php-sdk/%s', Version::SDK_VERSION),
            'Accept' => 'application/json',
        ];
    }

    /**
     * Собирает полный путь к ресурсу Public API.
     *
     * @param string $path Относительный путь ресурса.
     */
    private function buildUri(string $path): string
    {
        return rtrim(self::API_PREFIX, '/') . '/' . ltrim($path, '/');
    }

    /**
     * Собирает полный URL запроса к Public API.
     */
    private function buildFullUrl(string $path): string
    {
        return $this->configuration->getBaseUrl() . $this->buildUri($path);
    }

    /**
     * Обрабатывает HTTP-ответ: извлекает `data` или преобразует ошибку в исключение SDK.
     *
     * @return mixed Значение поля `data` из успешного ответа API.
     *
     * @throws RestopulseException При HTTP-ошибке API.
     * @throws SerializationException При некорректном JSON в ответе.
     */
    private function handleResponse(int $statusCode, string $responseBody): mixed
    {
        $decoded = $this->decodeBody($responseBody);

        if ($statusCode >= 200 && $statusCode < 300) {
            return $this->extractSuccessData($decoded);
        }

        throw $this->mapHttpError($statusCode, $decoded);
    }

    /**
     * Извлекает поле `data` из успешного JSON-ответа API.
     *
     * @param array<string, mixed> $decoded Декодированное тело ответа.
     *
     * @return mixed Значение поля `data` или `null`, если поле отсутствует.
     *
     * @throws ApiException Если в ответе `success` равен `false`.
     */
    private function extractSuccessData(array $decoded): mixed
    {
        if (($decoded['success'] ?? null) === false) {
            throw new ApiException($this->extractErrorMessage($decoded));
        }

        return $decoded['data'] ?? null;
    }

    /**
     * Преобразует HTTP-код ошибки в исключение SDK.
     *
     * @param array<string, mixed> $decoded Декодированное тело ответа.
     */
    private function mapHttpError(int $statusCode, array $decoded): RestopulseException
    {
        $message = $this->extractErrorMessage($decoded);

        return match (true) {
            $statusCode === 401 => new UnauthorizedException($message, $statusCode),
            $statusCode === 404 => new NotFoundException($message, $statusCode),
            $statusCode === 422 => new ValidationException($message, $statusCode),
            $statusCode >= 500 => new ApiException($message, $statusCode),
            default => new ApiException($message, $statusCode),
        };
    }

    /**
     * Извлекает текст ошибки из поля `error.message` ответа API.
     *
     * @param array<string, mixed> $decoded Декодированное тело ответа.
     */
    private function extractErrorMessage(array $decoded): string
    {
        $error = $decoded['error'] ?? null;

        if (is_array($error)) {
            $message = $error['message'] ?? null;

            if (is_string($message) && $message !== '') {
                return $message;
            }
        }

        return 'API request failed.';
    }

    /**
     * Декодирует JSON-тело HTTP-ответа.
     *
     * @return array<string, mixed> Декодированный объект ответа.
     *
     * @throws SerializationException При пустом невалидном JSON или если корень не является объектом.
     */
    private function decodeBody(string $body): array
    {
        if ($body === '') {
            return [];
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new SerializationException('Failed to decode API response JSON.', 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new SerializationException('API response must be a JSON object.');
        }

        return $decoded;
    }
}
