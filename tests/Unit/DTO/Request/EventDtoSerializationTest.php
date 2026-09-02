<?php

declare(strict_types=1);

namespace Restopulse\PhpSdk\Tests\Unit\DTO\Request;

use DateTime;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Restopulse\PhpSdk\DTO\Request\EventDto;
use Restopulse\PhpSdk\DTO\Request\EventFieldDto;

final class EventDtoSerializationTest extends TestCase
{
    private const IDEMPOTENCY_KEY = '550e8400-e29b-41d4-a716-446655440000';

    /**
     * Проверяет, что toArray() использует корректные имена JSON-полей.
     */
    public function test_to_array_uses_expected_json_field_names(): void
    {
        $payload = $this->createEvent()->toArray();

        $this->assertSame(
            [
                'branchIds',
                'eventType',
                'externalId',
                'eventDate',
                'title',
                'preview',
                'message',
                'idempotencyKey',
            ],
            array_keys($payload),
        );
    }

    /**
     * Проверяет сериализацию даты события в ISO 8601.
     */
    public function test_to_array_serializes_event_date_as_iso8601(): void
    {
        $event = new EventDto(
            branchIds: [47],
            eventType: 'order.created',
            externalId: 'ORDER-123456',
            eventDate: new DateTimeImmutable('2026-09-01T12:30:00+03:00'),
            title: 'Создан новый заказ',
            preview: 'На сайте создан новый заказ.',
            message: 'Ваш заказ №123456 успешно создан.',
            idempotencyKey: self::IDEMPOTENCY_KEY,
        );

        $this->assertSame('2026-09-01T09:30:00Z', $event->toArray()['eventDate']);
    }

    /**
     * Проверяет сериализацию DateTime в ISO 8601.
     */
    public function test_to_array_serializes_datetime_instance_as_iso8601(): void
    {
        $event = new EventDto(
            branchIds: [47],
            eventType: 'order.created',
            externalId: 'ORDER-123456',
            eventDate: new DateTime('2026-07-03T14:30:00Z'),
            title: 'Title',
            preview: 'Preview',
            message: 'Message',
            idempotencyKey: self::IDEMPOTENCY_KEY,
        );

        $this->assertSame('2026-07-03T14:30:00Z', $event->toArray()['eventDate']);
    }

    /**
     * Проверяет сериализацию idempotencyKey.
     */
    public function test_to_array_includes_idempotency_key(): void
    {
        $payload = $this->createEvent(idempotencyKey: self::IDEMPOTENCY_KEY)->toArray();

        $this->assertSame(self::IDEMPOTENCY_KEY, $payload['idempotencyKey']);
    }

    /**
     * Проверяет сериализацию дополнительных полей события.
     */
    public function test_to_array_serializes_fields(): void
    {
        $event = new EventDto(
            branchIds: [47],
            eventType: 'order.created',
            externalId: 'ORDER-123456',
            eventDate: new DateTimeImmutable('2026-09-01T12:30:00Z'),
            title: 'Создан новый заказ',
            preview: 'На сайте создан новый заказ.',
            message: 'Ваш заказ №123456 успешно создан.',
            idempotencyKey: self::IDEMPOTENCY_KEY,
            fields: [
                new EventFieldDto('Номер заказа', '123456'),
                new EventFieldDto('Статус', 'В пути'),
            ],
        );

        $this->assertSame(
            [
                [
                    'title' => 'Номер заказа',
                    'value' => '123456',
                ],
                [
                    'title' => 'Статус',
                    'value' => 'В пути',
                ],
            ],
            $event->toArray()['fields'],
        );
    }

    /**
     * Проверяет, что пустой список fields не попадает в payload.
     */
    public function test_to_array_omits_empty_fields(): void
    {
        $payload = $this->createEvent()->toArray();

        $this->assertArrayNotHasKey('fields', $payload);
    }

    /**
     * Проверяет сериализацию EventDto в JSON.
     */
    public function test_to_json_serializes_event_dto(): void
    {
        $event = new EventDto(
            branchIds: [47],
            eventType: 'order.created',
            externalId: 'ORDER-123456',
            eventDate: new DateTimeImmutable('2026-09-01T12:30:00Z'),
            title: 'Создан новый заказ',
            preview: 'На сайте создан новый заказ.',
            message: 'Ваш заказ №123456 успешно создан.',
            idempotencyKey: self::IDEMPOTENCY_KEY,
            fields: [
                new EventFieldDto('Номер заказа', '123456'),
            ],
        );

        $json = $event->toJson();
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame($event->toArray(), $decoded);
    }

    /**
     * Проверяет, что JSON содержит Unicode-символы без экранирования.
     */
    public function test_to_json_preserves_unicode_characters(): void
    {
        $json = $this->createEvent(
            title: 'Создан новый заказ',
            preview: 'На сайте создан новый заказ.',
            message: 'Сумма: 1450 ₽',
        )->toJson();

        $this->assertStringContainsString('Создан новый заказ', $json);
        $this->assertStringContainsString('1450 ₽', $json);
        $this->assertStringNotContainsString('\u', $json);
    }

    private function createEvent(
        ?string $idempotencyKey = self::IDEMPOTENCY_KEY,
        string $title = 'Title',
        string $preview = 'Preview',
        string $message = 'Message',
    ): EventDto {
        return new EventDto(
            branchIds: [47],
            eventType: 'order.created',
            externalId: 'ORDER-123456',
            eventDate: new DateTimeImmutable('2026-09-01T12:30:00Z'),
            title: $title,
            preview: $preview,
            message: $message,
            idempotencyKey: $idempotencyKey,
        );
    }
}
