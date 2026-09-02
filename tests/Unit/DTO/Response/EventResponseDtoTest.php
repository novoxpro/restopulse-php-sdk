<?php

declare(strict_types=1);

namespace Restopulse\PhpSdk\Tests\Unit\DTO\Response;

use PHPUnit\Framework\TestCase;
use Restopulse\PhpSdk\DTO\Response\EventResponseDto;
use Restopulse\PhpSdk\Exceptions\SerializationException;

final class EventResponseDtoTest extends TestCase
{
    /**
     * Проверяет десериализацию ответа API на отправку события.
     */
    public function test_from_array_creates_event_response_dto(): void
    {
        $response = EventResponseDto::fromArray([
            'id' => 12345,
        ]);

        $this->assertSame(12345, $response->getId());
    }

    /**
     * Проверяет, что отсутствие id вызывает исключение.
     */
    public function test_from_array_throws_when_id_is_missing(): void
    {
        $this->expectException(SerializationException::class);

        EventResponseDto::fromArray([]);
    }
}
