<?php

declare(strict_types=1);

namespace Restopulse\PhpSdk\Tests\Unit\DTO\Response;

use PHPUnit\Framework\TestCase;
use Restopulse\PhpSdk\DTO\Response\BranchDto;
use Restopulse\PhpSdk\Exceptions\SerializationException;

final class BranchDtoTest extends TestCase
{
    /**
     * Проверяет десериализацию филиала из ответа API.
     */
    public function test_from_array_creates_branch_dto(): void
    {
        $branch = BranchDto::fromArray([
            'id' => 1,
            'name' => 'Название филиала',
        ]);

        $this->assertSame(1, $branch->getId());
        $this->assertSame('Название филиала', $branch->getName());
    }

    /**
     * Проверяет десериализацию списка филиалов из ответа API.
     */
    public function test_list_from_array_creates_branch_dto_list(): void
    {
        $branches = BranchDto::listFromArray([
            [
                'id' => 1,
                'name' => 'Филиал №1',
            ],
            [
                'id' => 2,
                'name' => 'Филиал №2',
            ],
        ]);

        $this->assertCount(2, $branches);
        $this->assertContainsOnlyInstancesOf(BranchDto::class, $branches);
        $this->assertSame(1, $branches[0]->getId());
        $this->assertSame('Филиал №1', $branches[0]->getName());
        $this->assertSame(2, $branches[1]->getId());
        $this->assertSame('Филиал №2', $branches[1]->getName());
    }

    /**
     * Проверяет, что пустой список филиалов десериализуется корректно.
     */
    public function test_list_from_array_accepts_empty_list(): void
    {
        $this->assertSame([], BranchDto::listFromArray([]));
    }

    /**
     * Проверяет, что некорректное поле data вызывает исключение.
     */
    public function test_list_from_response_data_throws_when_data_is_not_array(): void
    {
        $this->expectException(SerializationException::class);

        BranchDto::listFromResponseData(null);
    }

    /**
     * Проверяет, что отсутствие обязательного ключа вызывает исключение.
     */
    public function test_from_array_throws_when_required_key_is_missing(): void
    {
        $this->expectException(SerializationException::class);

        BranchDto::fromArray([
            'id' => 1,
        ]);
    }

    /**
     * Проверяет, что некорректный элемент списка вызывает исключение.
     */
    public function test_list_from_array_throws_when_item_is_not_array(): void
    {
        $this->expectException(SerializationException::class);

        BranchDto::listFromArray(['invalid']);
    }
}
