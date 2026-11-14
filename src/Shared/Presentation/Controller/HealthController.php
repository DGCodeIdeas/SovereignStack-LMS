<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Shared\Presentation\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class HealthController
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    public function health(): Response
    {
        return new JsonResponse([
            'status' => 'healthy',
            'timestamp' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('c'),
        ]);
    }

    public function readiness(): Response
    {
        $checks = [];
        $allReady = true;

        try {
            $this->connection->executeQuery('SELECT 1');
            $checks['database'] = 'ready';
        } catch (\Throwable $e) {
            $checks['database'] = 'not_ready: ' . $e->getMessage();
            $allReady = false;
        }

        $statusCode = $allReady ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE;

        return new JsonResponse([
            'status' => $allReady ? 'ready' : 'not_ready',
            'checks' => $checks,
            'timestamp' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('c'),
        ], $statusCode);
    }
}
