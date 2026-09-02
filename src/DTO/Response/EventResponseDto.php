<?php

declare(strict_types=1);

namespace Restopulse\PhpSdk\DTO\Response;

use Restopulse\PhpSdk\Exceptions\SerializationException;

/**
 * DTO ответа REST API на отправку события.
 */
final class EventResponseDto
{
    public function __construct(
        private readonly int $id,
    ) {}

    /** Возвращает идентификатор созданного события. */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Создает DTO из поля data ответа REST API.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (!array_key_exists('id', $data)) {
            throw new SerializationException('Event response is missing required field "id".');
        }

        return new self(id: (int) $data['id']);
    }
}
