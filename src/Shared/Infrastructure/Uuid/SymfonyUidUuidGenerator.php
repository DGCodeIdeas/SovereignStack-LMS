<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Shared\Infrastructure\Uuid;

use SovereignStack\Lms\Shared\Domain\Uuid\UuidGeneratorInterface;
use Symfony\Component\Uid\UuidV7;

final class SymfonyUidUuidGenerator implements UuidGeneratorInterface
{
    public function generate(): string
    {
        return (string) new UuidV7();
    }
}
