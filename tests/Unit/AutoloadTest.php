<?php

declare(strict_types=1);

namespace Restopulse\PhpSdk\Tests\Unit;

use GuzzleHttp\Client as GuzzleClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Restopulse\PhpSdk\Api\BranchesApi;
use Restopulse\PhpSdk\Api\EventsApi;
use Restopulse\PhpSdk\Client\Client;
use Restopulse\PhpSdk\Configuration\Configuration;
use Restopulse\PhpSdk\Exceptions\RestopulseException;
use Restopulse\PhpSdk\Http\HttpClient;
use Restopulse\PhpSdk\Version;

final class AutoloadTest extends TestCase
{
    public function test_autoloads_sdk_classes(): void
    {
        $this->assertTrue(class_exists(Client::class));
        $this->assertTrue(class_exists(Configuration::class));
        $this->assertTrue(class_exists(HttpClient::class));
        $this->assertTrue(class_exists(BranchesApi::class));
        $this->assertTrue(class_exists(EventsApi::class));
        $this->assertTrue(class_exists(RestopulseException::class));
        $this->assertTrue(class_exists(Version::class));
    }

    public function test_autoloads_composer_dependencies(): void
    {
        $this->assertTrue(class_exists(GuzzleClient::class));
        $this->assertTrue(interface_exists(LoggerInterface::class));
    }

    public function test_client_can_be_instantiated(): void
    {
        $client = new Client(new Configuration('test-api-key'));

        $this->assertInstanceOf(BranchesApi::class, $client->branches());
        $this->assertInstanceOf(EventsApi::class, $client->events());
    }
}
