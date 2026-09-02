<?php

declare(strict_types=1);

namespace Restopulse\PhpSdk\Tests\Unit\DTO\Request;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use PHPUnit\Framework\TestCase;
use Restopulse\PhpSdk\DTO\Request\EventDto;
use Restopulse\PhpSdk\DTO\Request\EventFieldDto;
use Restopulse\PhpSdk\Exceptions\ValidationException;

final class EventDtoTest extends TestCase
{
    private const IDEMPOTENCY_KEY = '550e8400-e29b-41d4-a716-446655440000';

    /**
     * Проверяет создание корректного события со всеми полями.
     */
    public function test_creates_valid_event_with_fields(): void
    {
        $eventDate = new DateTimeImmutable('2026-07-03T14:30:00+03:00');
        $field = new EventFieldDto('Стоимость', '20 000 ₽');

        $event = new EventDto(
            branchIds: [1, 2],
            eventType: 'order.created',
            externalId: 'ORD-100500',
            eventDate: $eventDate,
            title: 'Новый заказ',
            preview: 'Заказ оформлен на сайте',
            message: 'Заказ №100500 оформлен на сайте.',
            idempotencyKey: self::IDEMPOTENCY_KEY,
            fields: [$field],
        );

        $this->assertSame([1, 2], $event->getBranchIds());
        $this->assertSame('order.created', $event->getEventType());
        $this->assertSame('ORD-100500', $event->getExternalId());
        $this->assertSame($eventDate, $event->getEventDate());
        $this->assertSame('2026-07-03T11:30:00Z', $event->getEventDateIso8601());
        $this->assertSame('Новый заказ', $event->getTitle());
        $this->assertSame('Заказ оформлен на сайте', $event->getPreview());
        $this->assertSame('Заказ №100500 оформлен на сайте.', $event->getMessage());
        $this->assertSame(self::IDEMPOTENCY_KEY, $event->getIdempotencyKey());
        $this->assertSame([$field], $event->getFields());
    }

    /**
     * Проверяет автоматическую генерацию idempotencyKey.
     */
    public function test_generates_idempotency_key_when_not_provided(): void
    {
        $event = $this->createEvent();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $event->getIdempotencyKey(),
        );
    }

    /**
     * Проверяет сохранение переданного вручную idempotencyKey.
     */
    public function test_preserves_manual_idempotency_key(): void
    {
        $event = $this->createEvent(idempotencyKey: self::IDEMPOTENCY_KEY);

        $this->assertSame(self::IDEMPOTENCY_KEY, $event->getIdempotencyKey());
    }

    /**
     * Проверяет поддержку DateTime.
     */
    public function test_accepts_datetime_instance(): void
    {
        $eventDate = new DateTime('2026-07-03T14:30:00Z');

        $event = $this->createEvent(eventDate: $eventDate);

        $this->assertSame($eventDate, $event->getEventDate());
        $this->assertSame('2026-07-03T14:30:00Z', $event->getEventDateIso8601());
    }

    /**
     * Проверяет, что пустой список branchIds вызывает исключение.
     */
    public function test_throws_when_branch_ids_is_empty(): void
    {
        $this->expectException(ValidationException::class);

        $this->createEvent(branchIds: []);
    }

    /**
     * Проверяет, что неположительный branchId вызывает исключение.
     */
    public function test_throws_when_branch_id_is_not_positive_integer(): void
    {
        $this->expectException(ValidationException::class);

        $this->createEvent(branchIds: [1, 0]);
    }

    /**
     * Проверяет, что повторяющиеся branchIds вызывают исключение.
     */
    public function test_throws_when_branch_ids_are_not_unique(): void
    {
        $this->expectException(ValidationException::class);

        $this->createEvent(branchIds: [1, 1]);
    }

    /**
     * Проверяет, что пустой eventType вызывает исключение.
     */
    public function test_throws_when_event_type_is_empty(): void
    {
        $this->expectException(ValidationException::class);

        $this->createEvent(eventType: '   ');
    }

    /**
     * Проверяет, что слишком длинный eventType вызывает исключение.
     */
    public function test_throws_when_event_type_is_too_long(): void
    {
        $this->expectException(ValidationException::class);

        $this->createEvent(eventType: str_repeat('a', EventDto::MAX_EVENT_TYPE_LENGTH + 1));
    }

    /**
     * Проверяет, что пустой externalId вызывает исключение.
     */
    public function test_throws_when_external_id_is_empty(): void
    {
        $this->expectException(ValidationException::class);

        $this->createEvent(externalId: '');
    }

    /**
     * Проверяет, что слишком длинный externalId вызывает исключение.
     */
    public function test_throws_when_external_id_is_too_long(): void
    {
        $this->expectException(ValidationException::class);

        $this->createEvent(externalId: str_repeat('a', EventDto::MAX_EXTERNAL_ID_LENGTH + 1));
    }

    /**
     * Проверяет, что пустой title вызывает исключение.
     */
    public function test_throws_when_title_is_empty(): void
    {
        $this->expectException(ValidationException::class);

        $this->createEvent(title: '');
    }

    /**
     * Проверяет, что слишком длинный title вызывает исключение.
     */
    public function test_throws_when_title_is_too_long(): void
    {
        $this->expectException(ValidationException::class);

        $this->createEvent(title: str_repeat('a', EventDto::MAX_TITLE_LENGTH + 1));
    }

    /**
     * Проверяет, что пустой preview вызывает исключение.
     */
    public function test_throws_when_preview_is_empty(): void
    {
        $this->expectException(ValidationException::class);

        $this->createEvent(preview: '');
    }

    /**
     * Проверяет, что слишком длинный preview вызывает исключение.
     */
    public function test_throws_when_preview_is_too_long(): void
    {
        $this->expectException(ValidationException::class);

        $this->createEvent(preview: str_repeat('a', EventDto::MAX_PREVIEW_LENGTH + 1));
    }

    /**
     * Проверяет, что пустой message вызывает исключение.
     */
    public function test_throws_when_message_is_empty(): void
    {
        $this->expectException(ValidationException::class);

        $this->createEvent(message: '');
    }

    /**
     * Проверяет, что слишком длинный message вызывает исключение.
     */
    public function test_throws_when_message_is_too_long(): void
    {
        $this->expectException(ValidationException::class);

        $this->createEvent(message: str_repeat('a', EventDto::MAX_MESSAGE_LENGTH + 1));
    }

    /**
     * Проверяет, что некорректный idempotencyKey вызывает исключение.
     */
    public function test_throws_when_idempotency_key_is_invalid(): void
    {
        $this->expectException(ValidationException::class);

        $this->createEvent(idempotencyKey: 'not-a-uuid');
    }

    /**
     * Проверяет, что количество fields больше 100 вызывает исключение.
     */
    public function test_throws_when_fields_count_exceeds_limit(): void
    {
        $fields = [];

        for ($i = 0; $i < EventDto::MAX_FIELDS_COUNT + 1; $i++) {
            $fields[] = new EventFieldDto('title ' . $i, 'value ' . $i);
        }

        $this->expectException(ValidationException::class);

        $this->createEvent(fields: $fields);
    }

    /**
     * @param list<int> $branchIds
     * @param list<EventFieldDto> $fields
     */
    private function createEvent(
        array $branchIds = [1],
        string $eventType = 'order.created',
        string $externalId = 'ORD-100500',
        ?DateTimeInterface $eventDate = null,
        string $title = 'Новый заказ',
        string $preview = 'Заказ оформлен на сайте',
        string $message = 'Заказ оформлен.',
        ?string $idempotencyKey = null,
        array $fields = [],
    ): EventDto {
        return new EventDto(
            branchIds: $branchIds,
            eventType: $eventType,
            externalId: $externalId,
            eventDate: $eventDate ?? new DateTimeImmutable('2026-07-03T14:30:00Z'),
            title: $title,
            preview: $preview,
            message: $message,
            idempotencyKey: $idempotencyKey,
            fields: $fields,
        );
    }
}
