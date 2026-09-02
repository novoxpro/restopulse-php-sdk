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

    /**
     * Создает HTTP-клиент SDK.
     *
     * @param Configuration $configuration Параметры подключения к API.
     * @param GuzzleClient|null $guzzleClient Опциональный Guzzle-клиент (для тестов).
     */
    public function __construct(
        Configuration $configuration,
        ?GuzzleClient $guzzleClient = null,
    ) {
        $this->configuration = $configuration;
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
     * Выполняет HTTP-запрос к Public API.
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
            throw new NetworkException($exception->getMessage(), 0, $exception);
        }

        return $this->handleResponse($response);
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
     * Обрабатывает HTTP-ответ: извлекает `data` или преобразует ошибку в исключение SDK.
     *
     * @return mixed Значение поля `data` из успешного ответа API.
     *
     * @throws RestopulseException При HTTP-ошибке API.
     * @throws SerializationException При некорректном JSON в ответе.
     */
    private function handleResponse(ResponseInterface $response): mixed
    {
        $statusCode = $response->getStatusCode();
        $decoded = $this->decodeResponse($response);

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
    private function decodeResponse(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();

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
