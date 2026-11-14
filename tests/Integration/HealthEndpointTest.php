<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class HealthEndpointTest extends WebTestCase
{
    public function testHealth(): void
    {
        $client = self::createClient();
        $client->request('GET', '/health');

        self::assertResponseIsSuccessful();

        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertSame('ok', $data['status'] ?? null);

        $checks = $data['checks'] ?? null;
        self::assertIsArray($checks);
        self::assertSame('ok', $checks['application'] ?? null);
    }

    public function testReady(): void
    {
        $client = self::createClient();
        $client->request('GET', '/ready');

        self::assertResponseIsSuccessful();

        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertSame('ok', $data['status'] ?? null);

        $checks = $data['checks'] ?? null;
        self::assertIsArray($checks);
        self::assertSame('ok', $checks['database'] ?? null);
    }
}
