<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Support\Fakes;

use SovereignStack\Lms\Shared\Application\Transaction\TransactionManagerInterface;

final class FakeTransactionManager implements TransactionManagerInterface
{
    public int $calls = 0;

    public function transactional(callable $operation): mixed
    {
        ++$this->calls;

        return $operation();
    }
}
