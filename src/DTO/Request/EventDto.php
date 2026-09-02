<?php

declare(strict_types=1);

namespace Restopulse\PhpSdk\DTO\Request;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Restopulse\PhpSdk\Exceptions\ValidationException;

/**
 * DTO события для отправки в REST API.
 */
final class EventDto
{
    /** Максимальная длина типа события. */
    public const MAX_EVENT_TYPE_LENGTH = 100;

    /** Максимальная длина внешнего идентификатора. */
    public const MAX_EXTERNAL_ID_LENGTH = 255;

    /** Максимальная длина заголовка события. */
    public const MAX_TITLE_LENGTH = 255;

    /** Максимальная длина краткого описания события. */
    public const MAX_PREVIEW_LENGTH = 255;

    /** Максимальная длина подробного описания события. */
    public const MAX_MESSAGE_LENGTH = 2000;

    /** Максимальное количество дополнительных полей. */
    public const MAX_FIELDS_COUNT = 100;

    /** Регулярное выражение для проверки UUID v4. */
    private const UUID_V4_PATTERN = '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-4[0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$/';

    /** @var list<int> Идентификаторы филиалов */
    private readonly array $branchIds;

    /** Тип события */
    private readonly string $eventType;

    /** Внешний идентификатор сущности в системе интеграции */
    private readonly string $externalId;

    /** Дата и время события */
    private readonly DateTimeInterface $eventDate;

    /** Заголовок события */
    private readonly string $title;

    /** Краткое описание события */
    private readonly string $preview;

    /** Подробное описание события */
    private readonly string $message;

    /** Ключ идемпотентности запроса */
    private readonly string $idempotencyKey;

    /** @var list<EventFieldDto> Дополнительные поля события */
    private readonly array $fields;

    /**
     * @param list<int> $branchIds Идентификаторы филиалов
     * @param string $eventType Тип события
     * @param string $externalId Внешний идентификатор сущности
     * @param DateTimeInterface $eventDate Дата и время события
     * @param string $title Заголовок события
     * @param string $preview Краткое описание события
     * @param string $message Подробное описание события
     * @param string|null $idempotencyKey Ключ идемпотентности (UUID v4); генерируется автоматически, если не передан
     * @param list<EventFieldDto> $fields Дополнительные поля события
     */
    public function __construct(
        array $branchIds,
        string $eventType,
        string $externalId,
        DateTimeInterface $eventDate,
        string $title,
        string $preview,
        string $message,
        ?string $idempotencyKey = null,
        array $fields = [],
    ) {
        $this->branchIds = $this->normalizeBranchIds($branchIds);
        $this->eventType = trim($eventType);
        $this->externalId = trim($externalId);
        $this->eventDate = $eventDate;
        $this->title = trim($title);
        $this->preview = trim($preview);
        $this->message = trim($message);
        $this->idempotencyKey = $idempotencyKey !== null
            ? trim($idempotencyKey)
            : self::generateUuidV4();
        $this->fields = $fields;

        $this->validate();
    }

    /**
     * Возвращает идентификаторы филиалов.
     *
     * @return list<int>
     */
    public function getBranchIds(): array
    {
        return $this->branchIds;
    }

    /** Возвращает тип события. */
    public function getEventType(): string
    {
        return $this->eventType;
    }

    /** Возвращает внешний идентификатор сущности. */
    public function getExternalId(): string
    {
        return $this->externalId;
    }

    /** Возвращает дату и время события. */
    public function getEventDate(): DateTimeInterface
    {
        return $this->eventDate;
    }

    /** Возвращает дату события в формате ISO 8601. */
    public function getEventDateIso8601(): string
    {
        return DateTimeImmutable::createFromInterface($this->eventDate)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s\Z');
    }

    /** Возвращает заголовок события. */
    public function getTitle(): string
    {
        return $this->title;
    }

    /** Возвращает краткое описание события. */
    public function getPreview(): string
    {
        return $this->preview;
    }

    /** Возвращает подробное описание события. */
    public function getMessage(): string
    {
        return $this->message;
    }

    /** Возвращает ключ идемпотентности запроса. */
    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    /**
     * Возвращает дополнительные поля события.
     *
     * @return list<EventFieldDto>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * Нормализует список идентификаторов филиалов.
     *
     * @param list<int> $branchIds
     *
     * @return list<int>
     */
    private function normalizeBranchIds(array $branchIds): array
    {
        return array_values($branchIds);
    }

    /** Генерирует UUID v4. */
    private static function generateUuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return sprintf(
            '%s-%s-%s-%s-%s',
            bin2hex(substr($bytes, 0, 4)),
            bin2hex(substr($bytes, 4, 2)),
            bin2hex(substr($bytes, 6, 2)),
            bin2hex(substr($bytes, 8, 2)),
            bin2hex(substr($bytes, 10, 6)),
        );
    }

    /** Проверяет корректность полей события. */
    private function validate(): void
    {
        $this->validateBranchIds();
        $this->validateEventType();
        $this->validateExternalId();
        $this->validateTitle();
        $this->validatePreview();
        $this->validateMessage();
        $this->validateIdempotencyKey();
        $this->validateFields();
    }

    /** Проверяет корректность идентификаторов филиалов. */
    private function validateBranchIds(): void
    {
        if ($this->branchIds === []) {
            throw new ValidationException('Branch IDs must contain at least one identifier.');
        }

        foreach ($this->branchIds as $branchId) {
            if (!is_int($branchId) || $branchId <= 0) {
                throw new ValidationException('Branch IDs must be positive integers.');
            }
        }

        if (count($this->branchIds) !== count(array_unique($this->branchIds))) {
            throw new ValidationException('Branch IDs must be unique.');
        }
    }

    /** Проверяет корректность типа события. */
    private function validateEventType(): void
    {
        if ($this->eventType === '') {
            throw new ValidationException('Event type must not be empty.');
        }

        if (mb_strlen($this->eventType) > self::MAX_EVENT_TYPE_LENGTH) {
            throw new ValidationException(
                sprintf('Event type must not exceed %d characters.', self::MAX_EVENT_TYPE_LENGTH),
            );
        }
    }

    /** Проверяет корректность внешнего идентификатора. */
    private function validateExternalId(): void
    {
        if ($this->externalId === '') {
            throw new ValidationException('External ID must not be empty.');
        }

        if (mb_strlen($this->externalId) > self::MAX_EXTERNAL_ID_LENGTH) {
            throw new ValidationException(
                sprintf('External ID must not exceed %d characters.', self::MAX_EXTERNAL_ID_LENGTH),
            );
        }
    }

    /** Проверяет корректность заголовка события. */
    private function validateTitle(): void
    {
        if ($this->title === '') {
            throw new ValidationException('Event title must not be empty.');
        }

        if (mb_strlen($this->title) > self::MAX_TITLE_LENGTH) {
            throw new ValidationException(
                sprintf('Event title must not exceed %d characters.', self::MAX_TITLE_LENGTH),
            );
        }
    }

    /** Проверяет корректность краткого описания события. */
    private function validatePreview(): void
    {
        if ($this->preview === '') {
            throw new ValidationException('Event preview must not be empty.');
        }

        if (mb_strlen($this->preview) > self::MAX_PREVIEW_LENGTH) {
            throw new ValidationException(
                sprintf('Event preview must not exceed %d characters.', self::MAX_PREVIEW_LENGTH),
            );
        }
    }

    /** Проверяет корректность подробного описания события. */
    private function validateMessage(): void
    {
        if ($this->message === '') {
            throw new ValidationException('Event message must not be empty.');
        }

        if (mb_strlen($this->message) > self::MAX_MESSAGE_LENGTH) {
            throw new ValidationException(
                sprintf('Event message must not exceed %d characters.', self::MAX_MESSAGE_LENGTH),
            );
        }
    }

    /** Проверяет корректность ключа идемпотентности. */
    private function validateIdempotencyKey(): void
    {
        if (preg_match(self::UUID_V4_PATTERN, $this->idempotencyKey) !== 1) {
            throw new ValidationException('Idempotency key must be a UUID v4.');
        }
    }

    /** Проверяет корректность дополнительных полей события. */
    private function validateFields(): void
    {
        if (count($this->fields) > self::MAX_FIELDS_COUNT) {
            throw new ValidationException(
                sprintf('Event fields count must not exceed %d.', self::MAX_FIELDS_COUNT),
            );
        }

        foreach ($this->fields as $field) {
            if (!$field instanceof EventFieldDto) {
                throw new ValidationException('Event fields must be instances of EventFieldDto.');
            }
        }
    }
}
