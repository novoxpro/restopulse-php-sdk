<?php

declare(strict_types=1);

namespace Restopulse\PhpSdk\Tests\Integration;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Restopulse\PhpSdk\Api\BranchesApi;
use Restopulse\PhpSdk\Client\Client;
use Restopulse\PhpSdk\Configuration\Configuration;
use Restopulse\PhpSdk\DTO\Response\BranchDto;
use Restopulse\PhpSdk\Http\HttpClient;

final class BranchesApiTest extends TestCase
{
    /**
     * Проверяет получение списка филиалов через BranchesApi.
     */
    public function test_all_returns_branch_dtos(): void
    {
        $httpClient = $this->createHttpClient([
            new Response(200, [], json_encode([
                'success' => true,
                'data' => [
                    ['id' => 47, 'name' => 'Центральный филиал'],
                    ['id' => 48, 'name' => 'Филиал №2'],
                ],
                'error' => null,
            ], JSON_THROW_ON_ERROR)),
        ]);

        $branches = (new BranchesApi($httpClient))->all();

        $this->assertCount(2, $branches);
        $this->assertContainsOnlyInstancesOf(BranchDto::class, $branches);
        $this->assertSame(47, $branches[0]->getId());
        $this->assertSame('Центральный филиал', $branches[0]->getName());
        $this->assertSame(48, $branches[1]->getId());
        $this->assertSame('Филиал №2', $branches[1]->getName());
    }

    /**
     * Проверяет получение филиалов через Client.
     */
    public function test_client_branches_all_returns_branch_dtos(): void
    {
        $configuration = new Configuration(
            apiKey: 'test-api-key',
            baseUrl: 'https://restopulse.ru',
        );

        $httpClient = $this->createHttpClient([
            new Response(200, [], json_encode([
                'success' => true,
                'data' => [
                    ['id' => 1, 'name' => 'Main'],
                ],
                'error' => null,
            ], JSON_THROW_ON_ERROR)),
        ]);

        $client = new Client($configuration, $httpClient);
        $branches = $client->branches()->all();

        $this->assertCount(1, $branches);
        $this->assertSame(1, $branches[0]->getId());
        $this->assertSame('Main', $branches[0]->getName());
    }

    /**
     * @param list<Response> $responses
     */
    private function createHttpClient(array $responses): HttpClient
    {
        $configuration = new Configuration(
            apiKey: 'test-api-key',
            baseUrl: 'https://restopulse.ru',
        );

        $mockHandler = new MockHandler($responses);

        $guzzleClient = new GuzzleClient([
            'handler' => HandlerStack::create($mockHandler),
            'base_uri' => $configuration->getBaseUrl() . '/',
            'http_errors' => false,
        ]);

        return new HttpClient($configuration, $guzzleClient);
    }
}
