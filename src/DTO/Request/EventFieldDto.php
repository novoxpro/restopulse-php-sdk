<?php

declare(strict_types=1);

namespace Restopulse\PhpSdk\DTO\Request;

use Restopulse\PhpSdk\Exceptions\ValidationException;

/**
 * Дополнительное поле события для детальной карточки.
 */
final class EventFieldDto
{
    public const MAX_TITLE_LENGTH = 255;

    public const MAX_VALUE_LENGTH = 500;

    private readonly string $title;

    private readonly string $value;

    public function __construct(string $title, string $value)
    {
        $this->title = trim($title);
        $this->value = trim($value);

        $this->validate();
    }

    /** Возвращает название поля. */
    public function getTitle(): string
    {
        return $this->title;
    }

    /** Возвращает значение поля. */
    public function getValue(): string
    {
        return $this->value;
    }

    /** Проверяет корректность полей. */
    private function validate(): void
    {
        if ($this->title === '') {
            throw new ValidationException('Event field title must not be empty.');
        }

        if (mb_strlen($this->title) > self::MAX_TITLE_LENGTH) {
            throw new ValidationException(
                sprintf('Event field title must not exceed %d characters.', self::MAX_TITLE_LENGTH),
            );
        }

        if ($this->value === '') {
            throw new ValidationException('Event field value must not be empty.');
        }

        if (mb_strlen($this->value) > self::MAX_VALUE_LENGTH) {
            throw new ValidationException(
                sprintf('Event field value must not exceed %d characters.', self::MAX_VALUE_LENGTH),
            );
        }
    }
}
