<?php

declare(strict_types=1);

namespace Restopulse\PhpSdk\Tests\Unit\Client;

use PHPUnit\Framework\TestCase;
use Restopulse\PhpSdk\Api\BranchesApi;
use Restopulse\PhpSdk\Api\EventsApi;
use Restopulse\PhpSdk\Client\Client;
use Restopulse\PhpSdk\Configuration\Configuration;

final class ClientTest extends TestCase
{
    /**
     * Проверяет публичный API Client.
     */
    public function test_exposes_branches_and_events_api(): void
    {
        $client = new Client(new Configuration('test-api-key'));

        $this->assertInstanceOf(BranchesApi::class, $client->branches());
        $this->assertInstanceOf(EventsApi::class, $client->events());
        $this->assertNotSame($client->branches(), $client->branches());
        $this->assertNotSame($client->events(), $client->events());
    }
}
