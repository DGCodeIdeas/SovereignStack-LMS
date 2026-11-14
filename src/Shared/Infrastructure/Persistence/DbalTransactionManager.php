<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Shared\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;
use SovereignStack\Lms\Shared\Application\Transaction\TransactionManagerInterface;

final readonly class DbalTransactionManager implements TransactionManagerInterface
{
    public function __construct(private Connection $connection) {}

    public function transactional(callable $operation): mixed
    {
        $this->connection->beginTransaction();

        try {
            $result = $operation();
            $this->connection->commit();

            return $result;
        } catch (\Throwable $exception) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }
    }
}
