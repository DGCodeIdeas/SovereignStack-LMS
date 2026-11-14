<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Shared\Presentation\Controller;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController
{
    #[Route('/health', name: 'app_health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'ok',
            'checks' => ['application' => 'ok'],
        ]);
    }

    #[Route('/ready', name: 'app_ready', methods: ['GET'])]
    public function ready(Connection $connection, LoggerInterface $logger): JsonResponse
    {
        try {
            $connection->executeQuery('SELECT 1');
        } catch (\Throwable $e) {
            $logger->error('Readiness check failed: {error}', ['error' => $e->getMessage()]);

            return new JsonResponse([
                'status' => 'not_ready',
                'checks' => ['database' => 'not_ready'],
            ], 503);
        }

        return new JsonResponse([
            'status' => 'ok',
            'checks' => ['application' => 'ok', 'database' => 'ok'],
        ]);
    }
}
