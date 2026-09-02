<?php

declare(strict_types=1);

namespace Restopulse\PhpSdk\DTO\Response;

use Restopulse\PhpSdk\Exceptions\SerializationException;

/**
 * DTO филиала из ответа REST API.
 */
final class BranchDto
{
    public function __construct(
        private readonly int $id,
        private readonly string $name,
    ) {}

    /** Возвращает идентификатор филиала. */
    public function getId(): int
    {
        return $this->id;
    }

    /** Возвращает наименование филиала. */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Создает DTO из элемента поля data ответа REST API.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) self::requireKey($data, 'id'),
            name: (string) self::requireKey($data, 'name'),
        );
    }

    /**
     * Создает список DTO из поля data ответа REST API.
     *
     * @param array<int, array<string, mixed>> $data
     *
     * @return list<self>
     */
    public static function listFromArray(array $data): array
    {
        $branches = [];

        foreach ($data as $index => $item) {
            if (!is_array($item)) {
                throw new SerializationException(
                    sprintf('Branch response item at index %d must be an array.', $index),
                );
            }

            $branches[] = self::fromArray($item);
        }

        return $branches;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function requireKey(array $data, string $key): mixed
    {
        if (!array_key_exists($key, $data)) {
            throw new SerializationException(
                sprintf('Branch response is missing required field "%s".', $key),
            );
        }

        return $data[$key];
    }
}
