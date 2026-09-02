<?php

declare(strict_types=1);

namespace Restopulse\PhpSdk\Tests\Support;

use Psr\Log\AbstractLogger;

/**
 * In-memory PSR-3 logger for tests.
 */
final class TestLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    public array $records = [];

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    /**
     * @return list<array{level: string, message: string, context: array<string, mixed>}>
     */
    public function recordsByLevel(string $level): array
    {
        return array_values(array_filter(
            $this->records,
            static fn (array $record): bool => $record['level'] === $level,
        ));
    }
}
