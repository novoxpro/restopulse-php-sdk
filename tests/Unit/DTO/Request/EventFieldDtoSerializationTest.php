<?php

declare(strict_types=1);

namespace Restopulse\PhpSdk\Tests\Unit\DTO\Request;

use PHPUnit\Framework\TestCase;
use Restopulse\PhpSdk\DTO\Request\EventFieldDto;

final class EventFieldDtoSerializationTest extends TestCase
{
    /**
     * Проверяет сериализацию дополнительного поля события в массив.
     */
    public function test_to_array_serializes_event_field(): void
    {
        $field = new EventFieldDto('Стоимость', '20 000 ₽');

        $this->assertSame(
            [
                'title' => 'Стоимость',
                'value' => '20 000 ₽',
            ],
            $field->toArray(),
        );
    }
}
