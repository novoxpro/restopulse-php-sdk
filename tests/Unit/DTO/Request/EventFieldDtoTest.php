<?php

declare(strict_types=1);

namespace Restopulse\PhpSdk\Tests\Unit\DTO\Request;

use PHPUnit\Framework\TestCase;
use Restopulse\PhpSdk\DTO\Request\EventFieldDto;
use Restopulse\PhpSdk\Exceptions\ValidationException;

final class EventFieldDtoTest extends TestCase
{
    /**
     * Проверяет создание корректного дополнительного поля события.
     */
    public function test_creates_valid_event_field(): void
    {
        $field = new EventFieldDto('  Номер заказа  ', ' 123456 ');

        $this->assertSame('Номер заказа', $field->getTitle());
        $this->assertSame('123456', $field->getValue());
    }

    /**
     * Проверяет, что пустой title вызывает исключение.
     */
    public function test_throws_when_title_is_empty(): void
    {
        $this->expectException(ValidationException::class);

        new EventFieldDto('', 'value');
    }

    /**
     * Проверяет, что пустой value вызывает исключение.
     */
    public function test_throws_when_value_is_empty(): void
    {
        $this->expectException(ValidationException::class);

        new EventFieldDto('title', '   ');
    }

    /**
     * Проверяет, что слишком длинный title вызывает исключение.
     */
    public function test_throws_when_title_is_too_long(): void
    {
        $this->expectException(ValidationException::class);

        new EventFieldDto(str_repeat('a', EventFieldDto::MAX_TITLE_LENGTH + 1), 'value');
    }

    /**
     * Проверяет, что слишком длинный value вызывает исключение.
     */
    public function test_throws_when_value_is_too_long(): void
    {
        $this->expectException(ValidationException::class);

        new EventFieldDto('title', str_repeat('a', EventFieldDto::MAX_VALUE_LENGTH + 1));
    }
}
